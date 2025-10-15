<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Batch Report for multiple file analysis results
 */

namespace YcPca\Analysis\Report;

use YcPca\Analysis\Issue\Issue;

/**
 * Batch report containing analysis results for multiple files
 * 
 * Features:
 * - Multi-file statistics
 * - Project-level quality metrics
 * - Trend analysis
 * - Export capabilities
 */
class BatchReport
{
    private \DateTime $createdAt;
    
    /** @var AnalysisReport[] */
    private array $reports = [];
    
    private array $summary = [];

    public function __construct(array $reports = [])
    {
        $this->createdAt = new \DateTime();
        
        foreach ($reports as $report) {
            if ($report instanceof AnalysisReport) {
                $this->reports[] = $report;
            }
        }
        
        $this->calculateSummary();
    }

    /**
     * Add analysis report
     */
    public function addReport(AnalysisReport $report): self
    {
        $this->reports[] = $report;
        $this->calculateSummary();
        return $this;
    }

    /**
     * Get all reports
     */
    public function getReports(): array
    {
        return $this->reports;
    }

    /**
     * Get report count
     */
    public function getReportCount(): int
    {
        return count($this->reports);
    }

    /**
     * Get total issue count across all files
     */
    public function getTotalIssueCount(): int
    {
        return array_sum(array_map(
            fn(AnalysisReport $report) => $report->getTotalIssueCount(),
            $this->reports
        ));
    }

    /**
     * Get total critical issue count
     */
    public function getTotalCriticalIssues(): int
    {
        return array_sum(array_map(
            fn(AnalysisReport $report) => $report->getCriticalIssueCount(),
            $this->reports
        ));
    }

    /**
     * Get total security issue count
     */
    public function getTotalSecurityIssues(): int
    {
        return array_sum(array_map(
            fn(AnalysisReport $report) => $report->getSecurityIssueCount(),
            $this->reports
        ));
    }

    /**
     * Get average quality score
     */
    public function getAverageQualityScore(): float
    {
        if (empty($this->reports)) {
            return 0.0;
        }
        
        $totalScore = array_sum(array_map(
            fn(AnalysisReport $report) => $report->getQualityScore(),
            $this->reports
        ));
        
        return $totalScore / count($this->reports);
    }

    /**
     * Get files with critical issues
     */
    public function getFilesWithCriticalIssues(): array
    {
        return array_filter($this->reports, fn(AnalysisReport $report) => $report->hasCriticalIssues());
    }

    /**
     * Get files with security issues
     */
    public function getFilesWithSecurityIssues(): array
    {
        return array_filter($this->reports, fn(AnalysisReport $report) => $report->hasSecurityIssues());
    }

    /**
     * Get worst quality files (bottom 10%)
     */
    public function getWorstQualityFiles(int $count = null): array
    {
        $reports = $this->reports;
        
        usort($reports, fn(AnalysisReport $a, AnalysisReport $b) => 
            $a->getQualityScore() - $b->getQualityScore()
        );
        
        $count = $count ?? max(1, intval(count($reports) * 0.1));
        
        return array_slice($reports, 0, $count);
    }

    /**
     * Get best quality files (top 10%)
     */
    public function getBestQualityFiles(int $count = null): array
    {
        $reports = $this->reports;
        
        usort($reports, fn(AnalysisReport $a, AnalysisReport $b) => 
            $b->getQualityScore() - $a->getQualityScore()
        );
        
        $count = $count ?? max(1, intval(count($reports) * 0.1));
        
        return array_slice($reports, 0, $count);
    }

