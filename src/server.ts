/*
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Main MCP Server Entry Point for PHP Code Analysis
 */

import { MCPServer } from './protocol/mcp-protocol';
import { ContextManager } from './context/context-manager';
import { PluginManager } from './plugins/plugin-system';
import { PerformanceOptimizer, PerformanceConfig } from './performance/performance-optimizer';
import { SecurityManager, SecurityConfig } from './security/security-manager';
import * as fs from 'fs/promises';
import * as path from 'path';
import * as os from 'os';

export interface ServerConfig {
  server: {
    port: number;
    host: string;
    name: string;
    version: string;
    maxConnections: number;
    timeout: number;
  };
  php: {
    version: string;
    extensions: string[];
    frameworks: string[];
  };
  analysis: {
    enableSyntax: boolean;
    enableSemantics: boolean;
    enableSecurity: boolean;
    enablePerformance: boolean;
    enableQuality: boolean;
  };
  context: {
    cacheSize: number;
    cacheTTL: number;
    maxProjects: number;
    enableWatching: boolean;
  };
  plugins: {
    enabled: boolean;
    directory: string;
    autoLoad: boolean;
    sandboxed: boolean;
  };
  performance: PerformanceConfig;
  security: SecurityConfig;
  logging: {
    level: 'debug' | 'info' | 'warn' | 'error';
    file?: string;
    console: boolean;
    structured: boolean;
  };
}

export class PHPAnalysisMCPServer {
  private config: ServerConfig;
  private mcpServer: MCPServer;
  private contextManager: ContextManager;
  private pluginManager: PluginManager;
  private performanceOptimizer: PerformanceOptimizer;
  private securityManager: SecurityManager;
  private isRunning = false;

  constructor(configPath?: string) {
    console.log('🚀 Initializing PHP Analysis MCP Server...');
    
    // Load configuration
    this.config = this.loadConfiguration(configPath);
    
    // Initialize core components
    this.contextManager = new ContextManager();
    this.securityManager = new SecurityManager(this.config.security);
    this.performanceOptimizer = new PerformanceOptimizer(this.config.performance);
    this.pluginManager = new PluginManager(this.contextManager);
    
    // Initialize MCP server
    this.mcpServer = new MCPServer(this.config.server.port, {
      name: this.config.server.name,
      version: this.config.server.version,
      protocolVersion: '2024-11-05',
      capabilities: {
        tools: {
          listChanged: true,
          supportsPartialResults: true,
        },
        resources: {
          subscribe: true,
          listChanged: true,
        },
        prompts: {
          listChanged: true,
        },
        sampling: {},
      },
    });

    this.setupEventHandlers();
  }

  /**
   * Start the MCP server
   */
  public async start(): Promise<void> {
    if (this.isRunning) {
      console.warn('Server is already running');
      return;
    }

    try {
      console.log(`🏗️  Starting ${this.config.server.name} v${this.config.server.version}`);

      // Initialize security
      console.log('🔐 Initializing security manager...');
      await this.initializeSecurity();

      // Initialize context manager
      console.log('🧠 Initializing context manager...');
      await this.initializeContextManager();

      // Load plugins
      if (this.config.plugins.enabled) {
        console.log('🔌 Loading plugins...');
        await this.loadPlugins();
      }

      // Start performance monitoring
      console.log('⚡ Starting performance monitoring...');
      await this.startPerformanceMonitoring();

      // Start MCP server
      console.log(`🌐 Starting MCP server on ${this.config.server.host}:${this.config.server.port}`);
      this.mcpServer.start();

      this.isRunning = true;

      console.log('✅ PHP Analysis MCP Server started successfully!');
      console.log(`📊 Server Info:`);
      console.log(`   - Name: ${this.config.server.name}`);
      console.log(`   - Version: ${this.config.server.version}`);
      console.log(`   - Port: ${this.config.server.port}`);
      console.log(`   - PHP Version: ${this.config.php.version}`);
      console.log(`   - Plugins: ${this.config.plugins.enabled ? 'Enabled' : 'Disabled'}`);
      console.log(`   - Security: ${this.config.security.authentication.jwtSecret ? 'Enabled' : 'Basic'}`);
      console.log(`   - Performance Optimization: Enabled`);

      // Setup graceful shutdown
      this.setupGracefulShutdown();

    } catch (error) {
      console.error('❌ Failed to start server:', error);
      process.exit(1);
    }
  }

