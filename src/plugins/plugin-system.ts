/*
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Plugin System Architecture for PHP Analysis Server
 */

import { EventEmitter } from 'events';
import * as path from 'path';
import * as fs from 'fs/promises';
import { Worker } from 'worker_threads';
import { createHash } from 'crypto';
import { ContextManager } from '../context/context-manager';

export interface PluginManifest {
  name: string;
  version: string;
  description: string;
  author: string;
  license: string;
  main: string;
  type: PluginType;
  capabilities: PluginCapabilities;
  dependencies?: Record<string, string>;
  phpVersions?: string[];
  frameworks?: string[];
  hooks: string[];
  permissions: PluginPermissions;
  metadata?: Record<string, any>;
}

export interface PluginCapabilities {
  analysis?: {
    syntax?: boolean;
    semantics?: boolean;
    security?: boolean;
    performance?: boolean;
    quality?: boolean;
  };
  completion?: {
    basic?: boolean;
    contextAware?: boolean;
    snippets?: boolean;
  };
  refactoring?: {
    extractMethod?: boolean;
    extractClass?: boolean;
    rename?: boolean;
    moveFile?: boolean;
  };
  diagnostics?: {
    errors?: boolean;
    warnings?: boolean;
    suggestions?: boolean;
  };
  integration?: {
    externalTools?: string[];
    fileFormats?: string[];
  };
}

export interface PluginPermissions {
  fileSystem: {
    read: string[];
    write?: string[];
    execute?: string[];
  };
  network?: {
    domains: string[];
    protocols: string[];
  };
  system?: {
    commands: string[];
    environment: string[];
  };
  sensitive?: boolean;
}

export enum PluginType {
  ANALYZER = 'analyzer',
  COMPLETION = 'completion',
  REFACTORING = 'refactoring',
  SECURITY = 'security',
  PERFORMANCE = 'performance',
  INTEGRATION = 'integration',
  UTILITY = 'utility',
}

export enum PluginStatus {
  INSTALLED = 'installed',
  LOADED = 'loaded',
  ACTIVE = 'active',
  DISABLED = 'disabled',
  ERROR = 'error',
}

export interface PluginInstance {
  manifest: PluginManifest;
  status: PluginStatus;
  worker?: Worker;
  api?: PluginAPI;
  loadTime?: Date;
  lastUsed?: Date;
  errorCount: number;
  performanceMetrics: PluginMetrics;
  sandboxContext?: SandboxContext;
}

export interface PluginMetrics {
  executionTime: {
    total: number;
    average: number;
    min: number;
    max: number;
  };
  memoryUsage: {
    peak: number;
    average: number;
    current: number;
  };
  callCount: number;
  errorRate: number;
  lastError?: Error;
}

export interface SandboxContext {
  allowedPaths: Set<string>;
  allowedCommands: Set<string>;
  networkAccess: boolean;
  timeoutMs: number;
  memoryLimitMB: number;
}

export interface PluginAPI {
  name: string;
  version: string;
  analyze?: (request: AnalysisRequest) => Promise<AnalysisResult>;
  complete?: (request: CompletionRequest) => Promise<CompletionResult>;
  refactor?: (request: RefactorRequest) => Promise<RefactorResult>;
  validate?: (request: ValidationRequest) => Promise<ValidationResult>;
  initialize?: (context: PluginContext) => Promise<void>;
  dispose?: () => Promise<void>;
}

export interface PluginContext {
  projectRoot: string;
  contextManager: ContextManager;
  logger: PluginLogger;
  config: Record<string, any>;
  cache: PluginCache;
  events: PluginEventEmitter;
}

export interface PluginLogger {
  debug(message: string, data?: any): void;
  info(message: string, data?: any): void;
  warn(message: string, data?: any): void;
  error(message: string, error?: Error): void;
}

export interface PluginCache {
  get(key: string): Promise<any>;
  set(key: string, value: any, ttlMs?: number): Promise<void>;
  delete(key: string): Promise<void>;
  clear(): Promise<void>;
}

export interface PluginEventEmitter extends EventEmitter {
  onAnalysis(callback: (result: AnalysisResult) => void): void;
  onCompletion(callback: (result: CompletionResult) => void): void;
  onRefactor(callback: (result: RefactorResult) => void): void;
  onError(callback: (error: Error) => void): void;
}

