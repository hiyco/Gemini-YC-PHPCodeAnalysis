/*
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Context Management and Caching System for PHP Analysis
 */

import { EventEmitter } from 'events';
import * as fs from 'fs/promises';
import * as path from 'path';
import { createHash } from 'crypto';
import { LRUCache } from 'lru-cache';
import chokidar from 'chokidar';

export interface ProjectContext {
  id: string;
  root: string;
  phpVersion: string;
  frameworks: string[];
  dependencies: Map<string, string>;
  autoloadMap: Map<string, string>;
  classMap: Map<string, ClassInfo>;
  functionMap: Map<string, FunctionInfo>;
  constantMap: Map<string, ConstantInfo>;
  fileMap: Map<string, FileInfo>;
  dependencyGraph: DependencyGraph;
  lastModified: Date;
  metadata: ProjectMetadata;
}

export interface FileInfo {
  path: string;
  hash: string;
  lastModified: Date;
  size: number;
  encoding: string;
  ast?: any;
  symbols: SymbolInfo[];
  diagnostics: any[];
  dependencies: string[];
  metrics: FileMetrics;
}

export interface SymbolInfo {
  name: string;
  kind: SymbolKind;
  location: Range;
  signature?: string;
  visibility?: 'public' | 'protected' | 'private';
  isStatic?: boolean;
  documentation?: string;
  tags?: string[];
}

export interface ClassInfo extends SymbolInfo {
  namespace: string;
  extends?: string;
  implements: string[];
  traits: string[];
  properties: PropertyInfo[];
  methods: MethodInfo[];
  constants: ConstantInfo[];
  isAbstract: boolean;
  isFinal: boolean;
}

export interface MethodInfo extends SymbolInfo {
  parameters: ParameterInfo[];
  returnType?: string;
  isAbstract: boolean;
  isFinal: boolean;
  throwsExceptions: string[];
}

export interface PropertyInfo extends SymbolInfo {
  type?: string;
  defaultValue?: string;
  isReadonly: boolean;
}

export interface FunctionInfo extends SymbolInfo {
  parameters: ParameterInfo[];
  returnType?: string;
  throwsExceptions: string[];
}

export interface ParameterInfo {
  name: string;
  type?: string;
  defaultValue?: any;
  isVariadic: boolean;
  isOptional: boolean;
  byReference: boolean;
}

export interface ConstantInfo extends SymbolInfo {
  value: any;
  type: string;
}

export interface DependencyGraph {
  nodes: Map<string, DependencyNode>;
  edges: Map<string, Set<string>>;
}

export interface DependencyNode {
  file: string;
  type: 'class' | 'function' | 'constant' | 'file';
  dependencies: Set<string>;
  dependents: Set<string>;
}

export interface FileMetrics {
  linesOfCode: number;
  physicalLines: number;
  logicalLines: number;
  cyclomaticComplexity: number;
  cognitiveComplexity: number;
  maintainabilityIndex: number;
  duplicatedLines: number;
}

export interface ProjectMetadata {
  name: string;
  version: string;
  description?: string;
  author?: string;
  license?: string;
  composerConfig?: any;
  gitInfo?: GitInfo;
  buildInfo?: BuildInfo;
}

export interface GitInfo {
  branch: string;
  commit: string;
  remoteUrl?: string;
  isDirty: boolean;
}

export interface BuildInfo {
  buildTime: Date;
  buildNumber?: string;
  environment?: string;
}

export enum SymbolKind {
  File = 1,
  Module = 2,
  Namespace = 3,
  Package = 4,
  Class = 5,
  Method = 6,
  Property = 7,
  Field = 8,
  Constructor = 9,
  Enum = 10,
  Interface = 11,
  Function = 12,
  Variable = 13,
  Constant = 14,
  String = 15,
  Number = 16,
  Boolean = 17,
  Array = 18,
  Object = 19,
  Key = 20,
  Null = 21,
  EnumMember = 22,
  Struct = 23,
  Event = 24,
  Operator = 25,
  TypeParameter = 26,
}

