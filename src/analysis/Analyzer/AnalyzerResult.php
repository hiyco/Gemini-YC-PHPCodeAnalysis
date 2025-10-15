<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Analyzer Result containing issues and metadata
 */

namespace YcPca\Analysis\Analyzer;

use YcPca\Analysis\Issue\Issue;

/**
 * Result from an analyzer execution
 * 
 * Contains:
 * - Found issues
 * - Execution metadata
 * - Performance metrics
 * - Error information
 */
class AnalyzerResult
{
    private \DateTime $createdAt;

    public function __construct(
        private string $analyzerName,
        private string $filePath,
        private array $issues = [],
        private array $metadata = [],
        private float $executionTime = 0.0,
        private int $memoryUsage = 0,
        private bool $hasErrors = false,
        private array $errors = [],
        private array $warnings = []
    ) {
        $this->createdAt = new \DateTime();
    }

    /**
     * Get analyzer name that produced this result
     */
    public function getAnalyzerName(): string
    {
        return $this->analyzerName;
    }

    /**
     * Get analyzed file path
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get all found issues
     * @return Issue[]
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * Add an issue to the result
     */
    public function addIssue(Issue $issue): self
    {
        $this->issues[] = $issue;
        return $this;
    }

    /**
     * Get issues filtered by severity
     */
    public function getIssuesBySeverity(string $severity): array
    {
        return array_filter($this->issues, fn(Issue $issue) => $issue->getSeverity() === $severity);
    }

    /**
     * Get issues filtered by category
     */
    public function getIssuesByCategory(string $category): array
    {
        return array_filter($this->issues, fn(Issue $issue) => $issue->getCategory() === $category);
    }

    /**
     * Get total issue count
     */
    public function getIssueCount(): int
    {
        return count($this->issues);
    }

    /**
     * Get critical issues count
     */
    public function getCriticalIssuesCount(): int
    {
        return count($this->getIssuesBySeverity('critical'));
    }

    /**
     * Get high severity issues count
     */
    public function getHighIssuesCount(): int
    {
        return count($this->getIssuesBySeverity('high'));
    }

    /**
     * Get medium severity issues count
     */
    public function getMediumIssuesCount(): int
    {
        return count($this->getIssuesBySeverity('medium'));
    }

    /**
     * Get low severity issues count
     */
    public function getLowIssuesCount(): int
    {
        return count($this->getIssuesBySeverity('low'));
    }

    /**
     * Get metadata
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
     * Get specific metadata value
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get execution time in seconds
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * Set execution time
     */
    public function setExecutionTime(float $time): self
    {
        $this->executionTime = $time;
        return $this;
    }

    /**
     * Get memory usage in bytes
     */
    public function getMemoryUsage(): int
    {
        return $this->memoryUsage;
    }

    /**
     * Set memory usage
     */
    public function setMemoryUsage(int $bytes): self
    {
        $this->memoryUsage = $bytes;
        return $this;
    }

    /**
     * Check if execution had errors
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
     * Add error message
     */
    public function addError(string $error): self
    {
        $this->errors[] = $error;
        $this->hasErrors = true;
        return $this;
    }

    /**
     * Get all warning messages
     */
    public function getWarnings(): array
    {
        return $this->warnings;
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
     * Get creation timestamp
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Check if analysis was successful
     */
    public function isSuccessful(): bool
    {
        return !$this->hasErrors;
    }

    /**
     * Get result summary
     */
    public function getSummary(): array
    {
        return [
            'analyzer' => $this->analyzerName,
            'file' => $this->filePath,
            'total_issues' => $this->getIssueCount(),
            'critical_issues' => $this->getCriticalIssuesCount(),
            'high_issues' => $this->getHighIssuesCount(),
            'medium_issues' => $this->getMediumIssuesCount(),
            'low_issues' => $this->getLowIssuesCount(),
            'execution_time' => $this->executionTime,
            'memory_usage' => $this->memoryUsage,
            'has_errors' => $this->hasErrors,
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'analyzer_name' => $this->analyzerName,
            'file_path' => $this->filePath,
            'issues' => array_map(fn(Issue $issue) => $issue->toArray(), $this->issues),
            'metadata' => $this->metadata,
            'execution_time' => $this->executionTime,
            'memory_usage' => $this->memoryUsage,
            'has_errors' => $this->hasErrors,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'created_at' => $this->createdAt->format('c'),
            'summary' => $this->getSummary()
        ];
    }
}