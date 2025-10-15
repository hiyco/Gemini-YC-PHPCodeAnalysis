/*
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: MCP Protocol Implementation for PHP Code Analysis
 */

import { EventEmitter } from 'events';
import WebSocket from 'ws';
import { createHash } from 'crypto';

// MCP Protocol Types
export interface MCPMessage {
  jsonrpc: '2.0';
  id?: string | number;
  method?: string;
  params?: any;
  result?: any;
  error?: MCPError;
}

export interface MCPError {
  code: number;
  message: string;
  data?: any;
}

export interface MCPCapabilities {
  tools?: {
    listChanged?: boolean;
    supportsPartialResults?: boolean;
  };
  resources?: {
    subscribe?: boolean;
    listChanged?: boolean;
  };
  prompts?: {
    listChanged?: boolean;
  };
  sampling?: {};
}

export interface MCPServerInfo {
  name: string;
  version: string;
  protocolVersion: string;
  capabilities: MCPCapabilities;
}

export interface PHPAnalysisContext {
  projectRoot: string;
  phpVersion: string;
  frameworks: string[];
  dependencies: Record<string, string>;
  excludePaths: string[];
  customRules?: string[];
}

export interface CodeAnalysisRequest {
  type: 'analyze' | 'complete' | 'refactor' | 'security' | 'performance';
  filePath: string;
  content?: string;
  position?: { line: number; character: number };
  context: PHPAnalysisContext;
  options?: Record<string, any>;
}

export interface AnalysisResult {
  diagnostics?: Diagnostic[];
  completions?: CompletionItem[];
  suggestions?: RefactoringSuggestion[];
  security?: SecurityIssue[];
  performance?: PerformanceIssue[];
  metrics?: CodeMetrics;
}

export interface Diagnostic {
  range: Range;
  severity: 'error' | 'warning' | 'info' | 'hint';
  code?: string;
  source: string;
  message: string;
  relatedInformation?: DiagnosticRelatedInformation[];
}

export interface CompletionItem {
  label: string;
  kind: CompletionItemKind;
  detail?: string;
  documentation?: string;
  insertText?: string;
  sortText?: string;
  filterText?: string;
  additionalTextEdits?: TextEdit[];
}

export interface RefactoringSuggestion {
  title: string;
  kind: 'quickfix' | 'refactor' | 'source';
  isPreferred?: boolean;
  edit: WorkspaceEdit;
  command?: Command;
}

export interface SecurityIssue {
  id: string;
  severity: 'critical' | 'high' | 'medium' | 'low';
  category: string;
  title: string;
  description: string;
  location: Range;
  fix?: RefactoringSuggestion;
  references?: string[];
}

export interface PerformanceIssue {
  id: string;
  type: 'n-plus-one' | 'inefficient-query' | 'memory-leak' | 'slow-algorithm';
  severity: 'high' | 'medium' | 'low';
  description: string;
  location: Range;
  impact: {
    timeComplexity?: string;
    spaceComplexity?: string;
    estimatedImpact?: string;
  };
  suggestions: string[];
}

export interface CodeMetrics {
  complexity: {
    cyclomatic: number;
    cognitive: number;
    maintainabilityIndex: number;
  };
  size: {
    linesOfCode: number;
    physicalLines: number;
    logicalLines: number;
  };
  quality: {
    duplicatedLines: number;
    technicalDebt: number;
    testCoverage?: number;
  };
}

// MCP Server Implementation
export class MCPServer extends EventEmitter {
  private wss: WebSocket.Server;
  private clients: Map<string, WebSocket> = new Map();
  private serverInfo: MCPServerInfo;
  private isShuttingDown = false;

  constructor(port: number, serverInfo: MCPServerInfo) {
    super();
    this.serverInfo = serverInfo;
    
    this.wss = new WebSocket.Server({
      port,
      perMessageDeflate: {
        zlibDeflateOptions: {
          level: 3,
          threshold: 1024,
        },
      },
    });

    this.setupServer();
  }

  private setupServer(): void {
    this.wss.on('connection', this.handleConnection.bind(this));
    
    process.on('SIGTERM', this.gracefulShutdown.bind(this));
    process.on('SIGINT', this.gracefulShutdown.bind(this));
  }

