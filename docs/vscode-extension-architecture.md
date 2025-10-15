# YC-PHPCodeAnalysis&MCP VSCode 扩展架构设计

## 1. 整体架构设计

### 1.1 架构概览

```
┌─────────────────────────────────────────────────────────────┐
│                    VSCode Extension Host                     │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────┐ │
│  │  Extension      │  │  Language       │  │   WebView    │ │
│  │  Main Process   │  │  Server         │  │   Provider   │ │
│  │                 │  │  (LSP Client)   │  │              │ │
│  └─────────────────┘  └─────────────────┘  └──────────────┘ │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────┐ │
│  │  Diagnostic     │  │  Quick Actions  │  │  Settings    │ │
│  │  Manager        │  │  Provider       │  │  Manager     │ │
│  └─────────────────┘  └─────────────────┘  └──────────────┘ │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────┐ │
│  │  MCP Client     │  │  Cache          │  │  UI          │ │
│  │  Connector      │  │  Manager        │  │  Components  │ │
│  └─────────────────┘  └─────────────────┘  └──────────────┘ │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│               YC-PHP-Analysis MCP Server                    │
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────┐ │
│  │  PHP Parser     │  │  Security       │  │  Performance │ │
│  │  & Analyzer     │  │  Analyzer       │  │  Analyzer    │ │
│  └─────────────────┘  └─────────────────┘  └──────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 核心模块结构

```typescript
// src/extension.ts - 扩展主入口
export interface ExtensionContext {
  mcpClient: MCPClientManager;
  diagnosticManager: DiagnosticManager;
  quickActionsProvider: QuickActionsProvider;
  webviewManager: WebviewManager;
  settingsManager: SettingsManager;
}

// 模块依赖关系
ExtensionMain → MCPClient → DiagnosticManager → QuickActions
              ↓
          WebviewManager ← SettingsManager
```

## 2. UI组件设计和用户交互流程

### 2.1 主要UI组件

#### 2.1.1 问题面板 (Problems Panel)
```typescript
interface ProblemsPanelConfig {
  // 问题分级显示
  severity: 'error' | 'warning' | 'info' | 'hint';
  
  // 问题分类
  categories: {
    syntax: boolean;
    security: boolean;
    performance: boolean;
    style: boolean;
  };
  
  // 实时筛选
  filters: {
    showFixed: boolean;
    showIgnored: boolean;
    filePattern?: string;
  };
}
```

#### 2.1.2 代码装饰器 (Code Decorations)
```typescript
interface CodeDecorationConfig {
  // 问题高亮类型
  highlightTypes: {
    error: vscode.TextEditorDecorationType;
    warning: vscode.TextEditorDecorationType;
    suggestion: vscode.TextEditorDecorationType;
  };
  
  // 内联提示
  inlineHints: {
    showSeverity: boolean;
    showQuickFix: boolean;
    maxHintLength: number;
  };
}
```

#### 2.1.3 分析报告WebView
```typescript
interface AnalysisReportView {
  // 项目概览
  overview: {
    filesAnalyzed: number;
    issuesFound: number;
    securityRisks: number;
    performanceIssues: number;
  };
  
  // 详细统计
  statistics: {
    issuesByFile: Record<string, number>;
    issuesByType: Record<string, number>;
    trendData: Array<{date: string, count: number}>;
  };
  
  // 交互功能
  actions: {
    exportReport: () => void;
    filterByType: (type: string) => void;
    navigateToIssue: (issue: Diagnostic) => void;
  };
}
```

### 2.2 用户交互流程

#### 2.2.1 实时分析流程
```
文件保存/打开 → 触发分析请求 → MCP服务器分析 → 返回诊断结果 → 更新UI显示
     ↓              ↓              ↓              ↓              ↓
  <200ms        缓存检查      异步处理       增量更新      用户体验无感知
```

#### 2.2.2 快速修复流程
```
用户hover问题 → 显示Quick Fix菜单 → 选择修复方案 → 应用代码更改 → 重新分析
     ↓                  ↓                 ↓              ↓           ↓
  智能提示          多种修复选项        原子操作        自动保存     验证修复
```

## 3. 与MCP服务器通信机制

### 3.1 MCP客户端连接管理

```typescript
// src/mcp/client.ts
export class MCPClientManager {
  private client: Client;
  private connectionState: 'connecting' | 'connected' | 'disconnected';
  private requestQueue: RequestQueue;
  private responseCache: LRUCache<string, AnalysisResult>;

  constructor(private config: MCPConnectionConfig) {
    this.client = new Client({
      name: "yc-php-analysis-vscode",
      version: "1.0.0"
    });
    
    this.setupConnectionHandlers();
    this.setupRequestQueue();
  }

  async connect(): Promise<void> {
    try {
      const transport = new StdioServerTransport({
        command: this.config.serverPath,
        args: this.config.serverArgs
      });

      await this.client.connect(transport);
      this.connectionState = 'connected';
      
      // 处理排队的请求
      await this.processQueuedRequests();
      
    } catch (error) {
      this.handleConnectionError(error);
    }
  }

