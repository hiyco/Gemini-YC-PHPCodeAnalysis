<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Comprehensive Analysis Report aggregating all analyzer results
 */

namespace YcPca\Analysis\Report;

use YcPca\Analysis\Analyzer\AnalyzerResult;
use YcPca\Analysis\Issue\Issue;
use YcPca\Model\AnalysisResult;

/**
 * Comprehensive report containing all analysis results
 * 
 * Features:
 * - Multi-analyzer result aggregation
 * - Issue categorization and prioritization
 * - Summary statistics
 * - Export capabilities
 */
class AnalysisReport
{
    private \DateTime $createdAt;
    private ?AnalysisResult $parseResult = null;
    
    /** @var AnalyzerResult[] */
    private array $analyzerResults = [];
    
    /** @var Issue[] */
    private array $allIssues = [];
    
    private array $summary = [];

    public function __construct(
        private string $filePath,
        array $analyzerResults = []
    ) {
        $this->createdAt = new \DateTime();
        
        foreach ($analyzerResults as $result) {
            $this->addAnalyzerResult($result);
        }
        
        $this->calculateSummary();
    }

    /**
     * Get analyzed file path
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Set parse result
     */
    public function setParseResult(AnalysisResult $parseResult): self
    {
        $this->parseResult = $parseResult;
        return $this;
    }

    /**
     * Get parse result
     */
    public function getParseResult(): ?AnalysisResult
    {
        return $this->parseResult;
    }

    /**
     * Add analyzer result
     */
    public function addAnalyzerResult(AnalyzerResult $result): self
    {
        $this->analyzerResults[$result->getAnalyzerName()] = $result;
        
        // Add all issues to master list
        foreach ($result->getIssues() as $issue) {
            $this->allIssues[] = $issue;
        }
        
        $this->calculateSummary();
        
        return $this;
    }

    /**
     * Get all analyzer results
     */
    public function getAnalyzerResults(): array
    {
        return $this->analyzerResults;
    }

    /**
     * Get result from specific analyzer
     */
    public function getAnalyzerResult(string $analyzerName): ?AnalyzerResult
    {
        return $this->analyzerResults[$analyzerName] ?? null;
    }

    /**
     * Get all issues across all analyzers
     */
    public function getAllIssues(): array
    {
        return $this->allIssues;
    }

    /**
     * Get issues by severity
     */
    public function getIssuesBySeverity(string $severity): array
    {
        return array_filter($this->allIssues, fn(Issue $issue) => $issue->getSeverity() === $severity);
    }

    /**
     * Get issues by category
     */
    public function getIssuesByCategory(string $category): array
    {
        return array_filter($this->allIssues, fn(Issue $issue) => $issue->getCategory() === $category);
    }

    /**
     * Get critical issues
     */
    public function getCriticalIssues(): array
    {
        return $this->getIssuesBySeverity(Issue::SEVERITY_CRITICAL);
    }

    /**
     * Get security issues
     */
    public function getSecurityIssues(): array
    {
        return $this->getIssuesByCategory(Issue::CATEGORY_SECURITY);
    }

    /**
     * Get performance issues
     */
    public function getPerformanceIssues(): array
    {
        return $this->getIssuesByCategory(Issue::CATEGORY_PERFORMANCE);
    }

    /**
     * Get total issue count
     */
    public function getTotalIssueCount(): int
    {
        return count($this->allIssues);
    }

    /**
     * Get critical issue count
     */
    public function getCriticalIssueCount(): int
    {
        return count($this->getCriticalIssues());
    }

    /**
     * Get high severity issue count
     */
    public function getHighIssueCount(): int
    {
        return count($this->getIssuesBySeverity(Issue::SEVERITY_HIGH));
    }

    /**
     * Get medium severity issue count
     */
    public function getMediumIssueCount(): int
    {
        return count($this->getIssuesBySeverity(Issue::SEVERITY_MEDIUM));
    }

    /**
     * Get low severity issue count
     */
    public function getLowIssueCount(): int
    {
        return count($this->getIssuesBySeverity(Issue::SEVERITY_LOW));
    }

    /**
     * Get security issue count
     */
    public function getSecurityIssueCount(): int
    {
        return count($this->getSecurityIssues());
    }

    /**
     * Get performance issue count
     */
    public function getPerformanceIssueCount(): int
    {
        return count($this->getPerformanceIssues());
    }

