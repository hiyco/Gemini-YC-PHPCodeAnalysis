# YC-PHPCodeAnalysis&MCP SDK 使用文档

本文档介绍如何使用 **YC-PHPCodeAnalysis&MCP** 项目集成的 MCP (Model Context Protocol) PHP SDK。

## 概述

YC-PHPCodeAnalysis&MCP SDK 提供了完整的 Model Context Protocol 实现，结合了传统代码分析功能和AI集成能力，支持：
- 多种AI模型提供商（阿里QWEN、DeepSeek、豆包、文心一言、OpenAI、Claude）
- AI增强的代码分析和质量检查
- MCP 服务器和客户端功能
- 多种传输协议（STDIO、WebSocket、HTTP）
- 丰富的内置工具和资源

## 安装和配置

### 基本要求

- PHP 8.1+
- Composer
- 必需的 PHP 扩展：curl, json, mbstring

### 安装依赖

```bash
composer install
```

### 基本配置

```php
use YcPca\Mcp\McpSdk;
use YcPca\Mcp\Transport\StdioTransport;

// 创建 SDK 实例
$sdk = new McpSdk([
    'name' => 'My-MCP-App',
    'version' => '1.0.0'
]);
```

## AI 模型提供商使用

### 1. 阿里 QWEN

```php
use YcPca\Mcp\Model\ModelProviderFactory;

// 创建 QWEN 提供商
$provider = ModelProviderFactory::create('qwen', [
    'api_key' => 'your-dashscope-api-key',
    'model' => 'qwen-turbo' // 可选，默认为 qwen-turbo
]);

// 发送聊天请求
$response = $provider->chat([
    ['role' => 'user', 'content' => '你好，请介绍一下自己']
]);

echo $response->getContent();
```

支持的模型：
- `qwen-turbo`: 8192 上下文，1500 最大令牌
- `qwen-plus`: 32768 上下文，2000 最大令牌
- `qwen-max`: 8192 上下文，2000 最大令牌
- `qwen-max-longcontext`: 30000 上下文，2000 最大令牌

### 2. DeepSeek

```php
$provider = ModelProviderFactory::create('deepseek', [
    'api_key' => 'your-deepseek-api-key',
    'model' => 'deepseek-chat'
]);

$response = $provider->complete('编写一个PHP函数来计算斐波那契数列');
echo $response->getContent();
```

支持的模型：
- `deepseek-chat`: 32768 上下文，4096 最大令牌
- `deepseek-coder`: 16384 上下文，4096 最大令牌

### 3. 字节跳动豆包 (Doubao)

```php
$provider = ModelProviderFactory::create('doubao', [
    'api_key' => 'your-volcano-api-key',
    'model' => 'doubao-lite-4k'
]);

$response = $provider->complete('解释什么是大语言模型');
echo $response->getContent();
```

支持的模型：
- `doubao-lite-4k`: 4096 上下文
- `doubao-lite-32k`: 32768 上下文
- `doubao-pro-4k`: 4096 上下文
- `doubao-pro-32k`: 32768 上下文

### 4. 百度文心一言 (ERNIE)

```php
$provider = ModelProviderFactory::create('ernie', [
    'api_key' => 'your-baidu-api-key',
    'secret_key' => 'your-baidu-secret-key', // ERNIE 需要密钥对
    'model' => 'ernie-bot-turbo'
]);

$response = $provider->chat([
    ['role' => 'user', 'content' => '请写一首关于人工智能的诗']
]);

echo $response->getContent();
```

支持的模型：
- `ernie-bot-turbo`: 8192 上下文，1024 最大令牌
- `ernie-bot`: 8192 上下文，1024 最大令牌
- `ernie-bot-4`: 8192 上下文，1024 最大令牌

### 5. OpenAI

```php
$provider = ModelProviderFactory::create('openai', [
    'api_key' => 'your-openai-api-key',
    'model' => 'gpt-3.5-turbo'
]);

$response = $provider->complete('What is artificial intelligence?');
echo $response->getContent();
```

### 6. Anthropic Claude

```php
$provider = ModelProviderFactory::create('claude', [
    'api_key' => 'your-anthropic-api-key',
    'model' => 'claude-3-sonnet-20240229'
]);

$response = $provider->complete('Explain the concept of machine learning');
echo $response->getContent();
```

## 流式响应

所有提供商都支持流式响应：

```php
$provider = ModelProviderFactory::create('qwen', [
    'api_key' => 'your-api-key'
]);

foreach ($provider->streamComplete('写一个长故事') as $chunk) {
    echo $chunk->getContent();
    flush(); // 实时输出
}
```

## MCP 服务器创建

### 基本服务器

```php
use YcPca\Mcp\Server\McpServer;
use YcPca\Mcp\Transport\StdioTransport;

// 创建服务器
$server = new McpServer(
    transport: new StdioTransport(),
    config: [
        'name' => 'My-MCP-Server',
        'version' => '1.0.0'
    ]
);

// 注册工具
$server->registerTool(
    'hello',
    function (array $args): string {
        $name = $args['name'] ?? 'World';
        return "Hello, {$name}!";
    },
    [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Name to greet'
            ]
        ]
    ],
    'A simple greeting tool'
);

// 启动服务器
$server->start();
```

### 注册资源