  async analyzeFile(filePath: string, content: string): Promise<AnalysisResult> {
    // 检查缓存
    const cacheKey = this.generateCacheKey(filePath, content);
    const cached = this.responseCache.get(cacheKey);
    if (cached && !this.isCacheExpired(cached)) {
      return cached;
    }

    // 发送分析请求
    const request: AnalysisRequest = {
      method: 'analyze_php_file',
      params: {
        file_path: filePath,
        content: content,
        analysis_types: this.getEnabledAnalysisTypes()
      }
    };

    const result = await this.sendRequest(request);
    
    // 缓存结果
    this.responseCache.set(cacheKey, result);
    
    return result;
  }

  private async sendRequest(request: AnalysisRequest): Promise<AnalysisResult> {
    if (this.connectionState !== 'connected') {
      // 排队等待连接
      return this.requestQueue.enqueue(request);
    }

    try {
      const response = await this.client.request(request);
      return this.parseResponse(response);
    } catch (error) {
      this.handleRequestError(error, request);
      throw error;
    }
  }
}
```

### 3.2 请求队列和缓存策略

```typescript
// src/mcp/request-queue.ts
export class RequestQueue {
  private queue: Array<QueuedRequest> = [];
  private processing = false;
  private maxQueueSize = 100;
  private batchSize = 10;

  async enqueue(request: AnalysisRequest): Promise<AnalysisResult> {
    if (this.queue.length >= this.maxQueueSize) {
      // 清除旧请求
      this.queue.splice(0, this.batchSize);
    }

    return new Promise((resolve, reject) => {
      this.queue.push({
        request,
        resolve,
        reject,
        timestamp: Date.now()
      });

      this.processQueue();
    });
  }

  private async processQueue(): Promise<void> {
    if (this.processing || this.queue.length === 0) return;

    this.processing = true;

    try {
      // 批量处理请求
      const batch = this.queue.splice(0, this.batchSize);
      const results = await this.processBatch(batch);
      
      batch.forEach((item, index) => {
        item.resolve(results[index]);
      });
    } catch (error) {
      // 处理批量错误
      this.handleBatchError(error);
    } finally {
      this.processing = false;
      
      // 继续处理剩余请求
      if (this.queue.length > 0) {
        setTimeout(() => this.processQueue(), 50);
      }
    }
  }
}
```

### 3.3 连接状态管理

```typescript
// src/mcp/connection-manager.ts
export class ConnectionManager {
  private reconnectAttempts = 0;
  private maxReconnectAttempts = 5;
  private reconnectDelay = 1000; // 指数退避
  
  async handleConnectionLoss(): Promise<void> {
    this.connectionState = 'disconnected';
    
    // 通知用户连接丢失
    vscode.window.showWarningMessage(
      'PHP分析服务连接丢失，正在重连...',
      '重试', '设置'
    ).then(selection => {
      if (selection === '重试') {
        this.reconnect();
      } else if (selection === '设置') {
        this.openSettings();
      }
    });

    // 自动重连
    await this.reconnectWithBackoff();
  }

  private async reconnectWithBackoff(): Promise<void> {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      vscode.window.showErrorMessage('无法连接到PHP分析服务，请检查配置');
      return;
    }

    await new Promise(resolve => 
      setTimeout(resolve, this.reconnectDelay * Math.pow(2, this.reconnectAttempts))
    );

    try {
      await this.connect();
      this.reconnectAttempts = 0;
      vscode.window.showInformationMessage('PHP分析服务已重连');
    } catch (error) {
      this.reconnectAttempts++;
      await this.reconnectWithBackoff();
    }
  }
}
```

## 4. 诊断系统和问题可视化

### 4.1 诊断管理器

```typescript
// src/diagnostics/manager.ts
export class DiagnosticManager {
  private diagnosticCollection: vscode.DiagnosticCollection;
  private issueDecorations: Map<string, vscode.TextEditorDecorationType[]>;
  private activeEditor: vscode.TextEditor | undefined;

  constructor() {
    this.diagnosticCollection = vscode.languages.createDiagnosticCollection('yc-php-analysis');
    this.issueDecorations = new Map();
    this.setupEventHandlers();
  }

  async updateDiagnostics(uri: vscode.Uri, issues: AnalysisIssue[]): Promise<void> {
    // 转换为VSCode诊断格式
    const diagnostics = issues.map(issue => this.convertToDiagnostic(issue));
    
    // 更新诊断集合
    this.diagnosticCollection.set(uri, diagnostics);
    
    // 更新代码装饰
    await this.updateDecorations(uri, issues);
    
    // 触发UI更新
    this.notifyUIUpdate(uri, issues);
  }

  private convertToDiagnostic(issue: AnalysisIssue): vscode.Diagnostic {
    const range = new vscode.Range(
      issue.line - 1, 
      issue.column - 1,
      issue.endLine - 1, 
      issue.endColumn - 1
    );

    const diagnostic = new vscode.Diagnostic(
      range,
      issue.message,
      this.mapSeverity(issue.severity)
    );

    // 添加元数据
    diagnostic.source = 'yc-php-analysis';
    diagnostic.code = issue.code;
    diagnostic.tags = this.mapTags(issue.type);
    
    // 关联数据用于Quick Fix
    diagnostic.relatedInformation = issue.suggestions?.map(suggestion => 
      new vscode.DiagnosticRelatedInformation(
        new vscode.Location(vscode.Uri.file(issue.file), range),
        suggestion.description
      )
    );

    return diagnostic;
  }