export interface Range {
  start: { line: number; character: number };
  end: { line: number; character: number };
}

export class ContextManager extends EventEmitter {
  private projects = new Map<string, ProjectContext>();
  private fileCache = new LRUCache<string, FileInfo>({
    max: 10000,
    maxSize: 512 * 1024 * 1024, // 512MB
    sizeCalculation: (value) => JSON.stringify(value).length,
    dispose: (value, key) => {
      this.emit('file-cache-evicted', key, value);
    },
  });
  
  private astCache = new LRUCache<string, any>({
    max: 5000,
    maxSize: 256 * 1024 * 1024, // 256MB
    sizeCalculation: (value) => JSON.stringify(value).length,
  });

  private analysisCache = new LRUCache<string, any>({
    max: 1000,
    maxSize: 128 * 1024 * 1024, // 128MB
    sizeCalculation: (value) => JSON.stringify(value).length,
    ttl: 30 * 60 * 1000, // 30 minutes
  });

  private watchers = new Map<string, chokidar.FSWatcher>();
  private isShuttingDown = false;

  constructor() {
    super();
    this.setupPeriodicCleanup();
    this.setupGracefulShutdown();
  }

  /**
   * Initialize a project context
   */
  public async initializeProject(rootPath: string, options: {
    phpVersion?: string;
    frameworks?: string[];
    excludePaths?: string[];
  } = {}): Promise<ProjectContext> {
    const normalizedRoot = path.resolve(rootPath);
    const projectId = this.generateProjectId(normalizedRoot);

    // Check if project already exists
    if (this.projects.has(projectId)) {
      return this.projects.get(projectId)!;
    }

    console.log(`Initializing project context: ${normalizedRoot}`);

    const context: ProjectContext = {
      id: projectId,
      root: normalizedRoot,
      phpVersion: options.phpVersion || '8.2',
      frameworks: options.frameworks || [],
      dependencies: new Map(),
      autoloadMap: new Map(),
      classMap: new Map(),
      functionMap: new Map(),
      constantMap: new Map(),
      fileMap: new Map(),
      dependencyGraph: { nodes: new Map(), edges: new Map() },
      lastModified: new Date(),
      metadata: await this.loadProjectMetadata(normalizedRoot),
    };

    // Load composer dependencies
    await this.loadComposerDependencies(context);

    // Build initial file index
    await this.buildFileIndex(context, options.excludePaths);

    // Parse PHP files and build symbol tables
    await this.buildSymbolTables(context);

    // Build dependency graph
    await this.buildDependencyGraph(context);

    // Setup file watcher
    this.setupFileWatcher(context, options.excludePaths);

    this.projects.set(projectId, context);
    this.emit('project-initialized', context);

    console.log(`Project context initialized: ${context.fileMap.size} files, ${context.classMap.size} classes`);
    return context;
  }

  /**
   * Get project context by root path
   */
  public getProject(rootPath: string): ProjectContext | undefined {
    const projectId = this.generateProjectId(path.resolve(rootPath));
    return this.projects.get(projectId);
  }

