<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: Completion Response Data Structure
 */

declare(strict_types=1);

namespace YcPca\Mcp\Model;

/**
 * Completion Response
 * 
 * Represents a response from an AI model completion request.
 * Standardizes the response format across different providers.
 * 
 * @package YcPca\Mcp\Model
 * @author YC
 * @version 1.0.0
 */
class CompletionResponse
{
    private string $id;
    private string $object;
    private int $created;
    private string $model;
    private array $choices;
    private ?array $usage;
    private array $metadata;
    
    public function __construct(
        string $id,
        string $object,
        int $created,
        string $model,
        array $choices,
        ?array $usage = null,
        array $metadata = []
    ) {
        $this->id = $id;
        $this->object = $object;
        $this->created = $created;
        $this->model = $model;
        $this->choices = $choices;
        $this->usage = $usage;
        $this->metadata = $metadata;
    }
    
    /**
     * Get completion ID
     *
     * @return string Completion ID
     */
    public function getId(): string
    {
        return $this->id;
    }
    
    /**
     * Get object type
     *
     * @return string Object type
     */
    public function getObject(): string
    {
        return $this->object;
    }
    
    /**
     * Get creation timestamp
     *
     * @return int Creation timestamp
     */
    public function getCreated(): int
    {
        return $this->created;
    }
    
    /**
     * Get model name
     *
     * @return string Model name
     */
    public function getModel(): string
    {
        return $this->model;
    }
    
    /**
     * Get all choices
     *
     * @return array All choices
     */
    public function getChoices(): array
    {
        return $this->choices;
    }
    
    /**
     * Get first choice
     *
     * @return array|null First choice or null
     */
    public function getFirstChoice(): ?array
    {
        return $this->choices[0] ?? null;
    }
    
    /**
     * Get completion text from first choice
     *
     * @return string Completion text
     */
    public function getContent(): string
    {
        $choice = $this->getFirstChoice();
        
        if (!$choice) {
            return '';
        }
        
        // Handle different response formats
        if (isset($choice['text'])) {
            return $choice['text'];
        }
        
        if (isset($choice['message']['content'])) {
            return $choice['message']['content'];
        }
        
        if (isset($choice['delta']['content'])) {
            return $choice['delta']['content'];
        }
        
        return '';
    }
    
    /**
     * Get finish reason from first choice
     *
     * @return string|null Finish reason
     */
    public function getFinishReason(): ?string
    {
        $choice = $this->getFirstChoice();
        return $choice['finish_reason'] ?? null;
    }
    
    /**
     * Get usage information
     *
     * @return array|null Usage information
     */
    public function getUsage(): ?array
    {
        return $this->usage;
    }
    
    /**
     * Get prompt tokens
     *
     * @return int|null Prompt tokens
     */
    public function getPromptTokens(): ?int
    {
        return $this->usage['prompt_tokens'] ?? null;
    }
    
    /**
     * Get completion tokens
     *
     * @return int|null Completion tokens
     */
    public function getCompletionTokens(): ?int
    {
        return $this->usage['completion_tokens'] ?? null;
    }
    
    /**
     * Get total tokens
     *
     * @return int|null Total tokens
     */
    public function getTotalTokens(): ?int
    {
        return $this->usage['total_tokens'] ?? null;
    }
    
    /**
     * Get metadata
     *
     * @return array Metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    /**
     * Get specific metadata value
     *
     * @param string $key Metadata key
     * @param mixed $default Default value
     * @return mixed Metadata value
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
    
    /**
     * Check if completion was successful
     *
     * @return bool True if successful
     */
    public function isSuccessful(): bool
    {
        return !empty($this->choices) && $this->getContent() !== '';
    }
    
    /**
     * Check if completion was truncated
     *
     * @return bool True if truncated
     */
    public function isTruncated(): bool
    {
        $finishReason = $this->getFinishReason();
        return in_array($finishReason, ['length', 'max_tokens', 'truncated']);
    }
    
    /**
     * Convert to array
     *
     * @return array Array representation
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'object' => $this->object,
            'created' => $this->created,
            'model' => $this->model,
            'choices' => $this->choices,
            'usage' => $this->usage,
            'metadata' => $this->metadata
        ];
    }
    
    /**
     * Create from array
     *
     * @param array $data Array data
     * @return self CompletionResponse instance
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            object: $data['object'] ?? 'text_completion',
            created: $data['created'] ?? time(),
            model: $data['model'] ?? '',
            choices: $data['choices'] ?? [],
            usage: $data['usage'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }
    
    /**
     * Create streaming chunk response
     *
     * @param string $id Completion ID
     * @param string $model Model name
     * @param string $content Content chunk
     * @param string|null $finishReason Finish reason
     * @param array $metadata Additional metadata
     * @return self Streaming response
     */
    public static function createStreamChunk(
        string $id,
        string $model,
        string $content,
        ?string $finishReason = null,
        array $metadata = []
    ): self {
        $choice = [
            'index' => 0,
            'delta' => [
                'content' => $content
            ],
            'finish_reason' => $finishReason
        ];
        
        return new self(
            id: $id,
            object: 'text_completion.chunk',
            created: time(),
            model: $model,
            choices: [$choice],
            usage: null,
            metadata: $metadata
        );
    }
    
    /**
     * Create chat completion response
     *
     * @param string $id Completion ID
     * @param string $model Model name
     * @param string $content Message content
     * @param string $role Message role
     * @param array $usage Usage information
     * @param array $metadata Additional metadata
     * @return self Chat completion response
     */
    public static function createChatCompletion(
        string $id,
        string $model,
        string $content,
        string $role = 'assistant',
        array $usage = [],
        array $metadata = []
    ): self {
        $choice = [
            'index' => 0,
            'message' => [
                'role' => $role,
                'content' => $content
            ],
            'finish_reason' => 'stop'
        ];
        
        return new self(
            id: $id,
            object: 'chat.completion',
            created: time(),
            model: $model,
            choices: [$choice],
            usage: $usage,
            metadata: $metadata
        );
    }
}