export interface AnalysisRequest {
  filePath: string;
  content: string;
  type: 'syntax' | 'semantics' | 'security' | 'performance' | 'quality';
  context: any;
  options?: Record<string, any>;
}

export interface AnalysisResult {
  diagnostics: any[];
  metrics?: any;
  suggestions?: any[];
  security?: any[];
  performance?: any[];
}

export interface CompletionRequest {
  filePath: string;
  content: string;
  position: { line: number; character: number };
  context: any;
  trigger?: string;
}

export interface CompletionResult {
  items: any[];
  isIncomplete?: boolean;
}

export interface RefactorRequest {
  filePath: string;
  content: string;
  type: string;
  selection?: any;
  options?: Record<string, any>;
}

export interface RefactorResult {
  edits: any[];
  description: string;
}

export interface ValidationRequest {
  manifest: PluginManifest;
  code: string;
}

export interface ValidationResult {
  valid: boolean;
  errors: string[];
  warnings: string[];
}

export class PluginManager extends EventEmitter {
  private plugins = new Map<string, PluginInstance>();
  private pluginPaths = new Set<string>();
  private contextManager: ContextManager;
  private securityPolicy: SecurityPolicy;
  private performanceMonitor: PerformanceMonitor;

  constructor(contextManager: ContextManager) {
    super();
    this.contextManager = contextManager;
    this.securityPolicy = new SecurityPolicy();
    this.performanceMonitor = new PerformanceMonitor();
    
    this.setupPerformanceMonitoring();
    this.setupSecurityMonitoring();
  }

  /**
   * Load plugin from directory
   */
  public async loadPlugin(pluginPath: string): Promise<PluginInstance> {
    const normalizedPath = path.resolve(pluginPath);
    
    console.log(`Loading plugin from: ${normalizedPath}`);

    // Read plugin manifest
    const manifestPath = path.join(normalizedPath, 'package.json');
    const manifestContent = await fs.readFile(manifestPath, 'utf-8');
    const manifest = JSON.parse(manifestContent) as PluginManifest;

    // Validate plugin manifest
    await this.validatePlugin(manifest, normalizedPath);

    // Check security permissions
    await this.securityPolicy.validatePermissions(manifest);

    // Create plugin instance
    const instance: PluginInstance = {
      manifest,
      status: PluginStatus.INSTALLED,
      errorCount: 0,
      performanceMetrics: {
        executionTime: { total: 0, average: 0, min: 0, max: 0 },
        memoryUsage: { peak: 0, average: 0, current: 0 },
        callCount: 0,
        errorRate: 0,
      },
      sandboxContext: this.createSandboxContext(manifest),
    };

    // Load plugin code in sandbox
    await this.loadPluginCode(instance, normalizedPath);

    this.plugins.set(manifest.name, instance);
    this.pluginPaths.add(normalizedPath);

    instance.status = PluginStatus.LOADED;
    instance.loadTime = new Date();

    this.emit('plugin-loaded', instance);
    console.log(`Plugin loaded: ${manifest.name} v${manifest.version}`);

    return instance;
  }

  /**
   * Activate plugin
   */
  public async activatePlugin(name: string): Promise<void> {
    const instance = this.plugins.get(name);
    if (!instance) {
      throw new Error(`Plugin not found: ${name}`);
    }

    if (instance.status === PluginStatus.ACTIVE) {
      return;
    }

    try {
      // Initialize plugin
      if (instance.api?.initialize) {
        const context = this.createPluginContext(name);
        await this.executeWithTimeout(
          () => instance.api!.initialize!(context),
          30000, // 30 seconds timeout
          `Plugin ${name} initialization timeout`
        );
      }

      instance.status = PluginStatus.ACTIVE;
      this.emit('plugin-activated', instance);
      
      console.log(`Plugin activated: ${name}`);
    } catch (error) {
      instance.status = PluginStatus.ERROR;
      instance.errorCount++;
      instance.performanceMetrics.lastError = error as Error;
      
      this.emit('plugin-error', instance, error);
      throw error;
    }
  }