```php
$server->registerResource(
    'config://app',
    function (string $uri): array {
        return [
            'app_name' => 'My Application',
            'version' => '1.0.0',
            'environment' => 'production'
        ];
    },
    'app-config',
    'Application configuration',
    'application/json'
);
```

### 注册提示符

```php
$server->registerPrompt(
    'code_review',
    function (array $args): array {
        $code = $args['code'];
        return [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Please review this code:\n\n{$code}"
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
        ]
    ],
    'Generate a code review prompt'
);
```

## 内置工具

SDK 提供了丰富的内置工具：

### 文件操作工具

- `read_file`: 读取文件内容
- `write_file`: 写入文件内容
- `list_directory`: 列出目录内容

### 系统工具

- `execute_command`: 执行系统命令
- `system_info`: 获取系统信息

### 实用工具

- `format_json`: 格式化 JSON
- `base64`: Base64 编码/解码
- `hash`: 生成哈希值

使用内置工具：

```php
use YcPca\Mcp\Server\Tools\BuiltinTools;

// 注册所有内置工具
BuiltinTools::registerAll($server);
```

## 完整示例

```php
#!/usr/bin/env php
<?php

require_once 'vendor/autoload.php';

use YcPca\Mcp\Server\McpServer;
use YcPca\Mcp\Server\Tools\BuiltinTools;
use YcPca\Mcp\Model\ModelProviderFactory;

// 创建服务器
$server = new McpServer();

// 注册内置工具
BuiltinTools::registerAll($server);

// 注册AI聊天工具
$server->registerTool(
    'ai_chat',
    function (array $args): string {
        $provider = ModelProviderFactory::create('qwen', [
            'api_key' => $args['api_key']
        ]);
        
        $response = $provider->complete($args['message']);
        return $response->getContent();
    },
    [
        'type' => 'object',
        'properties' => [
            'message' => ['type' => 'string', 'description' => 'Message to AI'],
            'api_key' => ['type' => 'string', 'description' => 'API key']
        ],
        'required' => ['message', 'api_key']
    ],
    'Chat with AI using QWEN'
);

// 启动服务器
echo "Starting MCP server...\n";
$server->start();
```

## 错误处理

```php
use YcPca\Mcp\McpException;
use YcPca\Mcp\McpModelException;

try {
    $provider = ModelProviderFactory::create('qwen', [
        'api_key' => 'invalid-key'
    ]);
    
    $response = $provider->complete('Hello');
    
} catch (McpModelException $e) {
    echo "Model error: " . $e->getMessage();
} catch (McpException $e) {
    echo "MCP error: " . $e->getMessage();
} catch (Exception $e) {
    echo "General error: " . $e->getMessage();
}
```

## 最佳实践

### 1. 配置管理

```php
$config = [
    'qwen' => [
        'api_key' => getenv('QWEN_API_KEY'),
        'model' => 'qwen-turbo',
        'temperature' => 0.7
    ],
    'openai' => [
        'api_key' => getenv('OPENAI_API_KEY'),
        'model' => 'gpt-3.5-turbo'
    ]
];
```

### 2. 日志记录

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('mcp');
$logger->pushHandler(new StreamHandler('mcp.log', Logger::INFO));

$provider = ModelProviderFactory::create('qwen', $config, $logger);
```

### 3. 连接测试

```php
if (!$provider->testConnection()) {
    throw new Exception('Failed to connect to AI provider');
}
```

### 4. 资源管理

```php
// 获取使用统计
$stats = $provider->getStats();
echo "Requests sent: " . $stats['requests_sent'];
echo "Tokens used: " . $stats['tokens_used'];
```

## 故障排除

### 常见问题

1. **API 密钥错误**
   - 确保 API 密钥正确且有效
   - 检查密钥权限和配额

2. **网络连接问题**
   - 检查网络连接
   - 验证防火墙设置

3. **模型不支持**
   - 检查模型名称是否正确
   - 确认提供商支持该模型

4. **内存不足**
   - 调整 PHP 内存限制
   - 使用流式响应处理大量数据

### 调试技巧

```php
// 启用详细日志
$logger = new Logger('debug');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// 检查提供商信息
$info = ModelProviderFactory::getProviderInfo('qwen');
print_r($info);

// 验证配置
$errors = ModelProviderFactory::validateConfig('qwen', $config);
if (!empty($errors)) {
    print_r($errors);
}
```

## 性能优化

### 1. 连接复用

```php
// 保持提供商实例以复用连接
static $providers = [];

if (!isset($providers[$providerName])) {
    $providers[$providerName] = ModelProviderFactory::create($providerName, $config);
}

$provider = $providers[$providerName];
```

### 2. 异步处理

```php
// 使用流式响应减少延迟
foreach ($provider->streamComplete($prompt) as $chunk) {
    // 实时处理响应块
    processChunk($chunk->getContent());
}
```

### 3. 缓存策略

```php
$cacheKey = md5($prompt);
$cached = $cache->get($cacheKey);

if ($cached) {
    return $cached;
}

$response = $provider->complete($prompt);
$cache->set($cacheKey, $response->getContent(), 3600);
```

## 更多信息

- [MCP 规范](https://github.com/modelcontextprotocol/specification)
- [项目 GitHub](https://github.com/hiyco/YC-PHPCodeAnalysis-MCP)
- [问题报告](https://github.com/hiyco/YC-PHPCodeAnalysis-MCP/issues)

---

*最后更新：2025-01-15*