  private handleConnection(ws: WebSocket, request: any): void {
    const clientId = this.generateClientId(request);
    this.clients.set(clientId, ws);

    console.log(`MCP Client connected: ${clientId}`);

    ws.on('message', (data: Buffer) => {
      try {
        const message: MCPMessage = JSON.parse(data.toString());
        this.handleMessage(clientId, message);
      } catch (error) {
        this.sendError(clientId, -32700, 'Parse error', error);
      }
    });

    ws.on('close', () => {
      this.clients.delete(clientId);
      console.log(`MCP Client disconnected: ${clientId}`);
    });

    ws.on('error', (error) => {
      console.error(`WebSocket error for client ${clientId}:`, error);
      this.clients.delete(clientId);
    });

    // Send initial capabilities
    this.sendMessage(clientId, {
      jsonrpc: '2.0',
      method: 'initialize',
      params: this.serverInfo,
    });
  }

  private async handleMessage(clientId: string, message: MCPMessage): Promise<void> {
    try {
      switch (message.method) {
        case 'initialize':
          await this.handleInitialize(clientId, message);
          break;
        case 'tools/list':
          await this.handleToolsList(clientId, message);
          break;
        case 'tools/call':
          await this.handleToolsCall(clientId, message);
          break;
        case 'resources/list':
          await this.handleResourcesList(clientId, message);
          break;
        case 'resources/read':
          await this.handleResourcesRead(clientId, message);
          break;
        case 'prompts/list':
          await this.handlePromptsList(clientId, message);
          break;
        case 'prompts/get':
          await this.handlePromptsGet(clientId, message);
          break;
        case 'php/analyze':
          await this.handlePHPAnalysis(clientId, message);
          break;
        case 'php/complete':
          await this.handlePHPCompletion(clientId, message);
          break;
        case 'php/refactor':
          await this.handlePHPRefactoring(clientId, message);
          break;
        default:
          this.sendError(clientId, -32601, 'Method not found', {
            method: message.method,
          });
      }
    } catch (error) {
      this.sendError(clientId, -32603, 'Internal error', error);
    }
  }

  private async handleInitialize(clientId: string, message: MCPMessage): Promise<void> {
    this.sendResult(clientId, message.id, {
      protocolVersion: '2024-11-05',
      capabilities: this.serverInfo.capabilities,
      serverInfo: {
        name: this.serverInfo.name,
        version: this.serverInfo.version,
      },
    });
  }

  private async handleToolsList(clientId: string, message: MCPMessage): Promise<void> {
    const tools = [
      {
        name: 'php_analyze',
        description: 'Analyze PHP code for issues, patterns, and improvements',
        inputSchema: {
          type: 'object',
          properties: {
            filePath: { type: 'string', description: 'Path to PHP file' },
            content: { type: 'string', description: 'PHP code content' },
            analysisType: { 
              type: 'string', 
              enum: ['full', 'syntax', 'security', 'performance', 'quality'],
              description: 'Type of analysis to perform'
            },
            context: {
              type: 'object',
              properties: {
                projectRoot: { type: 'string' },
                phpVersion: { type: 'string' },
                frameworks: { type: 'array', items: { type: 'string' } }
              }
            }
          },
          required: ['filePath']
        }
      },
      {
        name: 'php_complete',
        description: 'Provide PHP code completion suggestions',
        inputSchema: {
          type: 'object',
          properties: {
            filePath: { type: 'string' },
            content: { type: 'string' },
            position: {
              type: 'object',
              properties: {
                line: { type: 'number' },
                character: { type: 'number' }
              }
            },
            context: { type: 'object' }
          },
          required: ['filePath', 'content', 'position']
        }
      },
      {
        name: 'php_refactor',
        description: 'Generate PHP refactoring suggestions',
        inputSchema: {
          type: 'object',
          properties: {
            filePath: { type: 'string' },
            content: { type: 'string' },
            refactorType: {
              type: 'string',
              enum: ['extract_method', 'extract_class', 'inline', 'rename', 'move'],
              description: 'Type of refactoring'
            },
            selection: {
              type: 'object',
              properties: {
                start: { type: 'object', properties: { line: { type: 'number' }, character: { type: 'number' } } },
                end: { type: 'object', properties: { line: { type: 'number' }, character: { type: 'number' } } }
              }
            }
          },
          required: ['filePath', 'content', 'refactorType']
        }
      }
    ];

    this.sendResult(clientId, message.id, { tools });
  }

  private async handleToolsCall(clientId: string, message: MCPMessage): Promise<void> {
    const { name, arguments: args } = message.params;
    
    switch (name) {
      case 'php_analyze':
        const analysisResult = await this.performPHPAnalysis(args);
        this.sendResult(clientId, message.id, {
          content: [{
            type: 'text',
            text: JSON.stringify(analysisResult, null, 2)
          }]
        });
        break;
        
      case 'php_complete':
        const completionResult = await this.performPHPCompletion(args);
        this.sendResult(clientId, message.id, {
          content: [{
            type: 'text',
            text: JSON.stringify(completionResult, null, 2)
          }]
        });
        break;
        
      case 'php_refactor':
        const refactorResult = await this.performPHPRefactoring(args);
        this.sendResult(clientId, message.id, {
          content: [{
            type: 'text',
            text: JSON.stringify(refactorResult, null, 2)
          }]
        });
        break;
        
      default:
        this.sendError(clientId, -32601, 'Unknown tool', { tool: name });
    }
  }