  /**
   * Get file info with caching
   */
  public async getFileInfo(filePath: string, projectId?: string): Promise<FileInfo | undefined> {
    const normalizedPath = path.resolve(filePath);
    const cacheKey = `${projectId || 'global'}:${normalizedPath}`;

    // Check cache first
    let fileInfo = this.fileCache.get(cacheKey);
    if (fileInfo) {
      // Verify file hasn't changed
      try {
        const stat = await fs.stat(normalizedPath);
        if (stat.mtime <= fileInfo.lastModified) {
          return fileInfo;
        }
      } catch (error) {
        // File might have been deleted
        this.fileCache.delete(cacheKey);
        return undefined;
      }
    }

    // File changed or not in cache, read and parse
    try {
      const content = await fs.readFile(normalizedPath, 'utf-8');
      const stat = await fs.stat(normalizedPath);
      const hash = this.generateFileHash(content);

      fileInfo = {
        path: normalizedPath,
        hash,
        lastModified: stat.mtime,
        size: stat.size,
        encoding: 'utf-8',
        symbols: [],
        diagnostics: [],
        dependencies: [],
        metrics: {
          linesOfCode: 0,
          physicalLines: content.split('\n').length,
          logicalLines: 0,
          cyclomaticComplexity: 1,
          cognitiveComplexity: 0,
          maintainabilityIndex: 100,
          duplicatedLines: 0,
        },
      };

      // Parse PHP file if it's a PHP file
      if (normalizedPath.endsWith('.php')) {
        await this.parsePhpFile(fileInfo, content);
      }

      // Cache the file info
      this.fileCache.set(cacheKey, fileInfo);
      return fileInfo;
    } catch (error) {
      console.error(`Error reading file ${normalizedPath}:`, error);
      return undefined;
    }
  }

  /**
   * Get cached analysis result
   */
  public getCachedAnalysis(key: string): any | undefined {
    return this.analysisCache.get(key);
  }

  /**
   * Set cached analysis result
   */
  public setCachedAnalysis(key: string, result: any, ttl?: number): void {
    this.analysisCache.set(key, result, { ttl });
  }

  /**
   * Invalidate caches for a file
   */
  public invalidateFile(filePath: string): void {
    const normalizedPath = path.resolve(filePath);
    
    // Remove from all caches
    for (const [key] of this.fileCache.entries()) {
      if (key.includes(normalizedPath)) {
        this.fileCache.delete(key);
      }
    }

    for (const [key] of this.astCache.entries()) {
      if (key.includes(normalizedPath)) {
        this.astCache.delete(key);
      }
    }

    for (const [key] of this.analysisCache.entries()) {
      if (key.includes(normalizedPath)) {
        this.analysisCache.delete(key);
      }
    }

    // Update project context if file belongs to a project
    for (const project of this.projects.values()) {
      if (normalizedPath.startsWith(project.root)) {
        this.updateProjectForFile(project, normalizedPath);
        break;
      }
    }

    this.emit('file-invalidated', normalizedPath);
  }

  /**
   * Get context-aware completions
   */
  public async getCompletions(filePath: string, position: { line: number; character: number }): Promise<any[]> {
    const fileInfo = await this.getFileInfo(filePath);
    if (!fileInfo) return [];

    const cacheKey = `completions:${filePath}:${position.line}:${position.character}:${fileInfo.hash}`;
    let completions = this.getCachedAnalysis(cacheKey);
    if (completions) return completions;

    // Generate completions based on context
    completions = await this.generateCompletions(fileInfo, position);
    this.setCachedAnalysis(cacheKey, completions, 10 * 60 * 1000); // 10 minutes

    return completions;
  }

  /**
   * Find symbol references across project
   */
  public async findReferences(symbol: string, projectId: string): Promise<any[]> {
    const project = this.projects.get(projectId);
    if (!project) return [];

    const cacheKey = `references:${projectId}:${symbol}`;
    let references = this.getCachedAnalysis(cacheKey);
    if (references) return references;

    references = [];
    for (const fileInfo of project.fileMap.values()) {
      const fileReferences = this.findSymbolInFile(fileInfo, symbol);
      references.push(...fileReferences);
    }

    this.setCachedAnalysis(cacheKey, references, 15 * 60 * 1000); // 15 minutes
    return references;
  }

  private generateProjectId(rootPath: string): string {
    return createHash('md5').update(rootPath).digest('hex').substring(0, 16);
  }

  private generateFileHash(content: string): string {
    return createHash('md5').update(content).digest('hex');
  }

