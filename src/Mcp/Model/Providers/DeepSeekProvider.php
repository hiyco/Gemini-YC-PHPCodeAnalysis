<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: DeepSeek Model Provider
 */

declare(strict_types=1);

namespace YcPca\Mcp\Model\Providers;

use YcPca\Mcp\Model\ModelProviderInterface;
use YcPca\Mcp\Model\CompletionResponse;
use YcPca\Mcp\McpModelException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * DeepSeek Provider
 * 
 * Implements integration with DeepSeek's Chat and Code models.
 * Uses OpenAI-compatible API format.
 * 
 * @package YcPca\Mcp\Model\Providers
 * @author YC
 * @version 1.0.0
 */
class DeepSeekProvider implements ModelProviderInterface
{
    private const PROVIDER_NAME = 'deepseek';
    
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
            'base_url' => 'https://api.deepseek.com',
            'api_key' => null,
            'model' => 'deepseek-chat',
            'timeout' => 30,
            'max_retries' => 3,
            'temperature' => 1.0,
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
            'max_tokens' => 4096
        ], $config);
        
        $this->logger = $logger ?? new NullLogger();
        
        if (empty($this->config['api_key'])) {
            throw new McpModelException('DeepSeek API key is required');
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
            'deepseek-chat' => ['context' => 32768, 'max_tokens' => 4096],
            'deepseek-coder' => ['context' => 16384, 'max_tokens' => 4096]
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
            throw new McpModelException("DeepSeek API request failed: {$e->getMessage()}", 0, $e);
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
            throw new McpModelException("DeepSeek stream request failed: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function countTokens(string $text): int
    {
        // DeepSeek token counting approximation
        // Similar to GPT tokenization: ~4 characters per token on average
        return (int) ceil(strlen($text) / 4);
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
            $this->complete('Hello', ['max_tokens' => 10]);
            return true;
        } catch (\Exception $e) {
            $this->logger->warning('DeepSeek connection test failed', [
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
        
        // Add optional parameters
        if (isset($options['frequency_penalty']) || $this->config['frequency_penalty']) {
            $data['frequency_penalty'] = $options['frequency_penalty'] ?? $this->config['frequency_penalty'];
        }
        
        if (isset($options['presence_penalty']) || $this->config['presence_penalty']) {
            $data['presence_penalty'] = $options['presence_penalty'] ?? $this->config['presence_penalty'];
        }
        
        if (!empty($options['stop'])) {
            $data['stop'] = $options['stop'];
        }
        
        return $data;
    }
    
    /**
     * Make HTTP request to DeepSeek API
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     * @throws McpModelException
     */
    private function makeRequest(string $endpoint, array $data): array
    {
        $url = $this->config['base_url'] . '/v1' . $endpoint;
        
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
        
        // Check for API errors
        if (isset($decodedResponse['error'])) {
            throw new McpModelException(
                "DeepSeek API error: " . $decodedResponse['error']['message'],
                $decodedResponse['error']['code'] ?? 0
            );
        }
        
        return $decodedResponse;
    }
    
    /**
     * Make streaming request to DeepSeek API
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return \Generator Stream of response chunks
     * @throws McpModelException
     */
    private function makeStreamRequest(string $endpoint, array $data): \Generator
    {
        $url = $this->config['base_url'] . '/v1' . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['api_key'],
            'Accept: text/event-stream',
            'Cache-Control: no-cache',
            'User-Agent: YC-PCA-MCP-PHP/1.0'
        ];
        
        $ch = curl_init();
        
        $buffer = '';
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$buffer) {
                $buffer .= $chunk;
                
                // Process complete lines
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    
                    if (str_starts_with($line, 'data: ')) {
                        $data = substr($line, 6);
                        if (trim($data) !== '[DONE]' && !empty(trim($data))) {
                            echo $data . "\n"; // This will be captured by the generator
                        }
                    }
                }
                
                return strlen($chunk);
            },
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        // Start output buffering to capture the streamed data
        ob_start();
        curl_exec($ch);
        $streamContent = ob_get_contents();
        ob_end_clean();
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new McpModelException("HTTP error: {$httpCode}");
        }
        
        // Yield each chunk
        $chunks = explode("\n", trim($streamContent));
        foreach ($chunks as $chunk) {
            if (!empty($chunk)) {
                yield $chunk;
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
        // Track token usage
        if (isset($response['usage']['total_tokens'])) {
            $this->stats['tokens_used'] += $response['usage']['total_tokens'];
        }
        
        return CompletionResponse::fromArray($response);
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
        
        if (isset($data['choices'][0]['delta']['content'])) {
            return CompletionResponse::createStreamChunk(
                id: $data['id'] ?? uniqid('deepseek_'),
                model: $data['model'] ?? $this->config['model'],
                content: $data['choices'][0]['delta']['content'],
                finishReason: $data['choices'][0]['finish_reason'] ?? null
            );
        }
        
        return null;
    }
}