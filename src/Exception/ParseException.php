<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Parse Exception for AST parsing errors
 */

namespace YcPca\Exception;

/**
 * Exception thrown when PHP code parsing fails
 * 
 * This exception provides detailed information about parsing failures:
 * - Original error context
 * - File and line information
 * - Recovery suggestions
 */
class ParseException extends \Exception
{
    private ?string $filePath = null;
    private ?int $line = null;
    private array $context = [];

    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $filePath = null,
        ?int $line = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->filePath = $filePath;
        $this->line = $line;
        $this->context = $context;
    }

    /**
     * Get the file path where parsing failed
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * Get the line number where parsing failed
     */
    public function getErrorLine(): ?int
    {
        return $this->line;
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
            'file_path' => $this->filePath,
            'line' => $this->line,
            'code' => $this->getCode(),
            'context' => $this->context,
            'trace' => $this->getTraceAsString()
        ];
    }

    /**
     * Create exception for file not found
     */
    public static function fileNotFound(string $filePath): self
    {
        return new self(
            "File not found: {$filePath}",
            404,
            null,
            $filePath
        );
    }

    /**
     * Create exception for syntax error
     */
    public static function syntaxError(string $message, string $filePath, int $line): self
    {
        return new self(
            "Syntax error in {$filePath} on line {$line}: {$message}",
            422,
            null,
            $filePath,
            $line,
            ['type' => 'syntax_error']
        );
    }

    /**
     * Create exception for encoding error
     */
    public static function encodingError(string $filePath, string $encoding): self
    {
        return new self(
            "Encoding error in {$filePath}: Invalid {$encoding} encoding",
            400,
            null,
            $filePath,
            null,
            ['type' => 'encoding_error', 'encoding' => $encoding]
        );
    }
}