  private async updateDecorations(uri: vscode.Uri, issues: AnalysisIssue[]): Promise<void> {
    const editor = vscode.window.visibleTextEditors.find(e => e.document.uri.toString() === uri.toString());
    if (!editor) return;

    // 清除旧装饰
    const oldDecorations = this.issueDecorations.get(uri.toString()) || [];
    oldDecorations.forEach(decoration => decoration.dispose());

    // 按类型分组问题
    const issuesByType = this.groupIssuesByType(issues);
    const newDecorations: vscode.TextEditorDecorationType[] = [];

    // 为每种类型创建装饰
    for (const [type, typeIssues] of issuesByType) {
      const decoration = this.createDecorationType(type);
      const ranges = typeIssues.map(issue => new vscode.Range(
        issue.line - 1, issue.column - 1,
        issue.endLine - 1, issue.endColumn - 1
      ));

      editor.setDecorations(decoration, ranges);
      newDecorations.push(decoration);
    }

    this.issueDecorations.set(uri.toString(), newDecorations);
  }

  private createDecorationType(issueType: IssueType): vscode.TextEditorDecorationType {
    const config = this.getDecorationConfig(issueType);
    
    return vscode.window.createTextEditorDecorationType({
      backgroundColor: config.backgroundColor,
      border: config.border,
      borderRadius: '3px',
      overviewRulerColor: config.overviewRulerColor,
      overviewRulerLane: vscode.OverviewRulerLane.Right,
      
      // Hover提示
      light: {
        after: {
          contentText: config.indicator,
          color: config.indicatorColor
        }
      },
      dark: {
        after: {
          contentText: config.indicator,
          color: config.indicatorColor
        }
      }
    });
  }
}
```

### 4.2 问题分级和可视化

```typescript
// src/diagnostics/visualization.ts
export class IssueVisualization {
  private readonly severityColors = {
    error: '#f14c4c',
    warning: '#ff8c00',
    info: '#00bcd4',
    hint: '#4caf50'
  };

  private readonly typeIcons = {
    syntax: '$(bracket-error)',
    security: '$(shield)',
    performance: '$(dashboard)',
    style: '$(paintcan)'
  };

  getDecorationConfig(issueType: IssueType, severity: IssueSeverity): DecorationConfig {
    return {
      backgroundColor: `${this.severityColors[severity]}20`,
      border: `1px solid ${this.severityColors[severity]}`,
      overviewRulerColor: this.severityColors[severity],
      indicator: this.typeIcons[issueType],
      indicatorColor: this.severityColors[severity]
    };
  }

  createInlineHint(issue: AnalysisIssue): vscode.DecorationOptions {
    return {
      range: new vscode.Range(issue.line - 1, issue.column - 1, issue.endLine - 1, issue.endColumn - 1),
      hoverMessage: this.createHoverMessage(issue),
      renderOptions: {
        after: {
          contentText: ` ${this.typeIcons[issue.type]} ${issue.severity}`,
          color: this.severityColors[issue.severity],
          fontSize: '0.9em'
        }
      }
    };
  }

  private createHoverMessage(issue: AnalysisIssue): vscode.MarkdownString {
    const message = new vscode.MarkdownString();
    message.isTrusted = true;
    
    message.appendMarkdown(`### ${this.typeIcons[issue.type]} ${issue.title}\n\n`);
    message.appendMarkdown(`**严重程度**: ${issue.severity}\n\n`);
    message.appendMarkdown(`**描述**: ${issue.message}\n\n`);
    
    if (issue.suggestions && issue.suggestions.length > 0) {
      message.appendMarkdown(`**修复建议**:\n`);
      issue.suggestions.forEach((suggestion, index) => {
        message.appendMarkdown(`${index + 1}. ${suggestion.description}\n`);
      });
    }

    if (issue.documentation) {
      message.appendMarkdown(`\n[查看文档](${issue.documentation})`);
    }

    return message;
  }
}
```

## 5. 性能优化策略

### 5.1 异步处理和防抖

```typescript
// src/performance/async-processor.ts
export class AsyncProcessor {
  private debounceTimers: Map<string, NodeJS.Timeout> = new Map();
  private analysisQueue: Map<string, Promise<AnalysisResult>> = new Map();
  private readonly debounceDelay = 300; // 300ms防抖

