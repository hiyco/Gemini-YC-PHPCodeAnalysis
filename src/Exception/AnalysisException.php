<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Analysis Exception for analysis engine errors
 */

namespace YcPca\Exception;

/**
 * Exception thrown during analysis operations
 * 
 * This exception provides detailed information about analysis failures:
 * - Analysis context
 * - Analyzer information
 * - Recovery suggestions
 */
class AnalysisException extends \Exception
{
    private ?string $analyzerName = null;
    private ?string $filePath = null;
    private array $context = [];

    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $analyzerName = null,
        ?string $filePath = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->analyzerName = $analyzerName;
        $this->filePath = $filePath;
        $this->context = $context;
    }

    /**
     * Get the analyzer name that caused the exception
     */
    public function getAnalyzerName(): ?string
    {
        return $this->analyzerName;
    }

    /**
     * Get the file path being analyzed when exception occurred
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * Get additional context information
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get detailed error information
     */
    public function getDetailedInfo(): array
    {
        return [
            'message' => $this->getMessage(),
            'analyzer_name' => $this->analyzerName,
            'file_path' => $this->filePath,
            'code' => $this->getCode(),
            'context' => $this->context,
            'trace' => $this->getTraceAsString()
        ];
    }

    /**
     * Create exception for analyzer execution failure
     */
    public static function analyzerFailed(
        string $analyzerName,
        string $filePath,
        string $reason,
        ?\Throwable $previous = null
    ): self {
        return new self(
            "Analyzer '{$analyzerName}' failed for file '{$filePath}': {$reason}",
            500,
            $previous,
            $analyzerName,
            $filePath,
            ['type' => 'analyzer_execution_failure']
        );
    }

    /**
     * Create exception for configuration error
     */
    public static function configurationError(string $message, array $context = []): self
    {
        return new self(
            "Configuration error: {$message}",
            400,
            null,
            null,
            null,
            array_merge(['type' => 'configuration_error'], $context)
        );
    }

    /**
     * Create exception for missing analyzer
     */
    public static function analyzerNotFound(string $analyzerName): self
    {
        return new self(
            "Analyzer not found: {$analyzerName}",
            404,
            null,
            $analyzerName,
            null,
            ['type' => 'analyzer_not_found']
        );
    }

    /**
     * Create exception for resource limits
     */
    public static function resourceLimitExceeded(string $resource, string $limit): self
    {
        return new self(
            "Resource limit exceeded: {$resource} limit is {$limit}",
            429,
            null,
            null,
            null,
            ['type' => 'resource_limit_exceeded', 'resource' => $resource, 'limit' => $limit]
        );
    }

    /**
     * Create exception for timeout
     */
    public static function timeout(string $operation, int $timeoutSeconds): self
    {
        return new self(
            "Operation timeout: {$operation} exceeded {$timeoutSeconds} seconds",
            408,
            null,
            null,
            null,
            ['type' => 'timeout', 'operation' => $operation, 'timeout_seconds' => $timeoutSeconds]
        );
    }
}