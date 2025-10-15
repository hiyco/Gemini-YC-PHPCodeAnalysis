<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: Model Provider Factory
 */

declare(strict_types=1);

namespace YcPca\Mcp\Model;

use YcPca\Mcp\Model\Providers\QwenProvider;
use YcPca\Mcp\Model\Providers\DeepSeekProvider;
use YcPca\Mcp\Model\Providers\DoubaoProvider;
use YcPca\Mcp\Model\Providers\ErnieProvider;
use YcPca\Mcp\Model\Providers\OpenAIProvider;
use YcPca\Mcp\Model\Providers\ClaudeProvider;
use YcPca\Mcp\McpModelException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Model Provider Factory
 * 
 * Creates instances of different AI model providers.
 * Supports all major Chinese and international providers.
 * 
 * @package YcPca\Mcp\Model
 * @author YC
 * @version 1.0.0
 */
class ModelProviderFactory
{
    /**
     * Supported providers configuration
     */
    private const PROVIDERS = [
        'qwen' => [
            'class' => QwenProvider::class,
            'name' => 'Alibaba QWEN',
            'description' => 'Alibaba Qianwen large language model series',
            'base_url' => 'https://dashscope.aliyuncs.com/api/v1',
            'auth_type' => 'api_key',
            'models' => [
                'qwen-turbo' => ['context' => 8192, 'max_tokens' => 1500],
                'qwen-plus' => ['context' => 32768, 'max_tokens' => 2000],
                'qwen-max' => ['context' => 8192, 'max_tokens' => 2000],
                'qwen-max-longcontext' => ['context' => 30000, 'max_tokens' => 2000]
            ]
        ],
        'deepseek' => [
            'class' => DeepSeekProvider::class,
            'name' => 'DeepSeek',
            'description' => 'DeepSeek Chat and Code models',
            'base_url' => 'https://api.deepseek.com',
            'auth_type' => 'api_key',
            'models' => [
                'deepseek-chat' => ['context' => 32768, 'max_tokens' => 4096],
                'deepseek-coder' => ['context' => 16384, 'max_tokens' => 4096]
            ]
        ],
        'doubao' => [
            'class' => DoubaoProvider::class,
            'name' => 'ByteDance Doubao',
            'description' => 'ByteDance Doubao (Volcano Engine) models',
            'base_url' => 'https://ark.cn-beijing.volces.com/api/v3',
            'auth_type' => 'api_key',
            'models' => [
                'doubao-lite-4k' => ['context' => 4096, 'max_tokens' => 4096],
                'doubao-lite-32k' => ['context' => 32768, 'max_tokens' => 4096],
                'doubao-pro-4k' => ['context' => 4096, 'max_tokens' => 4096],
                'doubao-pro-32k' => ['context' => 32768, 'max_tokens' => 4096]
            ]
        ],
        'ernie' => [
            'class' => ErnieProvider::class,
            'name' => 'Baidu ERNIE',
            'description' => 'Baidu ERNIE (Wenxin Yiyan) models',
            'base_url' => 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat',
            'auth_type' => 'api_key_secret',
            'models' => [
                'ernie-bot-turbo' => ['context' => 8192, 'max_tokens' => 1024],
                'ernie-bot' => ['context' => 8192, 'max_tokens' => 1024],
                'ernie-bot-4' => ['context' => 8192, 'max_tokens' => 1024]
            ]
        ],
        'openai' => [
            'class' => OpenAIProvider::class,
            'name' => 'OpenAI',
            'description' => 'OpenAI GPT models',
            'base_url' => 'https://api.openai.com/v1',
            'auth_type' => 'api_key',
            'models' => [
                'gpt-3.5-turbo' => ['context' => 16385, 'max_tokens' => 4096],
                'gpt-4' => ['context' => 8192, 'max_tokens' => 4096],
                'gpt-4-turbo-preview' => ['context' => 128000, 'max_tokens' => 4096],
                'gpt-4o' => ['context' => 128000, 'max_tokens' => 4096]
            ]
        ],
        'claude' => [
            'class' => ClaudeProvider::class,
            'name' => 'Anthropic Claude',
            'description' => 'Anthropic Claude models',
            'base_url' => 'https://api.anthropic.com',
            'auth_type' => 'api_key',
            'models' => [
                'claude-3-haiku-20240307' => ['context' => 200000, 'max_tokens' => 4096],
                'claude-3-sonnet-20240229' => ['context' => 200000, 'max_tokens' => 4096],
                'claude-3-opus-20240229' => ['context' => 200000, 'max_tokens' => 4096]
            ]
        ]
    ];
    