    /**
     * Get issues sorted by priority
     */
    public function getIssuesByPriority(): array
    {
        $issues = $this->allIssues;
        
        usort($issues, function (Issue $a, Issue $b) {
            // Sort by severity first, then by line number
            $severityDiff = $b->getSeverityPriority() - $a->getSeverityPriority();
            
            if ($severityDiff !== 0) {
                return $severityDiff;
            }
            
            return ($a->getLine() ?? 0) - ($b->getLine() ?? 0);
        });
        
        return $issues;
    }

    /**
     * Get report summary
     */
    public function getSummary(): array
    {
        return $this->summary;
    }

    /**
     * Check if analysis was successful
     */
    public function isSuccessful(): bool
    {
        foreach ($this->analyzerResults as $result) {
            if ($result->hasErrors()) {
                return false;
            }
        }
        
        return $this->parseResult === null || $this->parseResult->isSuccessful();
    }

    /**
     * Check if there are critical issues
     */
    public function hasCriticalIssues(): bool
    {
        return $this->getCriticalIssueCount() > 0;
    }

    /**
     * Check if there are security issues
     */
    public function hasSecurityIssues(): bool
    {
        return $this->getSecurityIssueCount() > 0;
    }

    /**
     * Get creation timestamp
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Get quality score (0-100)
     */
    public function getQualityScore(): int
    {
        $totalIssues = $this->getTotalIssueCount();
        
        if ($totalIssues === 0) {
            return 100;
        }
        
        // Calculate weighted penalty
        $penalty = 
            ($this->getCriticalIssueCount() * 20) +
            ($this->getHighIssueCount() * 10) +
            ($this->getMediumIssueCount() * 5) +
            ($this->getLowIssueCount() * 1);
        
        // Base score calculation
        $score = max(0, 100 - $penalty);
        
        // Additional penalties
        if ($this->parseResult && $this->parseResult->hasErrors()) {
            $score -= 30; // Heavy penalty for parse errors
        }
        
        if ($this->hasSecurityIssues()) {
            $score -= 15; // Extra penalty for security issues
        }
        
        return max(0, min(100, $score));
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'created_at' => $this->createdAt->format('c'),
            'summary' => $this->summary,
            'parse_result' => $this->parseResult?->toArray(),
            'analyzer_results' => array_map(
                fn(AnalyzerResult $result) => $result->toArray(),
                $this->analyzerResults
            ),
            'issues' => array_map(
                fn(Issue $issue) => $issue->toArray(),
                $this->allIssues
            ),
            'quality_score' => $this->getQualityScore(),
            'is_successful' => $this->isSuccessful(),
            'has_critical_issues' => $this->hasCriticalIssues(),
            'has_security_issues' => $this->hasSecurityIssues()
        ];
    }

    /**
     * Calculate comprehensive summary
     */
    private function calculateSummary(): void
    {
        $this->summary = [
            'total_analyzers' => count($this->analyzerResults),
            'successful_analyzers' => count(array_filter(
                $this->analyzerResults,
                fn(AnalyzerResult $result) => $result->isSuccessful()
            )),
            'total_issues' => $this->getTotalIssueCount(),
            'critical_issues' => $this->getCriticalIssueCount(),
            'high_issues' => $this->getHighIssueCount(),
            'medium_issues' => $this->getMediumIssueCount(),
            'low_issues' => $this->getLowIssueCount(),
            'security_issues' => $this->getSecurityIssueCount(),
            'performance_issues' => $this->getPerformanceIssueCount(),
            'quality_score' => $this->getQualityScore(),
            'is_successful' => $this->isSuccessful(),
            'has_critical_issues' => $this->hasCriticalIssues(),
            'has_security_issues' => $this->hasSecurityIssues(),
            'categories' => $this->getCategorySummary(),
            'analyzers' => array_keys($this->analyzerResults),
            'total_execution_time' => $this->getTotalExecutionTime(),
            'total_memory_usage' => $this->getTotalMemoryUsage()
        ];
    }

    /**
     * Get category distribution summary
     */
    private function getCategorySummary(): array
    {
        $categories = [];
        
        foreach (Issue::getValidCategories() as $category) {
            $categories[$category] = count($this->getIssuesByCategory($category));
        }
        
        return $categories;
    }

    /**
     * Get total execution time across all analyzers
     */
    private function getTotalExecutionTime(): float
    {
        return array_sum(array_map(
            fn(AnalyzerResult $result) => $result->getExecutionTime(),
            $this->analyzerResults
        ));
    }

    /**
     * Get total memory usage across all analyzers
     */
    private function getTotalMemoryUsage(): int
    {
        return array_sum(array_map(
            fn(AnalyzerResult $result) => $result->getMemoryUsage(),
            $this->analyzerResults
        ));
    }
}