  async processFile(uri: vscode.Uri, content: string): Promise<AnalysisResult> {
    const key = uri.toString();
    
    // 清除之前的定时器
    const existingTimer = this.debounceTimers.get(key);
    if (existingTimer) {
      clearTimeout(existingTimer);
    }

    // 检查是否已有正在进行的分析
    const existingAnalysis = this.analysisQueue.get(key);
    if (existingAnalysis) {
      return existingAnalysis;
    }

    // 创建防抖Promise
    const analysisPromise = new Promise<AnalysisResult>((resolve, reject) => {
      const timer = setTimeout(async () => {
        try {
          this.debounceTimers.delete(key);
          const result = await this.performAnalysis(uri, content);
          this.analysisQueue.delete(key);
          resolve(result);
        } catch (error) {
          this.analysisQueue.delete(key);
          reject(error);
        }
      }, this.debounceDelay);

      this.debounceTimers.set(key, timer);
    });

    this.analysisQueue.set(key, analysisPromise);
    return analysisPromise;
  }

  private async performAnalysis(uri: vscode.Uri, content: string): Promise<AnalysisResult> {
    // 检查文件大小限制
    if (content.length > 1024 * 1024) { // 1MB
      return this.handleLargeFile(uri, content);
    }

    // 正常分析流程
    return this.mcpClient.analyzeFile(uri.fsPath, content);
  }

  private async handleLargeFile(uri: vscode.Uri, content: string): Promise<AnalysisResult> {
    // 大文件分块处理
    const chunks = this.splitIntoChunks(content, 50 * 1024); // 50KB chunks
    const results: AnalysisResult[] = [];

    for (let i = 0; i < chunks.length; i++) {
      const chunk = chunks[i];
      const chunkResult = await this.mcpClient.analyzeFile(
        `${uri.fsPath}#chunk${i}`, 
        chunk
      );
      results.push(chunkResult);
    }

    return this.mergeResults(results);
  }
}
```

### 5.2 智能缓存策略

```typescript
// src/performance/cache-manager.ts
export class CacheManager {
  private fileCache: LRUCache<string, CachedAnalysis>;
  private dependencyGraph: Map<string, Set<string>>;
  private fileWatcher: vscode.FileSystemWatcher;

  constructor() {
    this.fileCache = new LRUCache<string, CachedAnalysis>({
      max: 1000, // 最多缓存1000个文件
      maxAge: 1000 * 60 * 30, // 30分钟过期
      updateAgeOnGet: true
    });

    this.dependencyGraph = new Map();
    this.setupFileWatcher();
  }

  async getCachedAnalysis(filePath: string, contentHash: string): Promise<CachedAnalysis | null> {
    const cached = this.fileCache.get(filePath);
    
    if (!cached) return null;
    
    // 检查内容是否变更
    if (cached.contentHash !== contentHash) {
      this.fileCache.del(filePath);
      return null;
    }

    // 检查依赖文件是否变更
    if (await this.hasDependencyChanged(filePath, cached.timestamp)) {
      this.invalidateDependents(filePath);
      return null;
    }

    // 更新访问时间
    cached.lastAccessed = Date.now();
    return cached;
  }

  setCachedAnalysis(filePath: string, analysis: AnalysisResult, contentHash: string): void {
    const cached: CachedAnalysis = {
      result: analysis,
      contentHash,
      timestamp: Date.now(),
      lastAccessed: Date.now(),
      dependencies: this.extractDependencies(analysis)
    };

    this.fileCache.set(filePath, cached);
    this.updateDependencyGraph(filePath, cached.dependencies);
  }

  private async hasDependencyChanged(filePath: string, cacheTimestamp: number): Promise<boolean> {
    const dependencies = this.dependencyGraph.get(filePath);
    if (!dependencies) return false;

    for (const depPath of dependencies) {
      try {
        const stat = await vscode.workspace.fs.stat(vscode.Uri.file(depPath));
        if (stat.mtime > cacheTimestamp) {
          return true;
        }
      } catch {
        // 依赖文件不存在，认为有变更
        return true;
      }
    }

    return false;
  }

  private invalidateDependents(changedFilePath: string): void {
    // 找到所有依赖于该文件的缓存项并使其失效
    for (const [filePath, dependencies] of this.dependencyGraph) {
      if (dependencies.has(changedFilePath)) {
        this.fileCache.del(filePath);
      }
    }
  }

  private setupFileWatcher(): void {
    this.fileWatcher = vscode.workspace.createFileSystemWatcher('**/*.php');
    
    this.fileWatcher.onDidChange((uri) => {
      this.fileCache.del(uri.fsPath);
      this.invalidateDependents(uri.fsPath);
    });

    this.fileWatcher.onDidDelete((uri) => {
      this.fileCache.del(uri.fsPath);
      this.dependencyGraph.delete(uri.fsPath);
      this.invalidateDependents(uri.fsPath);
    });
  }
}
```

### 5.3 内存管理和资源限制

```typescript
// src/performance/resource-manager.ts
export class ResourceManager {
  private readonly maxMemoryUsage = 200 * 1024 * 1024; // 200MB
  private readonly maxConcurrentAnalysis = 5;
  private currentMemoryUsage = 0;
  private activeAnalysisCount = 0;
  private analysisQueue: Array<() => Promise<void>> = [];

