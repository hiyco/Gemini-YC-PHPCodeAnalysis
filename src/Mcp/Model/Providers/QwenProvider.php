<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: Alibaba QWEN Model Provider
 */

declare(strict_types=1);

namespace YcPca\Mcp\Model\Providers;

use YcPca\Mcp\Model\ModelProviderInterface;
use YcPca\Mcp\Model\CompletionResponse;
use YcPca\Mcp\McpModelException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Alibaba QWEN Provider
 * 
 * Implements integration with Alibaba's QWEN (Qianwen) models
 * through the DashScope API.
 * 
 * @package YcPca\Mcp\Model\Providers
 * @author YC
 * @version 1.0.0
 */
class QwenProvider implements ModelProviderInterface
{
    private const PROVIDER_NAME = 'qwen';
    
    private LoggerInterface $logger;
    private array $config;
    private array $stats = [
        'requests_sent' => 0,
        'tokens_used' => 0,
        'errors' => 0,
        'last_request' => null
    ];
    
    public function __construct(array $config, ?LoggerInterface $logger = null)
    {
        $this->config = array_merge([
            'base_url' => 'https://dashscope.aliyuncs.com/api/v1',
            'api_key' => null,
            'model' => 'qwen-turbo',
            'timeout' => 30,
            'max_retries' => 3,
            'temperature' => 0.7,
            'top_p' => 0.8,
            'top_k' => 0,
            'repetition_penalty' => 1.1,
            'seed' => null,
            'enable_search' => false
        ], $config);
        
        $this->logger = $logger ?? new NullLogger();
        
        if (empty($this->config['api_key'])) {
            throw new McpModelException('QWEN API key is required');
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }
    
    /**
     * {@inheritDoc}
     */
    public function getSupportedModels(): array
    {
        return [
            'qwen-turbo' => ['context' => 8192, 'max_tokens' => 1500],
            'qwen-plus' => ['context' => 32768, 'max_tokens' => 2000],
            'qwen-max' => ['context' => 8192, 'max_tokens' => 2000],
            'qwen-max-longcontext' => ['context' => 30000, 'max_tokens' => 2000],
            'qwen-7b-chat' => ['context' => 8192, 'max_tokens' => 2048],
            'qwen-14b-chat' => ['context' => 8192, 'max_tokens' => 2048],
            'qwen-72b-chat' => ['context' => 32768, 'max_tokens' => 2048]
        ];
    }
    
    /**
     * {@inheritDoc}
     */
    public function complete(string $prompt, array $options = []): CompletionResponse
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];
        
