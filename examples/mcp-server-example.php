<?php
#!/usr/bin/env php
<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: MCP Server Example
 */

require_once __DIR__ . '/../vendor/autoload.php';

use YcPca\Mcp\Server\McpServer;
use YcPca\Mcp\Server\Tools\BuiltinTools;
use YcPca\Mcp\Transport\StdioTransport;
use YcPca\Mcp\Model\ModelProviderFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Example MCP Server
 * 
 * This example demonstrates how to create and run an MCP server
 * with various tools, resources, and AI model integrations.
 */

// Create logger
$logger = new Logger('mcp-server');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::INFO));

try {
    // Create MCP server
    $server = new McpServer(
        transport: new StdioTransport(),
        config: [
            'name' => 'PHP-MCP-Example-Server',
            'version' => '1.0.0',
            'timeout' => 30
        ],
        logger: $logger
    );
    
    // Register built-in tools
    BuiltinTools::registerAll($server);
    
    // Register custom AI model tools
    registerAITools($server, $logger);
    
    // Register custom resources
    registerResources($server);
    
    // Register custom prompts
    registerPrompts($server);
    
    // Handle shutdown signals
    pcntl_signal(SIGTERM, function() use ($server, $logger) {
        $logger->info('Received SIGTERM, shutting down...');
        $server->stop();
        exit(0);
    });
    
    pcntl_signal(SIGINT, function() use ($server, $logger) {
        $logger->info('Received SIGINT, shutting down...');
        $server->stop();
        exit(0);
    });
    
    // Start server
    $logger->info('Starting MCP server...');
    $server->start();
    
} catch (Exception $e) {
    $logger->error('Server error: ' . $e->getMessage());
    exit(1);
}

/**
 * Register AI model tools
 */
function registerAITools(McpServer $server, Logger $logger): void
{
    // AI Chat tool
    $server->registerTool(
        'ai_chat',
        function (array $args) use ($logger): string {
            $provider = $args['provider'] ?? 'qwen';
            $model = $args['model'] ?? null;
            $message = $args['message'] ?? '';
            $apiKey = $args['api_key'] ?? '';
            
            if (empty($message)) {
                throw new InvalidArgumentException('Message is required');
            }
            
            if (empty($apiKey)) {
                throw new InvalidArgumentException('API key is required');
            }
            
            try {
                $config = [
                    'api_key' => $apiKey,
                    'model' => $model
                ];
                
                if ($provider === 'ernie') {
                    $config['secret_key'] = $args['secret_key'] ?? '';
                    if (empty($config['secret_key'])) {
                        throw new InvalidArgumentException('Secret key is required for ERNIE');
                    }
                }
                
                $aiProvider = ModelProviderFactory::create($provider, $config, $logger);
                $response = $aiProvider->complete($message);
                
                return $response->getContent();
                
            } catch (Exception $e) {
                $logger->error('AI chat error', ['error' => $e->getMessage()]);
                throw new RuntimeException('AI chat failed: ' . $e->getMessage());
            }
        },
        [
            'type' => 'object',
            'properties' => [
                'provider' => [
                    'type' => 'string',
                    'enum' => ['qwen', 'deepseek', 'doubao', 'ernie', 'openai', 'claude'],
                    'description' => 'AI provider to use',
                    'default' => 'qwen'
                ],
                'model' => [
                    'type' => 'string',
                    'description' => 'Model name (optional, uses provider default)'
                ],
                'message' => [
                    'type' => 'string',
                    'description' => 'Message to send to the AI'
                ],
                'api_key' => [
                    'type' => 'string',
                    'description' => 'API key for the provider'
                ],
                'secret_key' => [
                    'type' => 'string',
                    'description' => 'Secret key (required for ERNIE)'
                ]
            ],
            'required' => ['message', 'api_key']
        ],
        'Chat with AI models (QWEN, DeepSeek, Doubao, ERNIE, OpenAI, Claude)'
    );
    
    // AI model info tool
    $server->registerTool(
        'ai_model_info',
        function (array $args): array {
            $provider = $args['provider'] ?? '';
            
            if (empty($provider)) {
                return [
                    'available_providers' => ModelProviderFactory::getSupportedProviders(),
                    'provider_info' => ModelProviderFactory::getAllProvidersInfo()
                ];
            }
            
            if (!ModelProviderFactory::isSupported($provider)) {
                throw new InvalidArgumentException("Unsupported provider: {$provider}");
            }
            
            return [
                'provider' => $provider,
                'info' => ModelProviderFactory::getProviderInfo($provider),
                'models' => ModelProviderFactory::getProviderModels($provider)
            ];
        },
        [
            'type' => 'object',
            'properties' => [
                'provider' => [
                    'type' => 'string',
                    'description' => 'Provider name (optional, returns all if not specified)'
                ]
            ]
        ],
        'Get information about available AI models and providers'
    );
}