    /**
     * Create model provider instance
     *
     * @param string $provider Provider name
     * @param array $config Provider configuration
     * @param LoggerInterface|null $logger Logger instance
     * @return ModelProviderInterface Provider instance
     * @throws McpModelException
     */
    public static function create(
        string $provider, 
        array $config = [], 
        ?LoggerInterface $logger = null
    ): ModelProviderInterface {
        $logger = $logger ?? new NullLogger();
        
        if (!isset(self::PROVIDERS[$provider])) {
            throw new McpModelException("Unsupported model provider: {$provider}");
        }
        
        $providerConfig = self::PROVIDERS[$provider];
        $className = $providerConfig['class'];
        
        if (!class_exists($className)) {
            throw new McpModelException("Provider class not found: {$className}");
        }
        
        // Merge default configuration with user configuration
        $finalConfig = array_merge([
            'base_url' => $providerConfig['base_url'],
            'models' => $providerConfig['models']
        ], $config);
        
        try {
            $instance = new $className($finalConfig, $logger);
            
            if (!$instance instanceof ModelProviderInterface) {
                throw new McpModelException(
                    "Provider class must implement ModelProviderInterface: {$className}"
                );
            }
            
            $logger->info("Created model provider", [
                'provider' => $provider,
                'class' => $className
            ]);
            
            return $instance;
        } catch (\Throwable $e) {
            throw new McpModelException(
                "Failed to create provider {$provider}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
    
    /**
     * Get supported providers
     *
     * @return array List of supported provider names
     */
    public static function getSupportedProviders(): array
    {
        return array_keys(self::PROVIDERS);
    }
    
    /**
     * Get provider information
     *
     * @param string $provider Provider name
     * @return array Provider information
     * @throws McpModelException
     */
    public static function getProviderInfo(string $provider): array
    {
        if (!isset(self::PROVIDERS[$provider])) {
            throw new McpModelException("Unsupported model provider: {$provider}");
        }
        
        $info = self::PROVIDERS[$provider];
        unset($info['class']); // Remove internal class reference
        
        return $info;
    }
    
    /**
     * Get all providers information
     *
     * @return array All providers information
     */
    public static function getAllProvidersInfo(): array
    {
        $providers = [];
        
        foreach (self::PROVIDERS as $name => $info) {
            $providerInfo = $info;
            unset($providerInfo['class']);
            $providers[$name] = $providerInfo;
        }
        
        return $providers;
    }
    
    /**
     * Check if provider is supported
     *
     * @param string $provider Provider name
     * @return bool True if supported
     */
    public static function isSupported(string $provider): bool
    {
        return isset(self::PROVIDERS[$provider]);
    }
    
    /**
     * Get models for provider
     *
     * @param string $provider Provider name
     * @return array Models information
     * @throws McpModelException
     */
    public static function getProviderModels(string $provider): array
    {
        if (!isset(self::PROVIDERS[$provider])) {
            throw new McpModelException("Unsupported model provider: {$provider}");
        }
        
        return self::PROVIDERS[$provider]['models'] ?? [];
    }
    
    /**
     * Find provider by model name
     *
     * @param string $modelName Model name
     * @return string|null Provider name or null if not found
     */
    public static function findProviderByModel(string $modelName): ?string
    {
        foreach (self::PROVIDERS as $providerName => $providerConfig) {
            if (isset($providerConfig['models'][$modelName])) {
                return $providerName;
            }
        }
        
        return null;
    }
    
    /**
     * Get model information across all providers
     *
     * @param string $modelName Model name
     * @return array|null Model information or null if not found
     */
    public static function getModelInfo(string $modelName): ?array
    {
        foreach (self::PROVIDERS as $providerName => $providerConfig) {
            if (isset($providerConfig['models'][$modelName])) {
                return array_merge(
                    $providerConfig['models'][$modelName],
                    ['provider' => $providerName]
                );
            }
        }
        
        return null;
    }
    
    /**
     * Validate provider configuration
     *
     * @param string $provider Provider name
     * @param array $config Configuration to validate
     * @return array Validation errors (empty if valid)
     */
    public static function validateConfig(string $provider, array $config): array
    {
        $errors = [];
        
        if (!isset(self::PROVIDERS[$provider])) {
            $errors[] = "Unsupported provider: {$provider}";
            return $errors;
        }
        
        $providerConfig = self::PROVIDERS[$provider];
        
        // Check required authentication
        switch ($providerConfig['auth_type']) {
            case 'api_key':
                if (empty($config['api_key'])) {
                    $errors[] = 'API key is required';
                }
                break;
                
            case 'api_key_secret':
                if (empty($config['api_key'])) {
                    $errors[] = 'API key is required';
                }
                if (empty($config['secret_key'])) {
                    $errors[] = 'Secret key is required';
                }
                break;
        }
        
        // Validate model if specified
        if (!empty($config['model'])) {
            $availableModels = array_keys($providerConfig['models']);
            if (!in_array($config['model'], $availableModels)) {
                $errors[] = "Unsupported model: {$config['model']}. Available: " . 
                           implode(', ', $availableModels);
            }
        }
        
        return $errors;
    }
}