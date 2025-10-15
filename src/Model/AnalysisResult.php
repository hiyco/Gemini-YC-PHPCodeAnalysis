<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Analysis Result Data Model
 */

namespace YcPca\Model;

use PhpParser\Node;

/**
 * Comprehensive analysis result containing AST and metadata
 * 
 * This class encapsulates all information from a PHP file analysis:
 * - Parsed AST nodes
 * - File context and metadata
 * - Performance metrics
 * - Error information
 */
class AnalysisResult
{
    public function __construct(
        private string $filePath,
        private array $ast,
        private FileContext $context,
        private float $parseTime = 0.0,
        private int $memoryUsage = 0,
        private bool $hasErrors = false,
        private array $errors = [],
        private array $warnings = [],
        private array $metadata = []
    ) {}

    /**
     * Get the file path that was analyzed
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get the parsed AST nodes
     * @return Node[]
     */
    public function getAst(): array
    {
        return $this->ast;
    }

    /**
     * Get file context information
     */
    public function getContext(): FileContext
    {
        return $this->context;
    }

    /**
     * Get parsing time in seconds
     */
    public function getParseTime(): float
    {
        return $this->parseTime;
    }

    /**
     * Get memory usage in bytes
     */
    public function getMemoryUsage(): int
    {
        return $this->memoryUsage;
    }

    /**
     * Check if parsing had errors
     */
    public function hasErrors(): bool
    {
        return $this->hasErrors;
    }

    /**
     * Get all error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all warning messages
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get additional metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Add metadata entry
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Add error message
     */
    public function addError(string $error): self
    {
        $this->errors[] = $error;
        $this->hasErrors = true;
        return $this;
    }

    /**
     * Add warning message
     */
    public function addWarning(string $warning): self
    {
        $this->warnings[] = $warning;
        return $this;
    }

    /**
     * Get analysis summary for reporting
     */
    public function getSummary(): array
    {
        return [
            'file_path' => $this->filePath,
            'node_count' => count($this->ast),
            'file_size' => $this->context->getSize(),
            'line_count' => count($this->context->getLines()),
            'parse_time' => $this->parseTime,
            'memory_usage' => $this->memoryUsage,
            'has_errors' => $this->hasErrors,
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings),
            'encoding' => $this->context->getEncoding()
        ];
    }

    /**
     * Check if analysis was successful
     */
    public function isSuccessful(): bool
    {
        return !$this->hasErrors && !empty($this->ast);
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'ast' => $this->serializeAst(),
            'context' => $this->context->toArray(),
            'parse_time' => $this->parseTime,
            'memory_usage' => $this->memoryUsage,
            'has_errors' => $this->hasErrors,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
            'summary' => $this->getSummary()
        ];
    }

    /**
     * Serialize AST nodes for storage/transmission
     */
    private function serializeAst(): array
    {
        return array_map(function ($node) {
            if ($node instanceof Node) {
                return [
                    'type' => $node->getType(),
                    'line' => $node->getStartLine(),
                    'attributes' => $node->getAttributes()
                ];
            }
            return $node;
        }, $this->ast);
    }
}