    /**
     * Get project quality grade (A-F)
     */
    public function getQualityGrade(): string
    {
        $score = $this->getAverageQualityScore();
        
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F'
        };
    }

    /**
     * Get issue distribution by category
     */
    public function getIssueDistribution(): array
    {
        $distribution = [];
        
        foreach (Issue::getValidCategories() as $category) {
            $distribution[$category] = 0;
        }
        
        foreach ($this->reports as $report) {
            foreach ($report->getAllIssues() as $issue) {
                $distribution[$issue->getCategory()]++;
            }
        }
        
        return $distribution;
    }

    /**
     * Get severity distribution
     */
    public function getSeverityDistribution(): array
    {
        $distribution = [];
        
        foreach (Issue::getValidSeverities() as $severity) {
            $distribution[$severity] = 0;
        }
        
        foreach ($this->reports as $report) {
            foreach ($report->getAllIssues() as $issue) {
                $distribution[$issue->getSeverity()]++;
            }
        }
        
        return $distribution;
    }

    /**
     * Get batch summary
     */
    public function getSummary(): array
    {
        return $this->summary;
    }

    /**
     * Get creation timestamp
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'created_at' => $this->createdAt->format('c'),
            'summary' => $this->summary,
            'reports' => array_map(
                fn(AnalysisReport $report) => $report->toArray(),
                $this->reports
            ),
            'issue_distribution' => $this->getIssueDistribution(),
            'severity_distribution' => $this->getSeverityDistribution(),
            'worst_quality_files' => array_map(
                fn(AnalysisReport $report) => [
                    'file' => $report->getFilePath(),
                    'score' => $report->getQualityScore()
                ],
                $this->getWorstQualityFiles(5)
            ),
            'best_quality_files' => array_map(
                fn(AnalysisReport $report) => [
                    'file' => $report->getFilePath(),
                    'score' => $report->getQualityScore()
                ],
                $this->getBestQualityFiles(5)
            )
        ];
    }

    /**
     * Calculate batch summary statistics
     */
    private function calculateSummary(): void
    {
        if (empty($this->reports)) {
            $this->summary = [];
            return;
        }
        
        $this->summary = [
            'total_files' => count($this->reports),
            'successful_analyses' => count(array_filter(
                $this->reports,
                fn(AnalysisReport $report) => $report->isSuccessful()
            )),
            'failed_analyses' => count(array_filter(
                $this->reports,
                fn(AnalysisReport $report) => !$report->isSuccessful()
            )),
            'total_issues' => $this->getTotalIssueCount(),
            'total_critical_issues' => $this->getTotalCriticalIssues(),
            'total_security_issues' => $this->getTotalSecurityIssues(),
            'files_with_critical_issues' => count($this->getFilesWithCriticalIssues()),
            'files_with_security_issues' => count($this->getFilesWithSecurityIssues()),
            'average_quality_score' => round($this->getAverageQualityScore(), 2),
            'quality_grade' => $this->getQualityGrade(),
            'min_quality_score' => $this->getMinQualityScore(),
            'max_quality_score' => $this->getMaxQualityScore(),
            'total_execution_time' => $this->getTotalExecutionTime(),
            'average_execution_time' => $this->getAverageExecutionTime(),
            'issue_distribution' => $this->getIssueDistribution(),
            'severity_distribution' => $this->getSeverityDistribution()
        ];
    }

    /**
     * Get minimum quality score
     */
    private function getMinQualityScore(): int
    {
        if (empty($this->reports)) {
            return 0;
        }
        
        return min(array_map(
            fn(AnalysisReport $report) => $report->getQualityScore(),
            $this->reports
        ));
    }

    /**
     * Get maximum quality score
     */
    private function getMaxQualityScore(): int
    {
        if (empty($this->reports)) {
            return 0;
        }
        
        return max(array_map(
            fn(AnalysisReport $report) => $report->getQualityScore(),
            $this->reports
        ));
    }

    /**
     * Get total execution time
     */
    private function getTotalExecutionTime(): float
    {
        return array_sum(array_map(
            fn(AnalysisReport $report) => $report->getSummary()['total_execution_time'] ?? 0.0,
            $this->reports
        ));
    }

    /**
     * Get average execution time per file
     */
    private function getAverageExecutionTime(): float
    {
        if (empty($this->reports)) {
            return 0.0;
        }
        
        return $this->getTotalExecutionTime() / count($this->reports);
    }
}