  private async loadProjectMetadata(rootPath: string): Promise<ProjectMetadata> {
    const metadata: ProjectMetadata = {
      name: path.basename(rootPath),
      version: '1.0.0',
    };

    // Try to load composer.json
    try {
      const composerPath = path.join(rootPath, 'composer.json');
      const composerContent = await fs.readFile(composerPath, 'utf-8');
      metadata.composerConfig = JSON.parse(composerContent);
      metadata.name = metadata.composerConfig.name || metadata.name;
      metadata.version = metadata.composerConfig.version || metadata.version;
      metadata.description = metadata.composerConfig.description;
      metadata.author = metadata.composerConfig.authors?.[0]?.name;
      metadata.license = metadata.composerConfig.license;
    } catch (error) {
      // composer.json not found or invalid, use defaults
    }

    // Try to get Git info
    try {
      metadata.gitInfo = await this.getGitInfo(rootPath);
    } catch (error) {
      // Not a git repository or git not available
    }

    return metadata;
  }

  private async getGitInfo(rootPath: string): Promise<GitInfo> {
    const { execSync } = require('child_process');
    
    const branch = execSync('git rev-parse --abbrev-ref HEAD', { 
      cwd: rootPath, 
      encoding: 'utf-8' 
    }).trim();
    
    const commit = execSync('git rev-parse HEAD', { 
      cwd: rootPath, 
      encoding: 'utf-8' 
    }).trim();
    
    let remoteUrl: string | undefined;
    try {
      remoteUrl = execSync('git config --get remote.origin.url', { 
        cwd: rootPath, 
        encoding: 'utf-8' 
      }).trim();
    } catch (error) {
      // No remote origin
    }
    
    const isDirty = execSync('git status --porcelain', { 
      cwd: rootPath, 
      encoding: 'utf-8' 
    }).trim().length > 0;

    return { branch, commit, remoteUrl, isDirty };
  }

  private async loadComposerDependencies(context: ProjectContext): Promise<void> {
    try {
      const composerLockPath = path.join(context.root, 'composer.lock');
      const lockContent = await fs.readFile(composerLockPath, 'utf-8');
      const lockData = JSON.parse(lockContent);

      for (const package of lockData.packages || []) {
        context.dependencies.set(package.name, package.version);
      }

      for (const package of lockData['packages-dev'] || []) {
        context.dependencies.set(package.name, package.version);
      }
    } catch (error) {
      console.warn('Could not load composer.lock:', error.message);
    }

    // Load autoload map
    try {
      const vendorAutoloadPath = path.join(context.root, 'vendor', 'composer', 'autoload_classmap.php');
      const autoloadContent = await fs.readFile(vendorAutoloadPath, 'utf-8');
      // Parse PHP array to extract class mappings
      // This is a simplified implementation
      const matches = autoloadContent.match(/'([^']+)' => \$baseDir \. '([^']+)'/g);
      if (matches) {
        for (const match of matches) {
          const [, className, filePath] = match.match(/'([^']+)' => \$baseDir \. '([^']+)'/) || [];
          if (className && filePath) {
            context.autoloadMap.set(className, path.join(context.root, filePath));
          }
        }
      }
    } catch (error) {
      // No vendor autoload or error reading it
    }
  }

  private async buildFileIndex(context: ProjectContext, excludePaths?: string[]): Promise<void> {
    const excludePatterns = excludePaths || ['vendor', 'node_modules', '.git', 'cache', 'storage'];
    
    const findPhpFiles = async (dir: string): Promise<string[]> => {
      const files: string[] = [];
      
      try {
        const entries = await fs.readdir(dir, { withFileTypes: true });
        
        for (const entry of entries) {
          const fullPath = path.join(dir, entry.name);
          
          if (entry.isDirectory()) {
            const relativePath = path.relative(context.root, fullPath);
            if (!excludePatterns.some(pattern => relativePath.includes(pattern))) {
              files.push(...await findPhpFiles(fullPath));
            }
          } else if (entry.name.endsWith('.php')) {
            files.push(fullPath);
          }
        }
      } catch (error) {
        console.warn(`Error reading directory ${dir}:`, error.message);
      }
      
      return files;
    };

    const phpFiles = await findPhpFiles(context.root);
    
    for (const filePath of phpFiles) {
      const fileInfo = await this.getFileInfo(filePath, context.id);
      if (fileInfo) {
        context.fileMap.set(filePath, fileInfo);
      }
    }
  }

