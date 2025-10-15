<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: Baidu ERNIE Model Provider
 */

declare(strict_types=1);

namespace YcPca\Mcp\Model\Providers;

use YcPca\Mcp\Model\ModelProviderInterface;
use YcPca\Mcp\Model\CompletionResponse;
use YcPca\Mcp\McpModelException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Baidu ERNIE Provider
 * 
 * Implements integration with Baidu's ERNIE (Wenxin Yiyan) models.
 * Uses Baidu's custom API format with OAuth authentication.
 * 
 * @package YcPca\Mcp\Model\Providers
 * @author YC
 * @version 1.0.0
 */
class ErnieProvider implements ModelProviderInterface
{
    private const PROVIDER_NAME = 'ernie';
    
    private LoggerInterface $logger;
    private array $config;
    private ?string $accessToken = null;
    private ?int $tokenExpiry = null;
    private array $stats = [
        'requests_sent' => 0,
        'tokens_used' => 0,
        'errors' => 0,
        'last_request' => null,
        'token_refreshes' => 0
    ];
    
    public function __construct(array $config, ?LoggerInterface $logger = null)
    {
        $this->config = array_merge([
            'base_url' => 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat',
            'auth_url' => 'https://aip.baidubce.com/oauth/2.0/token',
            'api_key' => null,
            'secret_key' => null,
            'model' => 'ernie-bot-turbo',
            'timeout' => 30,
            'max_retries' => 3,
            'temperature' => 0.8,
            'top_p' => 0.8,
            'penalty_score' => 1.0
        ], $config);
        
        $this->logger = $logger ?? new NullLogger();
        
        if (empty($this->config['api_key']) || empty($this->config['secret_key'])) {
            throw new McpModelException('ERNIE API key and secret key are both required');
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
            'ernie-bot-turbo' => ['context' => 8192, 'max_tokens' => 1024, 'endpoint' => 'eb-instant'],
            'ernie-bot' => ['context' => 8192, 'max_tokens' => 1024, 'endpoint' => 'completions'],
            'ernie-bot-4' => ['context' => 8192, 'max_tokens' => 1024, 'endpoint' => 'completions_pro'],
            'ernie-3.5' => ['context' => 8192, 'max_tokens' => 1024, 'endpoint' => 'completions']
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
        $this->ensureAccessToken();
        $requestData = $this->buildChatRequest($messages, $options);
        $endpoint = $this->getModelEndpoint($options['model'] ?? $this->config['model']);
        
        try {
            $response = $this->makeRequest($endpoint, $requestData);
            $this->stats['requests_sent']++;
            $this->stats['last_request'] = time();
            
            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new McpModelException("ERNIE API request failed: {$e->getMessage()}", 0, $e);
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
        $this->ensureAccessToken();
        $requestData = $this->buildChatRequest($messages, $options);
        $endpoint = $this->getModelEndpoint($options['model'] ?? $this->config['model']);
        
        try {
            $stream = $this->makeStreamRequest($endpoint, $requestData);
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
            throw new McpModelException("ERNIE stream request failed: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function countTokens(string $text): int
    {
        // ERNIE token counting approximation
        // Chinese-focused model: Chinese characters count higher
        $chineseChars = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text);
        $englishWords = str_word_count(preg_replace('/[\x{4e00}-\x{9fff}]/u', '', $text));
        
        return (int) ($chineseChars * 1.8 + $englishWords * 1.1);
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
            $this->complete('你好', ['max_tokens' => 10]);
            return true;
        } catch (\Exception $e) {
            $this->logger->warning('ERNIE connection test failed', [
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
     * Get model endpoint
     *
     * @param string $model Model name
     * @return string Endpoint path
     */
    private function getModelEndpoint(string $model): string
    {
        $models = $this->getSupportedModels();
        $modelInfo = $models[$model] ?? $models['ernie-bot-turbo'];
        return '/' . $modelInfo['endpoint'];
    }
    
    /**
     * Ensure valid access token
     *
     * @throws McpModelException
     */
    private function ensureAccessToken(): void
    {
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry - 300) {
            return; // Token is still valid (with 5-minute buffer)
        }
        
        $this->refreshAccessToken();
    }
    
    /**
     * Refresh access token using OAuth
     *
     * @throws McpModelException
     */
    private function refreshAccessToken(): void
    {
        $url = $this->config['auth_url'];
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['api_key'],
            'client_secret' => $this->config['secret_key']
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: YC-PCA-MCP-PHP/1.0'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new McpModelException("cURL error during token refresh: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new McpModelException("Token refresh failed with HTTP {$httpCode}: {$response}");
        }
        
        $tokenData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new McpModelException("Invalid JSON in token response: " . json_last_error_msg());
        }
        
        if (!isset($tokenData['access_token'])) {
            throw new McpModelException("No access token in response: " . ($tokenData['error_description'] ?? 'Unknown error'));
        }
        
        $this->accessToken = $tokenData['access_token'];
        $this->tokenExpiry = time() + ($tokenData['expires_in'] ?? 2592000); // Default 30 days
        $this->stats['token_refreshes']++;
        
        $this->logger->info('ERNIE access token refreshed', [
            'expires_in' => $tokenData['expires_in'] ?? 'unknown'
        ]);
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
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $this->config['temperature'],
            'top_p' => $options['top_p'] ?? $this->config['top_p'],
            'penalty_score' => $options['penalty_score'] ?? $this->config['penalty_score'],
            'stream' => $options['stream'] ?? false
        ];
        
        if (!empty($options['stop'])) {
            $data['stop'] = $options['stop'];
        }
        
        if (!empty($options['user_id'])) {
            $data['user_id'] = $options['user_id'];
        }
        
        return $data;
    }
    
    /**
     * Make HTTP request to ERNIE API
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     * @throws McpModelException
     */
    private function makeRequest(string $endpoint, array $data): array
    {
        $url = $this->config['base_url'] . $endpoint . '?access_token=' . $this->accessToken;
        
        $headers = [
            'Content-Type: application/json',
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
        
        // Check for ERNIE API errors
        if (isset($decodedResponse['error_code'])) {
            throw new McpModelException(
                "ERNIE API error: " . $decodedResponse['error_msg'],
                $decodedResponse['error_code']
            );
        }
        
        return $decodedResponse;
    }
    
    /**
     * Make streaming request (simplified implementation)
     */
    private function makeStreamRequest(string $endpoint, array $data): \Generator
    {
        // ERNIE streaming implementation would be more complex
        // For now, return non-streaming response as a single chunk
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
        // Convert ERNIE response format to standard format
        $choices = [];
        
        if (isset($response['result'])) {
            $choices[] = [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => $response['result']
                ],
                'finish_reason' => $response['is_truncated'] ? 'length' : 'stop'
            ];
        }
        
        $usage = [];
        if (isset($response['usage'])) {
            $usage = [
                'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $response['usage']['total_tokens'] ?? 0
            ];
            
            $this->stats['tokens_used'] += $usage['total_tokens'];
        }
        
        return new CompletionResponse(
            id: $response['id'] ?? uniqid('ernie_'),
            object: 'chat.completion',
            created: $response['created'] ?? time(),
            model: $this->config['model'],
            choices: $choices,
            usage: $usage
        );
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
        
        if (isset($data['result'])) {
            return CompletionResponse::createStreamChunk(
                id: $data['id'] ?? uniqid('ernie_'),
                model: $this->config['model'],
                content: $data['result'],
                finishReason: $data['is_truncated'] ? 'length' : 'stop'
            );
        }
        
        return null;
    }
}