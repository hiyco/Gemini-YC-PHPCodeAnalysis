<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: Model Provider Interface for AI Integration
 */

declare(strict_types=1);

namespace YcPca\Mcp\Model;

use YcPca\Mcp\McpModelException;

/**
 * Model Provider Interface
 * 
 * Defines the contract for AI model providers.
 * Supports various providers like QWEN, DeepSeek, Doubao, ERNIE, etc.
 * 
 * @package YcPca\Mcp\Model
 * @author YC
 * @version 1.0.0
 */
interface ModelProviderInterface
{
    /**
     * Get provider name
     *
     * @return string Provider name
     */
    public function getName(): string;
    
    /**
     * Get supported models
     *
     * @return array List of supported models
     */
    public function getSupportedModels(): array;
    
    /**
     * Generate completion
     *
     * @param string $prompt Input prompt
     * @param array $options Generation options
     * @return CompletionResponse Completion response
     * @throws McpModelException
     */
    public function complete(string $prompt, array $options = []): CompletionResponse;
    
    /**
     * Generate chat completion
     *
     * @param array $messages Chat messages
     * @param array $options Generation options
     * @return CompletionResponse Chat completion response
     * @throws McpModelException
     */
    public function chat(array $messages, array $options = []): CompletionResponse;
    
    /**
     * Stream completion
     *
     * @param string $prompt Input prompt
     * @param array $options Generation options
     * @return \Generator Stream of completion chunks
     * @throws McpModelException
     */
    public function streamComplete(string $prompt, array $options = []): \Generator;
    
    /**
     * Stream chat completion
     *
     * @param array $messages Chat messages
     * @param array $options Generation options
     * @return \Generator Stream of completion chunks
     * @throws McpModelException
     */
    public function streamChat(array $messages, array $options = []): \Generator;
    
    /**
     * Count tokens in text
     *
     * @param string $text Text to count tokens for
     * @return int Token count
     */
    public function countTokens(string $text): int;
    
    /**
     * Get model information
     *
     * @param string $model Model name
     * @return array Model information
     */
    public function getModelInfo(string $model): array;
    
    /**
     * Test connection to provider
     *
     * @return bool Connection status
     */
    public function testConnection(): bool;
    
    /**
     * Get provider statistics
     *
     * @return array Provider statistics
     */
    public function getStats(): array;
}