  private async buildSymbolTables(context: ProjectContext): Promise<void> {
    for (const fileInfo of context.fileMap.values()) {
      this.extractSymbols(fileInfo, context);
    }
  }

  private async buildDependencyGraph(context: ProjectContext): Promise<void> {
    // Build dependency relationships between files
    for (const fileInfo of context.fileMap.values()) {
      const node: DependencyNode = {
        file: fileInfo.path,
        type: 'file',
        dependencies: new Set(fileInfo.dependencies),
        dependents: new Set(),
      };
      
      context.dependencyGraph.nodes.set(fileInfo.path, node);
      context.dependencyGraph.edges.set(fileInfo.path, new Set(fileInfo.dependencies));
    }

    // Build reverse dependencies (dependents)
    for (const [file, dependencies] of context.dependencyGraph.edges.entries()) {
      for (const dependency of dependencies) {
        const depNode = context.dependencyGraph.nodes.get(dependency);
        if (depNode) {
          depNode.dependents.add(file);
        }
      }
    }
  }

  private setupFileWatcher(context: ProjectContext, excludePaths?: string[]): void {
    const excludePatterns = excludePaths || ['vendor', 'node_modules', '.git'];
    
    const watcher = chokidar.watch('**/*.php', {
      cwd: context.root,
      ignored: excludePatterns.map(p => `**/${p}/**`),
      ignoreInitial: true,
      persistent: true,
    });

    watcher
      .on('change', (filePath) => {
        const fullPath = path.resolve(context.root, filePath);
        this.invalidateFile(fullPath);
        this.emit('file-changed', fullPath, context);
      })
      .on('add', (filePath) => {
        const fullPath = path.resolve(context.root, filePath);
        this.updateProjectForFile(context, fullPath);
        this.emit('file-added', fullPath, context);
      })
      .on('unlink', (filePath) => {
        const fullPath = path.resolve(context.root, filePath);
        context.fileMap.delete(fullPath);
        this.invalidateFile(fullPath);
        this.emit('file-removed', fullPath, context);
      });

    this.watchers.set(context.id, watcher);
  }

  private async parsePhpFile(fileInfo: FileInfo, content: string): Promise<void> {
    // This would use a PHP parser like php-parser
    // For now, implement basic symbol extraction
    await this.extractBasicSymbols(fileInfo, content);
  }

  private async extractBasicSymbols(fileInfo: FileInfo, content: string): Promise<void> {
    const lines = content.split('\n');
    
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i].trim();
      
      // Extract classes
      const classMatch = line.match(/^(?:abstract\s+|final\s+)?class\s+(\w+)/);
      if (classMatch) {
        fileInfo.symbols.push({
          name: classMatch[1],
          kind: SymbolKind.Class,
          location: {
            start: { line: i, character: line.indexOf(classMatch[1]) },
            end: { line: i, character: line.indexOf(classMatch[1]) + classMatch[1].length },
          },
        });
      }
      