  async requestAnalysis<T>(analysisFunction: () => Promise<T>): Promise<T> {
    // 检查并发限制
    if (this.activeAnalysisCount >= this.maxConcurrentAnalysis) {
      await this.waitForSlot();
    }

    // 检查内存使用
    if (this.currentMemoryUsage > this.maxMemoryUsage) {
      await this.freeMemory();
    }

    this.activeAnalysisCount++;
    
    try {
      return await analysisFunction();
    } finally {
      this.activeAnalysisCount--;
      this.processQueue();
    }
  }

  private async waitForSlot(): Promise<void> {
    return new Promise(resolve => {
      this.analysisQueue.push(async () => resolve());
    });
  }

  private processQueue(): void {
    if (this.analysisQueue.length > 0 && this.activeAnalysisCount < this.maxConcurrentAnalysis) {
      const next = this.analysisQueue.shift();
      if (next) {
        next();
      }
    }
  }

  private async freeMemory(): Promise<void> {
    // 清理缓存
    this.cacheManager.clearOldEntries();
    
    // 强制垃圾回收（如果可用）
    if (global.gc) {
      global.gc();
    }

    // 等待内存释放
    await new Promise(resolve => setTimeout(resolve, 100));
    
    // 重新计算内存使用
    this.updateMemoryUsage();
  }

  monitorMemoryUsage(): void {
    setInterval(() => {
      this.updateMemoryUsage();
      
      if (this.currentMemoryUsage > this.maxMemoryUsage * 0.8) {
        vscode.window.showWarningMessage(
          'PHP分析扩展内存使用较高，正在优化...'
        );
        this.freeMemory();
      }
    }, 10000); // 每10秒检查一次
  }

  private updateMemoryUsage(): void {
    const usage = process.memoryUsage();
    this.currentMemoryUsage = usage.heapUsed;
  }
}
```

## 6. 扩展配置和用户自定义选项

### 6.1 配置模式定义

```typescript
// src/config/settings.ts
export interface ExtensionSettings {
  // MCP服务器配置
  server: {
    path: string;
    args: string[];
    timeout: number;
    maxRetries: number;
  };

  // 分析配置
  analysis: {
    enabledTypes: {
      syntax: boolean;
      security: boolean;
      performance: boolean;
      style: boolean;
    };
    
    // 严重程度过滤
    minimumSeverity: 'error' | 'warning' | 'info' | 'hint';
    
    // 文件过滤
    excludePatterns: string[];
    includePatterns: string[];
    
    // 实时分析
    analyzeOnSave: boolean;
    analyzeOnType: boolean;
    debounceDelay: number;
  };

  // UI配置
  ui: {
    showInlineHints: boolean;
    showProblemsPanel: boolean;
    showStatusBar: boolean;
    
    // 主题配置
    decorations: {
      errorColor: string;
      warningColor: string;
      infoColor: string;
      hintColor: string;
    };
    
    // 报告面板
    reportPanel: {
      showOverview: boolean;
      showStatistics: boolean;
      showTrends: boolean;
      autoRefresh: boolean;
      refreshInterval: number;
    };
  };

  // 性能配置
  performance: {
    maxFileSize: number;
    maxConcurrentAnalysis: number;
    cacheSize: number;
    cacheTTL: number;
    
    // 大项目优化
    enableIncrementalAnalysis: boolean;
    enableDependencyTracking: boolean;
  };

  // 快速修复配置
  quickFix: {
    enableAutoFix: boolean;
    confirmBeforeApply: boolean;
    enableBulkFix: boolean;
  };
}
```

### 6.2 配置管理器

```typescript
// src/config/manager.ts
export class SettingsManager {
  private settings: ExtensionSettings;
  private watchers: Map<string, Array<(value: any) => void>> = new Map();
  
  constructor(private context: vscode.ExtensionContext) {
    this.loadSettings();
    this.setupConfigWatcher();
  }

  loadSettings(): void {
    const config = vscode.workspace.getConfiguration('ycPhpAnalysis');
    
    this.settings = {
      server: {
        path: config.get('server.path', 'yc-php-analysis-server'),
        args: config.get('server.args', []),
        timeout: config.get('server.timeout', 30000),
        maxRetries: config.get('server.maxRetries', 3)
      },
      
      analysis: {
        enabledTypes: {
          syntax: config.get('analysis.syntax', true),
          security: config.get('analysis.security', true),
          performance: config.get('analysis.performance', true),
          style: config.get('analysis.style', false)
        },
        minimumSeverity: config.get('analysis.minimumSeverity', 'info'),
        excludePatterns: config.get('analysis.excludePatterns', ['**/vendor/**', '**/node_modules/**']),
        includePatterns: config.get('analysis.includePatterns', ['**/*.php']),
        analyzeOnSave: config.get('analysis.analyzeOnSave', true),
        analyzeOnType: config.get('analysis.analyzeOnType', false),
        debounceDelay: config.get('analysis.debounceDelay', 300)
      },

      ui: {
        showInlineHints: config.get('ui.showInlineHints', true),
        showProblemsPanel: config.get('ui.showProblemsPanel', true),
        showStatusBar: config.get('ui.showStatusBar', true),
        decorations: {
          errorColor: config.get('ui.decorations.errorColor', '#f14c4c'),
          warningColor: config.get('ui.decorations.warningColor', '#ff8c00'),
          infoColor: config.get('ui.decorations.infoColor', '#00bcd4'),
          hintColor: config.get('ui.decorations.hintColor', '#4caf50')
        },
        reportPanel: {
          showOverview: config.get('ui.reportPanel.showOverview', true),
          showStatistics: config.get('ui.reportPanel.showStatistics', true),
          showTrends: config.get('ui.reportPanel.showTrends', false),
          autoRefresh: config.get('ui.reportPanel.autoRefresh', true),
          refreshInterval: config.get('ui.reportPanel.refreshInterval', 30000)
        }
      },

      performance: {
        maxFileSize: config.get('performance.maxFileSize', 1024 * 1024),
        maxConcurrentAnalysis: config.get('performance.maxConcurrentAnalysis', 5),
        cacheSize: config.get('performance.cacheSize', 1000),
        cacheTTL: config.get('performance.cacheTTL', 1800000),
        enableIncrementalAnalysis: config.get('performance.enableIncrementalAnalysis', true),
        enableDependencyTracking: config.get('performance.enableDependencyTracking', true)
      },

      quickFix: {
        enableAutoFix: config.get('quickFix.enableAutoFix', false),
        confirmBeforeApply: config.get('quickFix.confirmBeforeApply', true),
        enableBulkFix: config.get('quickFix.enableBulkFix', true)
      }
    };
  }