  private async handleResourcesList(clientId: string, message: MCPMessage): Promise<void> {
    // Implementation for resources list
    this.sendResult(clientId, message.id, { resources: [] });
  }

  private async handleResourcesRead(clientId: string, message: MCPMessage): Promise<void> {
    // Implementation for resources read
    this.sendResult(clientId, message.id, { contents: [] });
  }

  private async handlePromptsList(clientId: string, message: MCPMessage): Promise<void> {
    // Implementation for prompts list
    this.sendResult(clientId, message.id, { prompts: [] });
  }

  private async handlePromptsGet(clientId: string, message: MCPMessage): Promise<void> {
    // Implementation for prompts get
    this.sendResult(clientId, message.id, { messages: [] });
  }

  private async performPHPAnalysis(args: any): Promise<AnalysisResult> {
    // This will be implemented by the PHP Analysis Engine
    // For now, return a mock result
    return {
      diagnostics: [],
      security: [],
      performance: [],
      metrics: {
        complexity: { cyclomatic: 1, cognitive: 1, maintainabilityIndex: 100 },
        size: { linesOfCode: 0, physicalLines: 0, logicalLines: 0 },
        quality: { duplicatedLines: 0, technicalDebt: 0 }
      }
    };
  }

  private async performPHPCompletion(args: any): Promise<CompletionItem[]> {
    // This will be implemented by the PHP Analysis Engine
    return [];
  }

  private async performPHPRefactoring(args: any): Promise<RefactoringSuggestion[]> {
    // This will be implemented by the PHP Analysis Engine
    return [];
  }

  private sendMessage(clientId: string, message: MCPMessage): void {
    const ws = this.clients.get(clientId);
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify(message));
    }
  }

  private sendResult(clientId: string, id: any, result: any): void {
    this.sendMessage(clientId, {
      jsonrpc: '2.0',
      id,
      result,
    });
  }

  private sendError(clientId: string, code: number, message: string, data?: any): void {
    this.sendMessage(clientId, {
      jsonrpc: '2.0',
      id: null,
      error: { code, message, data },
    });
  }

  private generateClientId(request: any): string {
    const timestamp = Date.now().toString();
    const random = Math.random().toString(36).substring(2);
    return createHash('md5').update(`${timestamp}-${random}`).digest('hex').substring(0, 8);
  }

  private async gracefulShutdown(): Promise<void> {
    if (this.isShuttingDown) return;
    
    this.isShuttingDown = true;
    console.log('Gracefully shutting down MCP server...');

    // Close all client connections
    for (const [clientId, ws] of this.clients.entries()) {
      ws.close(1001, 'Server shutting down');
    }

    // Close WebSocket server
    this.wss.close(() => {
      console.log('MCP server shut down complete');
      process.exit(0);
    });
  }

  public start(): void {
    console.log(`MCP Server started on port ${this.wss.options.port}`);
    console.log(`Server: ${this.serverInfo.name} v${this.serverInfo.version}`);
    console.log(`Protocol version: ${this.serverInfo.protocolVersion}`);
  }
}

// Additional utility types
export interface Range {
  start: { line: number; character: number };
  end: { line: number; character: number };
}

export interface TextEdit {
  range: Range;
  newText: string;
}

export interface WorkspaceEdit {
  changes?: { [uri: string]: TextEdit[] };
}

export interface Command {
  title: string;
  command: string;
  arguments?: any[];
}

export interface DiagnosticRelatedInformation {
  location: { uri: string; range: Range };
  message: string;
}

export enum CompletionItemKind {
  Text = 1,
  Method = 2,
  Function = 3,
  Constructor = 4,
  Field = 5,
  Variable = 6,
  Class = 7,
  Interface = 8,
  Module = 9,
  Property = 10,
  Unit = 11,
  Value = 12,
  Enum = 13,
  Keyword = 14,
  Snippet = 15,
  Color = 16,
  File = 17,
  Reference = 18,
  Folder = 19,
  EnumMember = 20,
  Constant = 21,
  Struct = 22,
  Event = 23,
  Operator = 24,
  TypeParameter = 25,
}