      // Extract functions
      const functionMatch = line.match(/^(?:public\s+|private\s+|protected\s+)?function\s+(\w+)/);
      if (functionMatch) {
        fileInfo.symbols.push({
          name: functionMatch[1],
          kind: SymbolKind.Function,
          location: {
            start: { line: i, character: line.indexOf(functionMatch[1]) },
            end: { line: i, character: line.indexOf(functionMatch[1]) + functionMatch[1].length },
          },
        });
      }
    }
    
    // Update metrics
    fileInfo.metrics.linesOfCode = lines.filter(line => line.trim().length > 0).length;
    fileInfo.metrics.logicalLines = lines.filter(line => 
      line.trim().length > 0 && !line.trim().startsWith('//') && !line.trim().startsWith('/*')
    ).length;
  }

  private extractSymbols(fileInfo: FileInfo, context: ProjectContext): void {
    for (const symbol of fileInfo.symbols) {
      switch (symbol.kind) {
        case SymbolKind.Class:
          // This would be a proper ClassInfo object
          context.classMap.set(symbol.name, symbol as ClassInfo);
          break;
        case SymbolKind.Function:
          context.functionMap.set(symbol.name, symbol as FunctionInfo);
          break;
        case SymbolKind.Constant:
          context.constantMap.set(symbol.name, symbol as ConstantInfo);
          break;
      }
    }
  }

  private async updateProjectForFile(context: ProjectContext, filePath: string): Promise<void> {
    const fileInfo = await this.getFileInfo(filePath, context.id);
    if (fileInfo) {
      context.fileMap.set(filePath, fileInfo);
      this.extractSymbols(fileInfo, context);
      context.lastModified = new Date();
    }
  }

  private async generateCompletions(fileInfo: FileInfo, position: { line: number; character: number }): Promise<any[]> {
    // Implement context-aware completion generation
    return [];
  }

  private findSymbolInFile(fileInfo: FileInfo, symbol: string): any[] {
    // Find all occurrences of symbol in file
    return [];
  }

  private setupPeriodicCleanup(): void {
    setInterval(() => {
      if (!this.isShuttingDown) {
        this.performCacheCleanup();
      }
    }, 5 * 60 * 1000); // Every 5 minutes
  }

  private performCacheCleanup(): void {
    // LRU caches clean themselves, but we can do additional cleanup here
    const before = {
      fileCache: this.fileCache.size,
      astCache: this.astCache.size,
      analysisCache: this.analysisCache.size,
    };

    // Force garbage collection if available
    if (global.gc) {
      global.gc();
    }

    console.log('Cache cleanup completed:', {
      before,
      after: {
        fileCache: this.fileCache.size,
        astCache: this.astCache.size,
        analysisCache: this.analysisCache.size,
      },
    });
  }

  private setupGracefulShutdown(): void {
    const shutdown = async () => {
      this.isShuttingDown = true;
      console.log('Shutting down context manager...');

      // Close all file watchers
      for (const [projectId, watcher] of this.watchers.entries()) {
        await watcher.close();
        console.log(`Closed file watcher for project ${projectId}`);
      }

      // Clear all caches
      this.fileCache.clear();
      this.astCache.clear();
      this.analysisCache.clear();

      console.log('Context manager shutdown complete');
    };

    process.on('SIGTERM', shutdown);
    process.on('SIGINT', shutdown);
  }

  /**
   * Get memory usage statistics
   */
  public getMemoryUsage(): {
    fileCache: { size: number; maxSize: number; calculatedSize: number };
    astCache: { size: number; maxSize: number; calculatedSize: number };
    analysisCache: { size: number; maxSize: number; calculatedSize: number };
    projects: number;
    totalSymbols: number;
  } {
    let totalSymbols = 0;
    for (const project of this.projects.values()) {
      totalSymbols += project.classMap.size + project.functionMap.size + project.constantMap.size;
    }

    return {
      fileCache: {
        size: this.fileCache.size,
        maxSize: this.fileCache.max,
        calculatedSize: this.fileCache.calculatedSize || 0,
      },
      astCache: {
        size: this.astCache.size,
        maxSize: this.astCache.max,
        calculatedSize: this.astCache.calculatedSize || 0,
      },
      analysisCache: {
        size: this.analysisCache.size,
        maxSize: this.analysisCache.max,
        calculatedSize: this.analysisCache.calculatedSize || 0,
      },
      projects: this.projects.size,
      totalSymbols,
    };
  }
}