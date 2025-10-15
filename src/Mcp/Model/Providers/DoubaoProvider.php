<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: ByteDance Doubao Model Provider
 */

declare(strict_types=1);

namespace YcPca\Mcp\Model\Providers;

use YcPca\Mcp\Model\ModelProviderInterface;
use YcPca\Mcp\Model\CompletionResponse;
use YcPca\Mcp\McpModelException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * ByteDance Doubao Provider
 * 
 * Implements integration with ByteDance's Doubao models
 * through the Volcano Engine (Ark) API.
 * 
 * @package YcPca\Mcp\Model\Providers
 * @author YC
 * @version 1.0.0
 */
class DoubaoProvider implements ModelProviderInterface
{
    private const PROVIDER_NAME = 'doubao';
    
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
            'base_url' => 'https://ark.cn-beijing.volces.com/api/v3',
            'api_key' => null,
            'model' => 'doubao-lite-4k',
            'timeout' => 30,
            'max_retries' => 3,
            'temperature' => 0.9,
            'top_p' => 0.7,
            'max_tokens' => 4096
        ], $config);
        
        $this->logger = $logger ?? new NullLogger();
        
        if (empty($this->config['api_key'])) {
            throw new McpModelException('Doubao API key is required');
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
            'doubao-lite-4k' => ['context' => 4096, 'max_tokens' => 4096],
            'doubao-lite-32k' => ['context' => 32768, 'max_tokens' => 4096],
            'doubao-pro-4k' => ['context' => 4096, 'max_tokens' => 4096],
            'doubao-pro-32k' => ['context' => 32768, 'max_tokens' => 4096],
            'doubao-pro-128k' => ['context' => 128000, 'max_tokens' => 4096]
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
            throw new McpModelException("Doubao API request failed: {$e->getMessage()}", 0, $e);
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
            throw new McpModelException("Doubao stream request failed: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function countTokens(string $text): int
    {
        // Doubao token counting approximation
        // Similar to other Chinese models: mixed Chinese/English
        $chineseChars = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text);
        $englishWords = str_word_count(preg_replace('/[\x{4e00}-\x{9fff}]/u', '', $text));
        
        return (int) ($chineseChars * 1.6 + $englishWords * 1.2);
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
            $this->complete('测试', ['max_tokens' => 10]);
            return true;
        } catch (\Exception $e) {
            $this->logger->warning('Doubao connection test failed', [
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
            'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'],
            'stream' => $options['stream'] ?? false
        ];
        
        if (!empty($options['stop'])) {
            $data['stop'] = $options['stop'];
        }
        
        return $data;
    }
    
    /**
     * Make HTTP request to Doubao API
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
            'User-Agent: YC-PCA-MCP-PHP/1.0'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => true
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
     * Make streaming request (simplified implementation)
     */
    private function makeStreamRequest(string $endpoint, array $data): \Generator
    {
        // Simplified streaming implementation
        $response = $this->makeRequest($endpoint, $data);
        yield json_encode($response);
    }
    
    /**
     * Parse API response
     *
     * @param array $response Raw API response
     * @return CompletionResponse Parsed response
     */
    private function parseResponse(array $response): CompletionResponse
    {
        // Track token usage
        if (isset($response['usage']['total_tokens'])) {
            $this->stats['tokens_used'] += $response['usage']['total_tokens'];
        }
        
        return CompletionResponse::fromArray($response);
    }
    
    /**
     * Parse streaming response chunk
     */
    private function parseStreamChunk(string $chunk): ?CompletionResponse
    {
        $data = json_decode($chunk, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        if (isset($data['choices'][0])) {
            $choice = $data['choices'][0];
            $content = $choice['delta']['content'] ?? $choice['message']['content'] ?? '';
            
            return CompletionResponse::createStreamChunk(
                id: $data['id'] ?? uniqid('doubao_'),
                model: $data['model'] ?? $this->config['model'],
                content: $content,
                finishReason: $choice['finish_reason'] ?? null
            );
        }
        
        return null;
    }
}