  /**
   * Stop the MCP server
   */
  public async stop(): Promise<void> {
    if (!this.isRunning) {
      return;
    }

    console.log('🛑 Stopping PHP Analysis MCP Server...');

    try {
      // Stop accepting new connections
      this.isRunning = false;

      // Deactivate all plugins
      if (this.config.plugins.enabled) {
        console.log('🔌 Deactivating plugins...');
        const plugins = this.pluginManager.getAllPlugins();
        for (const [name] of plugins) {
          try {
            await this.pluginManager.deactivatePlugin(name);
          } catch (error) {
            console.error(`Error deactivating plugin ${name}:`, error);
          }
        }
      }

      // Stop performance monitoring
      console.log('⚡ Stopping performance monitoring...');
      // Performance optimizer cleanup would go here

      // Stop context manager
      console.log('🧠 Stopping context manager...');
      // Context manager cleanup would go here

      console.log('✅ Server stopped gracefully');

    } catch (error) {
      console.error('❌ Error during server shutdown:', error);
    }
  }

  /**
   * Get server status
   */
  public getStatus(): ServerStatus {
    const memUsage = process.memoryUsage();
    const uptime = process.uptime();

    return {
      running: this.isRunning,
      version: this.config.server.version,
      uptime,
      memory: {
        used: memUsage.heapUsed,
        total: memUsage.heapTotal,
        external: memUsage.external,
      },
      performance: this.performanceOptimizer.getMetrics(),
      security: this.securityManager.getSecurityMetrics(),
      plugins: {
        total: this.pluginManager.getAllPlugins().size,
        active: Array.from(this.pluginManager.getAllPlugins.values())
          .filter(p => p.status === 'active').length,
      },
      context: this.contextManager.getMemoryUsage(),
    };
  }

