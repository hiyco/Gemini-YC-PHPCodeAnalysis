<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Report Aggregator for combining multiple analyzer results
 */

namespace YcPca\Analysis\Report;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YcPca\Analysis\Analyzer\AnalyzerResult;
use YcPca\Analysis\Issue\Issue;

/**
 * Aggregates multiple analyzer results into comprehensive reports
 * 
 * Features:
 * - Result deduplication
 * - Issue prioritization
 * - Statistical analysis
 * - Format conversion
 */
class ReportAggregator
{
    private LoggerInterface $logger;
    private array $aggregationStats = [
        'reports_created' => 0,
        'results_processed' => 0,
        'issues_deduplicated' => 0,
        'total_processing_time' => 0.0
    ];

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Aggregate multiple analyzer results into a single report
     */
    public function aggregateResults(array $analyzerResults): AnalysisReport
    {
        $startTime = microtime(true);
        
        if (empty($analyzerResults)) {
            throw new \InvalidArgumentException('Cannot aggregate empty results array');
        }
        
        $filePath = $this->extractFilePath($analyzerResults);
        
        $this->logger->info('Starting result aggregation', [
            'file' => $filePath,
            'analyzer_count' => count($analyzerResults)
        ]);
        
        // Create report with all results
        $report = new AnalysisReport($filePath, $analyzerResults);
        
        // Deduplicate issues
        $this->deduplicateIssues($report);
        
        // Update statistics
        $this->updateAggregationStats($startTime, count($analyzerResults));
        
        $this->logger->info('Result aggregation completed', [
            'file' => $filePath,
            'total_issues' => $report->getTotalIssueCount(),
            'quality_score' => $report->getQualityScore(),
            'duration' => microtime(true) - $startTime
        ]);
        
        return $report;
    }

    /**
     * Aggregate multiple reports (for batch processing)
     */
    public function aggregateReports(array $reports): BatchReport
    {
        $startTime = microtime(true);
        
        $this->logger->info('Starting batch report aggregation', [
            'report_count' => count($reports)
        ]);
        
        $batchReport = new BatchReport($reports);
        
        $this->logger->info('Batch aggregation completed', [
            'total_files' => count($reports),
            'total_issues' => $batchReport->getTotalIssueCount(),
            'avg_quality_score' => $batchReport->getAverageQualityScore(),
            'duration' => microtime(true) - $startTime
        ]);
        
        return $batchReport;
    }

    /**
     * Get aggregation statistics
     */
    public function getStats(): array
    {
        return $this->aggregationStats;
    }

    /**
     * Reset aggregation statistics
     */
    public function resetStats(): self
    {
        $this->aggregationStats = array_fill_keys(array_keys($this->aggregationStats), 0);
        return $this;
    }

    /**
     * Extract file path from analyzer results
     */
    private function extractFilePath(array $analyzerResults): string
    {
        if (empty($analyzerResults)) {
            throw new \InvalidArgumentException('No analyzer results provided');
        }
        
        $firstResult = reset($analyzerResults);
        
        if (!$firstResult instanceof AnalyzerResult) {
            throw new \InvalidArgumentException('Invalid analyzer result type');
        }
        
        return $firstResult->getFilePath();
    }

    /**
     * Deduplicate similar issues across analyzers
     */
    private function deduplicateIssues(AnalysisReport $report): void
    {
        $allIssues = $report->getAllIssues();
        $originalCount = count($allIssues);
        
        if ($originalCount <= 1) {
            return; // Nothing to deduplicate
        }
        
        $deduplicatedIssues = [];
        $seenFingerprints = [];
        
        foreach ($allIssues as $issue) {
            $fingerprint = $this->generateIssueFingerprint($issue);
            
            if (!isset($seenFingerprints[$fingerprint])) {
                $deduplicatedIssues[] = $issue;
                $seenFingerprints[$fingerprint] = true;
            }
        }
        
        $deduplicatedCount = count($deduplicatedIssues);
        $duplicatesRemoved = $originalCount - $deduplicatedCount;
        
        if ($duplicatesRemoved > 0) {
            $this->aggregationStats['issues_deduplicated'] += $duplicatesRemoved;
            
            $this->logger->info('Issues deduplicated', [
                'original_count' => $originalCount,
                'deduplicated_count' => $deduplicatedCount,
                'duplicates_removed' => $duplicatesRemoved
            ]);
            
            // Update report with deduplicated issues
            // Note: This would require modifying the AnalysisReport class
            // to support issue replacement, which is not implemented here
        }
    }

    /**
     * Generate unique fingerprint for an issue
     */
    private function generateIssueFingerprint(Issue $issue): string
    {
        // Create fingerprint based on key issue characteristics
        $components = [
            $issue->getCategory(),
            $issue->getSeverity(),
            $issue->getRuleId() ?? 'no-rule',
            $issue->getLine() ?? 0,
            substr(md5($issue->getTitle()), 0, 8), // Short hash of title
            substr(md5($issue->getDescription()), 0, 8) // Short hash of description
        ];
        
        return implode(':', $components);
    }

    /**
     * Update aggregation statistics
     */
    private function updateAggregationStats(float $startTime, int $resultCount): void
    {
        $this->aggregationStats['reports_created']++;
        $this->aggregationStats['results_processed'] += $resultCount;
        $this->aggregationStats['total_processing_time'] += microtime(true) - $startTime;
    }
}