  /**
   * Deactivate plugin
   */
  public async deactivatePlugin(name: string): Promise<void> {
    const instance = this.plugins.get(name);
    if (!instance) {
      throw new Error(`Plugin not found: ${name}`);
    }

    try {
      // Dispose plugin resources
      if (instance.api?.dispose) {
        await this.executeWithTimeout(
          () => instance.api!.dispose!(),
          10000, // 10 seconds timeout
          `Plugin ${name} disposal timeout`
        );
      }

      // Terminate worker if exists
      if (instance.worker) {
        await instance.worker.terminate();
        instance.worker = undefined;
      }

      instance.status = PluginStatus.DISABLED;
      this.emit('plugin-deactivated', instance);
      
      console.log(`Plugin deactivated: ${name}`);
    } catch (error) {
      instance.errorCount++;
      console.error(`Error deactivating plugin ${name}:`, error);
      throw error;
    }
  }

  /**
   * Execute plugin analysis
   */
  public async executeAnalysis(
    pluginName: string, 
    request: AnalysisRequest
  ): Promise<AnalysisResult | null> {
    const instance = this.plugins.get(pluginName);
    if (!instance || instance.status !== PluginStatus.ACTIVE) {
      return null;
    }

    if (!instance.api?.analyze) {
      return null;
    }

    const startTime = process.hrtime.bigint();
    let result: AnalysisResult | null = null;

    try {
      // Update usage tracking
      instance.lastUsed = new Date();
      instance.performanceMetrics.callCount++;

      // Execute with performance monitoring
      result = await this.performanceMonitor.measure(
        `${pluginName}.analyze`,
        () => this.executeWithTimeout(
          () => instance.api!.analyze!(request),
          60000, // 60 seconds timeout
          `Plugin ${pluginName} analysis timeout`
        )
      );

      // Update performance metrics
      const endTime = process.hrtime.bigint();
      const executionTime = Number(endTime - startTime) / 1_000_000; // Convert to milliseconds

      this.updatePerformanceMetrics(instance, executionTime);

      return result;
    } catch (error) {
      instance.errorCount++;
      instance.performanceMetrics.lastError = error as Error;
      
      // Calculate error rate
      instance.performanceMetrics.errorRate = 
        instance.errorCount / instance.performanceMetrics.callCount;

      this.emit('plugin-error', instance, error);
      throw error;
    }
  }

  /**
   * Execute plugin completion
   */
  public async executeCompletion(
    pluginName: string,
    request: CompletionRequest
  ): Promise<CompletionResult | null> {
    const instance = this.plugins.get(pluginName);
    if (!instance || instance.status !== PluginStatus.ACTIVE || !instance.api?.complete) {
      return null;
    }

    return this.executeWithMetrics(instance, 'complete', () =>
      instance.api!.complete!(request)
    );
  }

  /**
   * Execute plugin refactoring
   */
  public async executeRefactor(
    pluginName: string,
    request: RefactorRequest
  ): Promise<RefactorResult | null> {
    const instance = this.plugins.get(pluginName);
    if (!instance || instance.status !== PluginStatus.ACTIVE || !instance.api?.refactor) {
      return null;
    }

    return this.executeWithMetrics(instance, 'refactor', () =>
      instance.api!.refactor!(request)
    );
  }

  /**
   * Get plugin by capability
   */
  public getPluginsByCapability(capability: string): PluginInstance[] {
    const plugins: PluginInstance[] = [];
    
    for (const instance of this.plugins.values()) {
      if (instance.status === PluginStatus.ACTIVE) {
        if (this.hasCapability(instance, capability)) {
          plugins.push(instance);
        }
      }
    }

    // Sort by performance and error rate
    return plugins.sort((a, b) => {
      const scoreA = this.calculatePluginScore(a);
      const scoreB = this.calculatePluginScore(b);
      return scoreB - scoreA;
    });
  }

  /**
   * Get all plugins with their status
   */
  public getAllPlugins(): Map<string, PluginInstance> {
    return new Map(this.plugins);
  }

  /**
   * Get plugin performance metrics
   */
  public getPerformanceMetrics(): Record<string, any> {
    const metrics: Record<string, any> = {};
    
    for (const [name, instance] of this.plugins.entries()) {
      metrics[name] = {
        status: instance.status,
        performance: instance.performanceMetrics,
        loadTime: instance.loadTime,
        lastUsed: instance.lastUsed,
        errorCount: instance.errorCount,
      };
    }

    return metrics;
  }