  getSettings(): ExtensionSettings {
    return this.settings;
  }

  getSetting<T>(path: string): T | undefined {
    return this.getNestedValue(this.settings, path);
  }

  async updateSetting(path: string, value: any): Promise<void> {
    const config = vscode.workspace.getConfiguration('ycPhpAnalysis');
    await config.update(path, value, vscode.ConfigurationTarget.Global);
  }

  // 监听配置变化
  onSettingChanged<T>(path: string, callback: (value: T) => void): vscode.Disposable {
    const watchers = this.watchers.get(path) || [];
    watchers.push(callback);
    this.watchers.set(path, watchers);

    return new vscode.Disposable(() => {
      const updatedWatchers = this.watchers.get(path) || [];
      const index = updatedWatchers.indexOf(callback);
      if (index !== -1) {
        updatedWatchers.splice(index, 1);
      }
    });
  }

  private setupConfigWatcher(): void {
    vscode.workspace.onDidChangeConfiguration(event => {
      if (event.affectsConfiguration('ycPhpAnalysis')) {
        const oldSettings = { ...this.settings };
        this.loadSettings();
        
        // 通知配置变化
        this.notifySettingChanges(oldSettings, this.settings);
      }
    });
  }

  private notifySettingChanges(oldSettings: ExtensionSettings, newSettings: ExtensionSettings): void {
    // 递归比较设置变化
    this.compareAndNotify('', oldSettings, newSettings);
  }

  private compareAndNotify(basePath: string, oldValue: any, newValue: any): void {
    if (typeof oldValue === 'object' && typeof newValue === 'object') {
      for (const key in newValue) {
        const path = basePath ? `${basePath}.${key}` : key;
        this.compareAndNotify(path, oldValue[key], newValue[key]);
      }
    } else if (oldValue !== newValue) {
      const watchers = this.watchers.get(basePath) || [];
      watchers.forEach(callback => callback(newValue));
    }
  }

  private getNestedValue(obj: any, path: string): any {
    return path.split('.').reduce((current, key) => current && current[key], obj);
  }
}
```

### 6.3 配置UI界面

```typescript
// src/config/webview-provider.ts
export class ConfigWebviewProvider implements vscode.WebviewViewProvider {
  private _view?: vscode.WebviewView;
  private _doc?: vscode.TextDocument;

  constructor(
    private readonly _extensionUri: vscode.Uri,
    private settingsManager: SettingsManager
  ) {}

  resolveWebviewView(webviewView: vscode.WebviewView): void {
    this._view = webviewView;

    webviewView.webview.options = {
      enableScripts: true,
      localResourceRoots: [this._extensionUri]
    };

    webviewView.webview.html = this.getHtmlForWebview(webviewView.webview);
    
    // 处理来自webview的消息
    webviewView.webview.onDidReceiveMessage(data => {
      switch (data.type) {
        case 'updateSetting':
          this.settingsManager.updateSetting(data.path, data.value);
          break;
        case 'resetToDefaults':
          this.resetToDefaults();
          break;
        case 'exportConfig':
          this.exportConfig();
          break;
        case 'importConfig':
          this.importConfig();
          break;
      }
    });

    // 监听设置变化并更新UI
    this.setupSettingWatchers();
  }