  private loadConfiguration(configPath?: string): ServerConfig {
    const defaultConfig: ServerConfig = {
      server: {
        port: parseInt(process.env.MCP_PORT || '3000'),
        host: process.env.MCP_HOST || '0.0.0.0',
        name: 'PHP Analysis MCP Server',
        version: '1.0.0',
        maxConnections: 100,
        timeout: 300000, // 5 minutes
      },
      php: {
        version: '8.2',
        extensions: ['ast', 'xdebug', 'opcache'],
        frameworks: ['laravel', 'symfony', 'codeigniter'],
      },
      analysis: {
        enableSyntax: true,
        enableSemantics: true,
        enableSecurity: true,
        enablePerformance: true,
        enableQuality: true,
      },
      context: {
        cacheSize: 512 * 1024 * 1024, // 512MB
        cacheTTL: 30 * 60 * 1000, // 30 minutes
        maxProjects: 50,
        enableWatching: true,
      },
      plugins: {
        enabled: true,
        directory: path.join(__dirname, '..', 'plugins'),
        autoLoad: true,
        sandboxed: true,
      },
      performance: {
        maxMemoryUsage: 1024, // MB
        maxCpuUsage: 80, // percentage
        maxResponseTime: 500, // milliseconds
        cacheSettings: {
          maxSize: 256, // MB
          ttl: 30 * 60 * 1000, // 30 minutes
          compression: true,
          persistence: false,
          strategies: {
            ast: {
              enabled: true,
              maxSize: 128, // MB
              ttl: 60 * 60 * 1000, // 1 hour
              evictionPolicy: 'LRU',
              compression: true,
            },
            analysis: {
              enabled: true,
              maxSize: 64, // MB
              ttl: 30 * 60 * 1000, // 30 minutes
              evictionPolicy: 'LRU',
              compression: true,
            },
            completions: {
              enabled: true,
              maxSize: 32, // MB
              ttl: 10 * 60 * 1000, // 10 minutes
              evictionPolicy: 'LFU',
              compression: false,
            },
            symbols: {
              enabled: true,
              maxSize: 32, // MB
              ttl: 60 * 60 * 1000, // 1 hour
              evictionPolicy: 'LRU',
              compression: true,
            },
          },
        },
        concurrency: {
          maxWorkers: os.cpus().length,
          workerPool: {
            minWorkers: 2,
            maxWorkers: os.cpus().length,
            idleTimeout: 60000, // 1 minute
            taskTimeout: 30000, // 30 seconds
            memoryLimit: 256, // MB per worker
          },
          taskQueue: {
            maxSize: 1000,
            priority: true,
            batching: true,
            batchSize: 10,
            debounceMs: 100,
          },
          loadBalancing: {
            strategy: 'cpu-usage',
            healthCheck: true,
            healthCheckInterval: 30000, // 30 seconds
          },
        },
        optimization: {
          incrementalAnalysis: true,
          lazyLoading: true,
          prefetching: true,
          compression: true,
          minification: false,
          treeshaking: true,
          parallelization: {
            parsing: true,
            analysis: true,
            indexing: true,
          },
        },
      },
      security: {
        authentication: {
          jwtSecret: process.env.JWT_SECRET || 'your-super-secret-jwt-key',
          jwtExpiry: '1h',
          refreshTokenExpiry: '7d',
          apiKeys: {
            enabled: true,
            keyLength: 32,
            rateLimit: 1000, // requests per hour
            scopes: ['php:*'],
            expiryDays: 365,
          },
          mfa: {
            enabled: false,
            methods: ['totp'],
            required: false,
            backupCodes: true,
          },
          passwordPolicy: {
            minLength: 8,
            requireUppercase: true,
            requireLowercase: true,
            requireNumbers: true,
            requireSymbols: true,
            preventReuse: 5,
            maxAge: 90,
          },
        },
        authorization: {
          rbac: {
            enabled: true,
            roles: [
              {
                name: 'admin',
                description: 'Full system access',
                permissions: ['*'],
              },
              {
                name: 'user',
                description: 'Standard user access',
                permissions: ['php:analyze', 'php:complete', 'php:refactor'],
              },
              {
                name: 'readonly',
                description: 'Read-only access',
                permissions: ['php:analyze:read'],
              },
            ],
            hierarchical: true,
            inheritance: true,
          },
          permissions: {
            granular: true,
            resourceBased: true,
            timeRestricted: false,
            contextual: true,
          },
          resourceAccess: {
            defaultDeny: true,
            pathWhitelist: [],
            pathBlacklist: ['/etc', '/proc', '/sys'],
            fileTypeRestrictions: ['.php', '.js', '.ts', '.json'],
            sizeLimit: 10 * 1024 * 1024, // 10MB
          },
        },
        encryption: {
          algorithm: 'aes-256-gcm',
          keyLength: 32,
          saltRounds: 12,
          dataAtRest: true,
          dataInTransit: true,
          keyRotation: {
            enabled: false,
            interval: 90,
            gracePeriod: 7,
            automatic: false,
          },
        },
        validation: {
          inputSanitization: true,
          outputEscaping: true,
          sqlInjectionPrevention: true,
          xssProtection: true,
          csrfProtection: true,
          maxRequestSize: 10 * 1024 * 1024, // 10MB
          maxUploadSize: 100 * 1024 * 1024, // 100MB
        },
        monitoring: {
          intrusion: {
            enabled: true,
            rules: [],
            blockDuration: 60, // minutes
            maxAttempts: 5,
            timeWindow: 15, // minutes
          },
          anomaly: {
            enabled: true,
            algorithms: ['statistical', 'behavioral'],
            sensitivity: 0.8,
            learningPeriod: 7,
          },
          realtime: true,
          alerting: {
            enabled: true,
            channels: ['email'],
            escalation: false,
            throttling: 300, // seconds
          },
          forensics: true,
        },
        sandbox: {
          enabled: true,
          isolation: {
            processIsolation: true,
            filesystem: true,
            network: true,
            memory: true,
          },
          resourceLimits: {
            maxMemory: 256, // MB
            maxCpu: 50, // percentage
            maxFiles: 1000,
            maxProcesses: 10,
            timeout: 30, // seconds
          },
          networkRestrictions: {
            outbound: false,
            allowedHosts: [],
            allowedPorts: [],
            dnsRestriction: true,
          },
        },
        audit: {
          enabled: true,
          events: [
            { type: 'authentication' as any, enabled: true, fields: ['user', 'ip', 'result'], sensitivity: 'medium' as any },
            { type: 'authorization' as any, enabled: true, fields: ['user', 'action', 'resource'], sensitivity: 'medium' as any },
            { type: 'data_access' as any, enabled: true, fields: ['user', 'resource', 'action'], sensitivity: 'high' as any },
            { type: 'security_violation' as any, enabled: true, fields: ['type', 'source', 'details'], sensitivity: 'critical' as any },
          ],
          retention: 90, // days
          integrity: true,
          encryption: true,
          compression: true,
        },
      },
      logging: {
        level: (process.env.LOG_LEVEL as any) || 'info',
        console: true,
        structured: true,
        file: process.env.LOG_FILE,
      },
    };

    // If config file is provided, merge with defaults
    if (configPath) {
      try {
        const userConfig = JSON.parse(require('fs').readFileSync(configPath, 'utf-8'));
        return this.mergeConfig(defaultConfig, userConfig);
      } catch (error) {
        console.warn(`Warning: Could not load config file ${configPath}, using defaults`);
      }
    }

    // Load from environment variables
    return this.loadFromEnvironment(defaultConfig);
  }

  private mergeConfig(defaultConfig: ServerConfig, userConfig: any): ServerConfig {
    // Deep merge configuration
    return this.deepMerge(defaultConfig, userConfig);
  }

