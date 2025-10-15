<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: File Context Information Model
 */

namespace YcPca\Model;

/**
 * File Context containing metadata and content information
 * 
 * This class stores comprehensive information about analyzed files:
 * - File system metadata
 * - Content structure
 * - Processing statistics
 */
class FileContext
{
    private \DateTime $createdAt;
    private array $statistics;

    public function __construct(
        private string $filePath,
        private string $content,
        private array $lines,
        private int $size,
        private int $nodeCount,
        private string $encoding = 'UTF-8'
    ) {
        $this->createdAt = new \DateTime();
        $this->statistics = $this->calculateStatistics();
    }

    /**
     * Get the full file path
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get the file content
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get content lines array
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * Get file size in bytes
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get AST node count
     */
    public function getNodeCount(): int
    {
        return $this->nodeCount;
    }

    /**
     * Get file encoding
     */
    public function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * Get creation timestamp
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Get file basename
     */
    public function getBasename(): string
    {
        return basename($this->filePath);
    }

    /**
     * Get file directory
     */
    public function getDirectory(): string
    {
        return dirname($this->filePath);
    }

    /**
     * Get file extension
     */
    public function getExtension(): string
    {
        return pathinfo($this->filePath, PATHINFO_EXTENSION);
    }

    /**
     * Get specific line content
     */
    public function getLine(int $lineNumber): ?string
    {
        $index = $lineNumber - 1; // Convert to 0-based index
        return $this->lines[$index] ?? null;
    }

    /**
     * Get line range content
     */
    public function getLineRange(int $startLine, int $endLine): array
    {
        $startIndex = max(0, $startLine - 1);
        $length = $endLine - $startLine + 1;
        
        return array_slice($this->lines, $startIndex, $length);
    }

    /**
     * Get file statistics
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * Check if file is likely a test file
     */
    public function isTestFile(): bool
    {
        $basename = strtolower($this->getBasename());
        $directory = strtolower($this->getDirectory());
        
        return str_contains($basename, 'test') ||
               str_contains($directory, 'test') ||
               str_contains($directory, 'spec') ||
               str_ends_with($basename, 'test.php') ||
               str_ends_with($basename, '.test.php');
    }

    /**
     * Check if file is in vendor directory
     */
    public function isVendorFile(): bool
    {
        return str_contains($this->filePath, '/vendor/') ||
               str_contains($this->filePath, '\\vendor\\');
    }

    /**
     * Get relative path from given base directory
     */
    public function getRelativePath(string $basePath): string
    {
        $basePath = rtrim($basePath, '/\\');
        
        if (str_starts_with($this->filePath, $basePath)) {
            return ltrim(substr($this->filePath, strlen($basePath)), '/\\');
        }
        
        return $this->filePath;
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'basename' => $this->getBasename(),
            'directory' => $this->getDirectory(),
            'extension' => $this->getExtension(),
            'size' => $this->size,
            'line_count' => count($this->lines),
            'node_count' => $this->nodeCount,
            'encoding' => $this->encoding,
            'is_test_file' => $this->isTestFile(),
            'is_vendor_file' => $this->isVendorFile(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'statistics' => $this->statistics
        ];
    }

    /**
     * Calculate file content statistics
     */
    private function calculateStatistics(): array
    {
        $nonEmptyLines = array_filter($this->lines, fn($line) => trim($line) !== '');
        $commentLines = array_filter($this->lines, fn($line) => $this->isCommentLine($line));
        
        return [
            'total_lines' => count($this->lines),
            'non_empty_lines' => count($nonEmptyLines),
            'comment_lines' => count($commentLines),
            'code_lines' => count($nonEmptyLines) - count($commentLines),
            'avg_line_length' => $this->calculateAverageLineLength(),
            'max_line_length' => $this->findMaxLineLength(),
            'complexity_estimate' => $this->estimateComplexity()
        ];
    }

    /**
     * Check if line is primarily a comment
     */
    private function isCommentLine(string $line): bool
    {
        $trimmed = trim($line);
        
        return str_starts_with($trimmed, '//') ||
               str_starts_with($trimmed, '#') ||
               str_starts_with($trimmed, '/*') ||
               str_starts_with($trimmed, '*') ||
               str_starts_with($trimmed, '*/');
    }

    /**
     * Calculate average line length
     */
    private function calculateAverageLineLength(): float
    {
        if (empty($this->lines)) {
            return 0.0;
        }
        
        $totalLength = array_sum(array_map('strlen', $this->lines));
        return $totalLength / count($this->lines);
    }

    /**
     * Find maximum line length
     */
    private function findMaxLineLength(): int
    {
        return empty($this->lines) ? 0 : max(array_map('strlen', $this->lines));
    }

    /**
     * Estimate code complexity based on keywords
     */
    private function estimateComplexity(): int
    {
        $complexityKeywords = [
            'if', 'else', 'elseif', 'switch', 'case', 'default',
            'for', 'foreach', 'while', 'do',
            'try', 'catch', 'finally', 'throw',
            'function', 'class', 'interface', 'trait',
            '?', ':', '&&', '||'
        ];
        
        $content = strtolower($this->content);
        $complexity = 0;
        
        foreach ($complexityKeywords as $keyword) {
            $complexity += substr_count($content, $keyword);
        }
        
        return $complexity;
    }
}