  private getHtmlForWebview(webview: vscode.Webview): string {
    const scriptUri = webview.asWebviewUri(
      vscode.Uri.joinPath(this._extensionUri, 'resources', 'config-panel.js')
    );
    const styleUri = webview.asWebviewUri(
      vscode.Uri.joinPath(this._extensionUri, 'resources', 'config-panel.css')
    );

    const settings = this.settingsManager.getSettings();

    return `<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="${styleUri}" rel="stylesheet">
    <title>PHP分析配置</title>
</head>
<body>
    <div class="config-container">
        <!-- 服务器配置 -->
        <section class="config-section">
            <h2>🔧 MCP服务器配置</h2>
            <div class="config-group">
                <label for="serverPath">服务器路径:</label>
                <input type="text" id="serverPath" value="${settings.server.path}">
            </div>
            <div class="config-group">
                <label for="serverTimeout">超时时间 (ms):</label>
                <input type="number" id="serverTimeout" value="${settings.server.timeout}">
            </div>
            <div class="config-group">
                <label for="maxRetries">最大重试次数:</label>
                <input type="number" id="maxRetries" value="${settings.server.maxRetries}">
            </div>
        </section>

        <!-- 分析配置 -->
        <section class="config-section">
            <h2>🔍 分析配置</h2>
            <div class="config-group checkbox-group">
                <h3>启用的分析类型:</h3>
                <label><input type="checkbox" id="syntax" ${settings.analysis.enabledTypes.syntax ? 'checked' : ''}> 语法分析</label>
                <label><input type="checkbox" id="security" ${settings.analysis.enabledTypes.security ? 'checked' : ''}> 安全分析</label>
                <label><input type="checkbox" id="performance" ${settings.analysis.enabledTypes.performance ? 'checked' : ''}> 性能分析</label>
                <label><input type="checkbox" id="style" ${settings.analysis.enabledTypes.style ? 'checked' : ''}> 代码风格</label>
            </div>
            <div class="config-group">
                <label for="minimumSeverity">最小严重程度:</label>
                <select id="minimumSeverity">
                    <option value="error" ${settings.analysis.minimumSeverity === 'error' ? 'selected' : ''}>错误</option>
                    <option value="warning" ${settings.analysis.minimumSeverity === 'warning' ? 'selected' : ''}>警告</option>
                    <option value="info" ${settings.analysis.minimumSeverity === 'info' ? 'selected' : ''}>信息</option>
                    <option value="hint" ${settings.analysis.minimumSeverity === 'hint' ? 'selected' : ''}>提示</option>
                </select>
            </div>
            <div class="config-group">
                <label><input type="checkbox" id="analyzeOnSave" ${settings.analysis.analyzeOnSave ? 'checked' : ''}> 保存时分析</label>
                <label><input type="checkbox" id="analyzeOnType" ${settings.analysis.analyzeOnType ? 'checked' : ''}> 输入时分析</label>
            </div>
        </section>

        <!-- UI配置 -->
        <section class="config-section">
            <h2>🎨 界面配置</h2>
            <div class="config-group">
                <label><input type="checkbox" id="showInlineHints" ${settings.ui.showInlineHints ? 'checked' : ''}> 显示内联提示</label>
                <label><input type="checkbox" id="showProblemsPanel" ${settings.ui.showProblemsPanel ? 'checked' : ''}> 显示问题面板</label>
                <label><input type="checkbox" id="showStatusBar" ${settings.ui.showStatusBar ? 'checked' : ''}> 显示状态栏</label>
            </div>
            <div class="config-group color-group">
                <h3>颜色配置:</h3>
                <label>错误: <input type="color" id="errorColor" value="${settings.ui.decorations.errorColor}"></label>
                <label>警告: <input type="color" id="warningColor" value="${settings.ui.decorations.warningColor}"></label>
                <label>信息: <input type="color" id="infoColor" value="${settings.ui.decorations.infoColor}"></label>
                <label>提示: <input type="color" id="hintColor" value="${settings.ui.decorations.hintColor}"></label>
            </div>
        </section>

        <!-- 性能配置 -->
        <section class="config-section">
            <h2>⚡ 性能配置</h2>
            <div class="config-group">
                <label for="maxFileSize">最大文件大小 (bytes):</label>
                <input type="number" id="maxFileSize" value="${settings.performance.maxFileSize}">
            </div>
            <div class="config-group">
                <label for="maxConcurrentAnalysis">最大并发分析数:</label>
                <input type="number" id="maxConcurrentAnalysis" value="${settings.performance.maxConcurrentAnalysis}">
            </div>
            <div class="config-group">
                <label for="cacheSize">缓存大小:</label>
                <input type="number" id="cacheSize" value="${settings.performance.cacheSize}">
            </div>
        </section>

        <!-- 操作按钮 -->
        <section class="config-actions">
            <button id="resetDefaults">重置为默认</button>
            <button id="exportConfig">导出配置</button>
            <button id="importConfig">导入配置</button>
            <button id="testConnection">测试连接</button>
        </section>
    </div>

    <script src="${scriptUri}"></script>
    <script>
        // 初始化设置数据
        window.currentSettings = ${JSON.stringify(settings)};
    </script>
</body>
</html>`;
  }

  private setupSettingWatchers(): void {
    // 监听所有设置变化并更新webview
    this.settingsManager.onSettingChanged('', (newSettings) => {
      this._view?.webview.postMessage({
        type: 'settingsUpdated',
        settings: newSettings
      });
    });
  }