  private loadFromEnvironment(config: ServerConfig): ServerConfig {
    // Override specific values from environment variables
    if (process.env.PHP_VERSION) {
      config.php.version = process.env.PHP_VERSION;
    }
    
    if (process.env.MAX_MEMORY) {
      config.performance.maxMemoryUsage = parseInt(process.env.MAX_MEMORY);
    }

    if (process.env.PLUGINS_ENABLED) {
      config.plugins.enabled = process.env.PLUGINS_ENABLED === 'true';
    }

    return config;
  }

  private deepMerge(target: any, source: any): any {
    const result = { ...target };
    
    for (const key in source) {
      if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
        result[key] = this.deepMerge(target[key] || {}, source[key]);
      } else {
        result[key] = source[key];
      }
    }
    
    return result;
  }

  private setupEventHandlers(): void {
    // MCP Server events
    this.mcpServer.on('client-connected', (clientId) => {
      console.log(`📱 Client connected: ${clientId}`);
    });

    this.mcpServer.on('client-disconnected', (clientId) => {
      console.log(`📱 Client disconnected: ${clientId}`);
    });

    // Plugin events
    this.pluginManager.on('plugin-loaded', (plugin) => {
      console.log(`🔌 Plugin loaded: ${plugin.manifest.name} v${plugin.manifest.version}`);
    });

    this.pluginManager.on('plugin-error', (plugin, error) => {
      console.error(`🔌 Plugin error in ${plugin.manifest.name}:`, error);
    });

    // Security events
    this.securityManager.on('security-alert', (alert) => {
      console.warn(`🚨 Security alert: ${alert.type} - ${alert.severity}`);
    });

    this.securityManager.on('user-authenticated', (user, clientInfo) => {
      console.log(`🔐 User authenticated: ${user.username} from ${clientInfo.ip}`);
    });

    // Performance events
    this.performanceOptimizer.on('optimization-applied', (optimization) => {
      console.log(`⚡ Applied optimization: ${optimization.type}`);
    });

    // Context manager events
    this.contextManager.on('project-initialized', (project) => {
      console.log(`🏗️  Project initialized: ${project.root}`);
    });

    this.contextManager.on('file-changed', (filePath, project) => {
      console.log(`📝 File changed: ${path.relative(project.root, filePath)}`);
    });
  }

  private async initializeSecurity(): Promise<void> {
    // Security initialization is handled in the constructor
    // Additional setup could be done here if needed
  }

  private async initializeContextManager(): Promise<void> {
    // Context manager initialization is handled in the constructor
    // Additional setup could be done here if needed
  }

  private async loadPlugins(): Promise<void> {
    if (!this.config.plugins.autoLoad) {
      return;
    }

    try {
      const pluginDir = this.config.plugins.directory;
      const entries = await fs.readdir(pluginDir, { withFileTypes: true });
      
      for (const entry of entries) {
        if (entry.isDirectory()) {
          const pluginPath = path.join(pluginDir, entry.name);
          try {
            const plugin = await this.pluginManager.loadPlugin(pluginPath);
            await this.pluginManager.activatePlugin(plugin.manifest.name);
          } catch (error) {
            console.error(`Failed to load plugin ${entry.name}:`, error);
          }
        }
      }
    } catch (error) {
      console.warn('Plugin directory not found or not accessible:', error.message);
    }
  }

  private async startPerformanceMonitoring(): Promise<void> {
    // Performance monitoring is started automatically in the constructor
  }

  private setupGracefulShutdown(): void {
    const shutdown = async (signal: string) => {
      console.log(`\n📡 Received ${signal}, shutting down gracefully...`);
      await this.stop();
      process.exit(0);
    };

    process.on('SIGTERM', () => shutdown('SIGTERM'));
    process.on('SIGINT', () => shutdown('SIGINT'));
    process.on('SIGUSR2', () => shutdown('SIGUSR2')); // For nodemon

    process.on('uncaughtException', (error) => {
      console.error('💥 Uncaught Exception:', error);
      this.stop().finally(() => process.exit(1));
    });

    process.on('unhandledRejection', (reason, promise) => {
      console.error('💥 Unhandled Rejection at:', promise, 'reason:', reason);
      this.stop().finally(() => process.exit(1));
    });
  }
}

export interface ServerStatus {
  running: boolean;
  version: string;
  uptime: number;
  memory: {
    used: number;
    total: number;
    external: number;
  };
  performance: any;
  security: any;
  plugins: {
    total: number;
    active: number;
  };
  context: any;
}

// CLI entry point
if (require.main === module) {
  const configPath = process.argv[2];
  const server = new PHPAnalysisMCPServer(configPath);
  
  server.start().catch((error) => {
    console.error('Failed to start server:', error);
    process.exit(1);
  });
}