  private async validatePlugin(manifest: PluginManifest, pluginPath: string): Promise<void> {
    // Basic manifest validation
    const requiredFields = ['name', 'version', 'main', 'type', 'capabilities'];
    for (const field of requiredFields) {
      if (!manifest[field as keyof PluginManifest]) {
        throw new Error(`Plugin manifest missing required field: ${field}`);
      }
    }

    // Validate plugin main file exists
    const mainPath = path.join(pluginPath, manifest.main);
    try {
      await fs.access(mainPath);
    } catch (error) {
      throw new Error(`Plugin main file not found: ${manifest.main}`);
    }

    // Validate plugin type
    if (!Object.values(PluginType).includes(manifest.type)) {
      throw new Error(`Invalid plugin type: ${manifest.type}`);
    }
  }

  private createSandboxContext(manifest: PluginManifest): SandboxContext {
    return {
      allowedPaths: new Set(manifest.permissions.fileSystem.read),
      allowedCommands: new Set(manifest.permissions.system?.commands || []),
      networkAccess: !!manifest.permissions.network,
      timeoutMs: 60000, // 60 seconds
      memoryLimitMB: 256,
    };
  }

  private async loadPluginCode(instance: PluginInstance, pluginPath: string): Promise<void> {
    const mainPath = path.join(pluginPath, instance.manifest.main);
    
    // Load plugin in worker thread for isolation
    if (instance.manifest.permissions.sensitive) {
      instance.worker = new Worker(mainPath, {
        workerData: {
          manifest: instance.manifest,
          sandbox: instance.sandboxContext,
        },
      });

      // Setup worker communication
      instance.worker.on('message', (message) => {
        this.handleWorkerMessage(instance, message);
      });

      instance.worker.on('error', (error) => {
        instance.status = PluginStatus.ERROR;
        instance.errorCount++;
        this.emit('plugin-error', instance, error);
      });
    } else {
      // Load plugin directly for better performance
      try {
        const pluginModule = require(mainPath);
        instance.api = pluginModule.default || pluginModule;
      } catch (error) {
        throw new Error(`Failed to load plugin code: ${error}`);
      }
    }
  }

  private handleWorkerMessage(instance: PluginInstance, message: any): void {
    // Handle messages from worker thread
    switch (message.type) {
      case 'result':
        // Handle analysis result
        break;
      case 'error':
        instance.errorCount++;
        this.emit('plugin-error', instance, new Error(message.error));
        break;
      case 'log':
        console.log(`[Plugin ${instance.manifest.name}] ${message.message}`);
        break;
    }
  }

  private createPluginContext(pluginName: string): PluginContext {
    return {
      projectRoot: '',
      contextManager: this.contextManager,
      logger: new PluginLoggerImpl(pluginName),
      config: {},
      cache: new PluginCacheImpl(pluginName),
      events: new PluginEventEmitterImpl(),
    };
  }

  private async executeWithTimeout<T>(
    fn: () => Promise<T>,
    timeoutMs: number,
    timeoutMessage: string
  ): Promise<T> {
    return Promise.race([
      fn(),
      new Promise<never>((_, reject) => 
        setTimeout(() => reject(new Error(timeoutMessage)), timeoutMs)
      ),
    ]);
  }

  private async executeWithMetrics<T>(
    instance: PluginInstance,
    method: string,
    fn: () => Promise<T>
  ): Promise<T> {
    const startTime = process.hrtime.bigint();
    
    try {
      instance.lastUsed = new Date();
      instance.performanceMetrics.callCount++;

      const result = await this.performanceMonitor.measure(
        `${instance.manifest.name}.${method}`,
        () => this.executeWithTimeout(
          fn,
          30000, // 30 seconds timeout
          `Plugin ${instance.manifest.name} ${method} timeout`
        )
      );

      const endTime = process.hrtime.bigint();
      const executionTime = Number(endTime - startTime) / 1_000_000;
      this.updatePerformanceMetrics(instance, executionTime);

      return result;
    } catch (error) {
      instance.errorCount++;
      instance.performanceMetrics.lastError = error as Error;
      instance.performanceMetrics.errorRate = 
        instance.errorCount / instance.performanceMetrics.callCount;

      this.emit('plugin-error', instance, error);
      throw error;
    }
  }

  private updatePerformanceMetrics(instance: PluginInstance, executionTime: number): void {
    const metrics = instance.performanceMetrics;
    
    metrics.executionTime.total += executionTime;
    metrics.executionTime.average = metrics.executionTime.total / metrics.callCount;
    
    if (metrics.executionTime.min === 0 || executionTime < metrics.executionTime.min) {
      metrics.executionTime.min = executionTime;
    }
    
    if (executionTime > metrics.executionTime.max) {
      metrics.executionTime.max = executionTime;
    }
  }