        return $this->chat($messages, $options);
    }
    
    /**
     * {@inheritDoc}
     */
    public function chat(array $messages, array $options = []): CompletionResponse
    {
        $requestData = $this->buildChatRequest($messages, $options);
        
        try {
            $response = $this->makeRequest('/chat/completions', $requestData);
            $this->stats['requests_sent']++;
            $this->stats['last_request'] = time();
            
            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new McpModelException("QWEN API request failed: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function streamComplete(string $prompt, array $options = []): \Generator
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];
        
        yield from $this->streamChat($messages, $options);
    }
    
    /**
     * {@inheritDoc}
     */
    public function streamChat(array $messages, array $options = []): \Generator
    {
        $options['stream'] = true;
        $requestData = $this->buildChatRequest($messages, $options);
        
        try {
            $stream = $this->makeStreamRequest('/chat/completions', $requestData);
            $this->stats['requests_sent']++;
            $this->stats['last_request'] = time();
            
            foreach ($stream as $chunk) {
                if (!empty($chunk)) {
                    $response = $this->parseStreamChunk($chunk);
                    if ($response) {
                        yield $response;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new McpModelException("QWEN stream request failed: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function countTokens(string $text): int
    {
        // QWEN token counting approximation
        // Chinese characters: ~1.5 tokens each
        // English words: ~1.3 tokens each
        // This is an approximation; actual tokenization may differ
        
        $chineseChars = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text);
        $englishWords = str_word_count(preg_replace('/[\x{4e00}-\x{9fff}]/u', '', $text));
        
        return (int) ($chineseChars * 1.5 + $englishWords * 1.3);
    }
    
    /**
     * {@inheritDoc}
     */
    public function getModelInfo(string $model): array
    {
        $models = $this->getSupportedModels();
        return $models[$model] ?? [];
    }
    
    /**
     * {@inheritDoc}
     */
    public function testConnection(): bool
    {
        try {
            $this->complete('测试连接', ['max_tokens' => 10]);
            return true;
        } catch (\Exception $e) {
            $this->logger->warning('QWEN connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function getStats(): array
    {
        return $this->stats;
    }
    
    /**
     * Build chat request data
     *
     * @param array $messages Chat messages
     * @param array $options Request options
     * @return array Request data
     */
    private function buildChatRequest(array $messages, array $options): array
    {
        $data = [
            'model' => $options['model'] ?? $this->config['model'],
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $this->config['temperature'],
            'top_p' => $options['top_p'] ?? $this->config['top_p'],
            'max_tokens' => $options['max_tokens'] ?? 1500,
            'stream' => $options['stream'] ?? false
        ];
        
        // Add optional parameters
        if ($options['top_k'] ?? $this->config['top_k']) {
            $data['top_k'] = $options['top_k'] ?? $this->config['top_k'];
        }
        
        if ($options['repetition_penalty'] ?? $this->config['repetition_penalty']) {
            $data['repetition_penalty'] = $options['repetition_penalty'] ?? $this->config['repetition_penalty'];
        }
        
        if ($options['seed'] ?? $this->config['seed']) {
            $data['seed'] = $options['seed'] ?? $this->config['seed'];
        }
        
        if ($options['enable_search'] ?? $this->config['enable_search']) {
            $data['enable_search'] = true;
        }
        
        if (!empty($options['stop'])) {
            $data['stop'] = $options['stop'];
        }
        
        return $data;
    }
    
    /**
     * Make HTTP request to QWEN API
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     * @throws McpModelException
     */
    private function makeRequest(string $endpoint, array $data): array
    {
        $url = $this->config['base_url'] . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['api_key'],
            'X-DashScope-SSE: enable'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'YC-PCA-MCP-PHP/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new McpModelException("cURL error: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new McpModelException("HTTP error: {$httpCode}, Response: {$response}");
        }
        
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new McpModelException("Invalid JSON response: " . json_last_error_msg());
        }
        
        return $decodedResponse;
    }
    
    /**
     * Make streaming request to QWEN API
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return \Generator Stream of response chunks
     * @throws McpModelException
     */
    private function makeStreamRequest(string $endpoint, array $data): \Generator
    {
        $url = $this->config['base_url'] . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['api_key'],
            'X-DashScope-SSE: enable'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => function($ch, $chunk) {
                return strlen($chunk);
            },
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'YC-PCA-MCP-PHP/1.0'
        ]);
        
        // For streaming, we need to handle the response differently
        // This is a simplified implementation
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new McpModelException("HTTP error: {$httpCode}");
        }
        
        // Parse SSE stream
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                if ($data !== '[DONE]') {
                    yield $data;
                }
            }
        }
    }
    
    /**
     * Parse API response
     *
     * @param array $response Raw API response
     * @return CompletionResponse Parsed response
     */
    private function parseResponse(array $response): CompletionResponse
    {
        if (isset($response['output']['choices'])) {
            // DashScope format
            $choices = [];
            foreach ($response['output']['choices'] as $choice) {
                $choices[] = [
                    'index' => $choice['message']['role'] === 'assistant' ? 0 : -1,
                    'message' => [
                        'role' => $choice['message']['role'],
                        'content' => $choice['message']['content']
                    ],
                    'finish_reason' => $choice['finish_reason'] ?? 'stop'
                ];
            }
            
            $usage = $response['usage'] ?? [];
            
            // Track token usage
            if (!empty($usage['total_tokens'])) {
                $this->stats['tokens_used'] += $usage['total_tokens'];
            }
            
            return new CompletionResponse(
                id: $response['request_id'] ?? uniqid('qwen_'),
                object: 'chat.completion',
                created: time(),
                model: $response['output']['model'] ?? $this->config['model'],
                choices: $choices,
                usage: $usage
            );
        }
        
        throw new McpModelException('Unexpected response format');
    }
    
    /**
     * Parse streaming response chunk
     *
     * @param string $chunk Raw chunk data
     * @return CompletionResponse|null Parsed chunk or null
     */
    private function parseStreamChunk(string $chunk): ?CompletionResponse
    {
        $data = json_decode($chunk, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        if (isset($data['output']['choices'][0]['delta']['content'])) {
            return CompletionResponse::createStreamChunk(
                id: $data['request_id'] ?? uniqid('qwen_'),
                model: $this->config['model'],
                content: $data['output']['choices'][0]['delta']['content'],
                finishReason: $data['output']['choices'][0]['finish_reason'] ?? null
            );
        }
        
        return null;
    }
}