  private async resetToDefaults(): Promise<void> {
    const config = vscode.workspace.getConfiguration('ycPhpAnalysis');
    
    // 重置所有设置到默认值
    await config.update('server', undefined, vscode.ConfigurationTarget.Global);
    await config.update('analysis', undefined, vscode.ConfigurationTarget.Global);
    await config.update('ui', undefined, vscode.ConfigurationTarget.Global);
    await config.update('performance', undefined, vscode.ConfigurationTarget.Global);
    await config.update('quickFix', undefined, vscode.ConfigurationTarget.Global);

    vscode.window.showInformationMessage('配置已重置为默认值');
  }

  private async exportConfig(): Promise<void> {
    const settings = this.settingsManager.getSettings();
    const configJson = JSON.stringify(settings, null, 2);

    const uri = await vscode.window.showSaveDialog({
      defaultUri: vscode.Uri.file('yc-php-analysis-config.json'),
      filters: {
        'JSON': ['json']
      }
    });

    if (uri) {
      await vscode.workspace.fs.writeFile(uri, Buffer.from(configJson));
      vscode.window.showInformationMessage('配置已导出');
    }
  }

  private async importConfig(): Promise<void> {
    const uris = await vscode.window.showOpenDialog({
      canSelectFiles: true,
      canSelectFolders: false,
      canSelectMany: false,
      filters: {
        'JSON': ['json']
      }
    });

    if (uris && uris[0]) {
      try {
        const content = await vscode.workspace.fs.readFile(uris[0]);
        const settings = JSON.parse(content.toString());
        
        // 应用导入的设置
        await this.applyImportedSettings(settings);
        
        vscode.window.showInformationMessage('配置已成功导入');
      } catch (error) {
        vscode.window.showErrorMessage(`导入配置失败: ${error}`);
      }
    }
  }

  private async applyImportedSettings(settings: any): Promise<void> {
    const config = vscode.workspace.getConfiguration('ycPhpAnalysis');
    
    for (const [section, values] of Object.entries(settings)) {
      if (typeof values === 'object') {
        for (const [key, value] of Object.entries(values as object)) {
          await config.update(`${section}.${key}`, value, vscode.ConfigurationTarget.Global);
        }
      } else {
        await config.update(section, values, vscode.ConfigurationTarget.Global);
      }
    }
  }
}
```

## 7. 项目文件结构

```
yc-php-analysis-vscode/
├── package.json
├── README.md
├── CHANGELOG.md
├── src/
│   ├── extension.ts                    # 扩展主入口
│   ├── mcp/
│   │   ├── client.ts                   # MCP客户端管理
│   │   ├── connection-manager.ts       # 连接状态管理
│   │   └── request-queue.ts           # 请求队列
│   ├── diagnostics/
│   │   ├── manager.ts                  # 诊断管理器
│   │   ├── visualization.ts            # 问题可视化
│   │   └── types.ts                   # 诊断类型定义
│   ├── quick-actions/
│   │   ├── provider.ts                 # 快速修复提供者
│   │   ├── actions.ts                 # 具体修复动作
│   │   └── bulk-fix.ts                # 批量修复
│   ├── webview/
│   │   ├── report-provider.ts          # 报告面板提供者
│   │   ├── config-provider.ts          # 配置面板提供者
│   │   └── panel-manager.ts           # 面板管理器
│   ├── performance/
│   │   ├── async-processor.ts          # 异步处理器
│   │   ├── cache-manager.ts           # 缓存管理器
│   │   └── resource-manager.ts        # 资源管理器
│   ├── config/
│   │   ├── manager.ts                  # 配置管理器
│   │   ├── settings.ts                # 设置类型定义
│   │   └── webview-provider.ts        # 配置界面提供者
│   └── utils/
│       ├── logger.ts                   # 日志工具
│       ├── file-utils.ts              # 文件工具
│       └── constants.ts               # 常量定义
├── resources/
│   ├── config-panel.html              # 配置面板HTML
│   ├── config-panel.css               # 配置面板样式
│   ├── config-panel.js                # 配置面板脚本
│   ├── report-panel.html              # 报告面板HTML
│   ├── report-panel.css               # 报告面板样式
│   ├── report-panel.js                # 报告面板脚本
│   └── icons/                         # 图标资源
│       ├── error.svg
│       ├── warning.svg
│       ├── info.svg
│       └── hint.svg
├── syntaxes/                          # 语法高亮定义
│   └── php-analysis.tmLanguage.json
├── schemas/                           # JSON schema
│   └── settings.schema.json
└── test/                              # 测试文件
    ├── suite/
    ├── integration/
    └── fixtures/
```

这个架构设计提供了：

1. **专业级VSCode扩展架构** - 模块化设计，易于维护和扩展
2. **优秀的用户体验** - 响应快速，界面直观，功能丰富
3. **高效的MCP通信** - 异步处理，智能缓存，连接管理
4. **智能诊断系统** - 实时分析，问题分级，可视化显示
5. **全面性能优化** - 内存管理，并发控制，资源限制
6. **灵活配置系统** - 图形化配置界面，配置导入导出，实时更新

该架构能够很好地支持PHP代码分析需求，提供专业级的开发工具体验。