  private hasCapability(instance: PluginInstance, capability: string): boolean {
    const capabilities = instance.manifest.capabilities as any;
    
    const parts = capability.split('.');
    let current = capabilities;
    
    for (const part of parts) {
      if (current && typeof current === 'object' && part in current) {
        current = current[part];
      } else {
        return false;
      }
    }
    
    return current === true;
  }

  private calculatePluginScore(instance: PluginInstance): number {
    const metrics = instance.performanceMetrics;
    
    // Lower execution time and error rate = higher score
    const timeScore = metrics.executionTime.average > 0 ? 1000 / metrics.executionTime.average : 1000;
    const errorScore = (1 - metrics.errorRate) * 100;
    
    return timeScore * 0.6 + errorScore * 0.4;
  }

  private setupPerformanceMonitoring(): void {
    setInterval(() => {
      for (const [name, instance] of this.plugins.entries()) {
        if (instance.status === PluginStatus.ACTIVE) {
          // Monitor memory usage, execution times, error rates
          this.performanceMonitor.recordMetrics(name, instance.performanceMetrics);
        }
      }
    }, 30000); // Every 30 seconds
  }

  private setupSecurityMonitoring(): void {
    this.on('plugin-error', (instance, error) => {
      this.securityPolicy.handlePluginError(instance, error);
    });
  }
}

// Helper classes
class SecurityPolicy {
  async validatePermissions(manifest: PluginManifest): Promise<void> {
    // Implement security policy validation
  }

  handlePluginError(instance: PluginInstance, error: Error): void {
    // Handle security-related plugin errors
  }
}

class PerformanceMonitor {
  async measure<T>(name: string, fn: () => Promise<T>): Promise<T> {
    const startTime = process.hrtime.bigint();
    try {
      return await fn();
    } finally {
      const endTime = process.hrtime.bigint();
      const duration = Number(endTime - startTime) / 1_000_000;
      console.debug(`Performance: ${name} took ${duration.toFixed(2)}ms`);
    }
  }

  recordMetrics(pluginName: string, metrics: PluginMetrics): void {
    // Record metrics for monitoring
  }
}

class PluginLoggerImpl implements PluginLogger {
  constructor(private pluginName: string) {}

  debug(message: string, data?: any): void {
    console.debug(`[Plugin ${this.pluginName}] DEBUG: ${message}`, data);
  }

  info(message: string, data?: any): void {
    console.info(`[Plugin ${this.pluginName}] INFO: ${message}`, data);
  }

  warn(message: string, data?: any): void {
    console.warn(`[Plugin ${this.pluginName}] WARN: ${message}`, data);
  }

  error(message: string, error?: Error): void {
    console.error(`[Plugin ${this.pluginName}] ERROR: ${message}`, error);
  }
}

class PluginCacheImpl implements PluginCache {
  private cache = new Map<string, { value: any; expires?: number }>();

  constructor(private pluginName: string) {}

  async get(key: string): Promise<any> {
    const entry = this.cache.get(`${this.pluginName}:${key}`);
    if (!entry) return undefined;
    
    if (entry.expires && Date.now() > entry.expires) {
      this.cache.delete(`${this.pluginName}:${key}`);
      return undefined;
    }
    
    return entry.value;
  }

  async set(key: string, value: any, ttlMs?: number): Promise<void> {
    const entry: any = { value };
    if (ttlMs) {
      entry.expires = Date.now() + ttlMs;
    }
    this.cache.set(`${this.pluginName}:${key}`, entry);
  }

  async delete(key: string): Promise<void> {
    this.cache.delete(`${this.pluginName}:${key}`);
  }

  async clear(): Promise<void> {
    for (const key of this.cache.keys()) {
      if (key.startsWith(`${this.pluginName}:`)) {
        this.cache.delete(key);
      }
    }
  }
}

class PluginEventEmitterImpl extends EventEmitter implements PluginEventEmitter {
  onAnalysis(callback: (result: AnalysisResult) => void): void {
    this.on('analysis', callback);
  }

  onCompletion(callback: (result: CompletionResult) => void): void {
    this.on('completion', callback);
  }

  onRefactor(callback: (result: RefactorResult) => void): void {
    this.on('refactor', callback);
  }

  onError(callback: (error: Error) => void): void {
    this.on('error', callback);
  }
}