/**
 * Register custom resources
 */
function registerResources(McpServer $server): void
{
    // Project info resource
    $server->registerResource(
        'project://info',
        function (string $uri): array {
            $projectRoot = dirname(__DIR__);
            $composerFile = $projectRoot . '/composer.json';
            
            $info = [
                'name' => 'PCA - PHP Code Analysis with MCP',
                'version' => '1.0.0',
                'description' => 'PHP静态代码分析工具，集成MCP模型调用功能',
                'project_root' => $projectRoot,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            if (file_exists($composerFile)) {
                $composer = json_decode(file_get_contents($composerFile), true);
                $info['composer'] = [
                    'name' => $composer['name'] ?? 'unknown',
                    'description' => $composer['description'] ?? '',
                    'version' => $composer['version'] ?? 'dev',
                    'authors' => $composer['authors'] ?? []
                ];
            }
            
            return $info;
        },
        'project-info',
        'Information about the current project',
        'application/json'
    );
    
    // System status resource
    $server->registerResource(
        'system://status',
        function (string $uri): array {
            return [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'uptime' => sys_getloadavg(),
                'disk_free' => disk_free_space('.'),
                'disk_total' => disk_total_space('.'),
                'timestamp' => time(),
                'date' => date('Y-m-d H:i:s')
            ];
        },
        'system-status',
        'Current system status and resource usage',
        'application/json'
    );
}

/**
 * Register custom prompts
 */
function registerPrompts(McpServer $server): void
{
    // Code review prompt
    $server->registerPrompt(
        'code_review',
        function (array $args): array {
            $code = $args['code'] ?? '';
            $language = $args['language'] ?? 'php';
            $focus = $args['focus'] ?? 'general';
            
            if (empty($code)) {
                throw new InvalidArgumentException('Code is required');
            }
            
            $focusInstructions = match ($focus) {
                'security' => '重点关注安全漏洞、输入验证、权限检查等安全问题。',
                'performance' => '重点关注性能问题、算法复杂度、资源使用效率等。',
                'maintainability' => '重点关注代码可读性、维护性、代码结构等。',
                'best_practices' => '重点关注编程最佳实践、代码规范、设计模式等。',
                default => '进行全面的代码审查，包括功能性、安全性、性能和维护性。'
            };
            
            return [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "请对以下{$language}代码进行专业的代码审查。{$focusInstructions}\n\n代码:\n```{$language}\n{$code}\n```\n\n请提供详细的审查意见，包括：\n1. 发现的问题和建议的改进\n2. 代码质量评估\n3. 具体的修改建议\n4. 最佳实践推荐"
                        ]
                    ]
                ]
            ];
        },
        [
            [
                'name' => 'code',
                'description' => 'Code to review',
                'required' => true
            ],
            [
                'name' => 'language',
                'description' => 'Programming language (default: php)',
                'required' => false
            ],
            [
                'name' => 'focus',
                'description' => 'Review focus: general, security, performance, maintainability, best_practices',
                'required' => false
            ]
        ],
        'Generate a comprehensive code review prompt'
    );
    
    // Documentation prompt
    $server->registerPrompt(
        'generate_docs',
        function (array $args): array {
            $code = $args['code'] ?? '';
            $type = $args['type'] ?? 'api';
            $language = $args['language'] ?? 'php';
            
            if (empty($code)) {
                throw new InvalidArgumentException('Code is required');
            }
            
            $typeInstructions = match ($type) {
                'api' => '生成API文档，包括端点说明、参数、返回值、示例等。',
                'class' => '生成类文档，包括类说明、属性、方法、使用示例等。',
                'function' => '生成函数文档，包括函数说明、参数、返回值、使用示例等。',
                'readme' => '生成README文档，包括项目介绍、安装、配置、使用方法等。',
                default => '生成完整的技术文档。'
            };
            
            return [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "请为以下{$language}代码生成专业的技术文档。{$typeInstructions}\n\n代码:\n```{$language}\n{$code}\n```\n\n请生成：\n1. 清晰的功能说明\n2. 详细的参数和返回值文档\n3. 实用的使用示例\n4. 注意事项和最佳实践\n5. 相关的错误处理说明"
                        ]
                    ]
                ]
            ];
        },
        [
            [
                'name' => 'code',
                'description' => 'Code to document',
                'required' => true
            ],
            [
                'name' => 'type',
                'description' => 'Documentation type: api, class, function, readme',
                'required' => false
            ],
            [
                'name' => 'language',
                'description' => 'Programming language (default: php)',
                'required' => false
            ]
        ],
        'Generate comprehensive documentation for code'
    );
}