<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Report Generator for analysis results
 */

namespace YcPca\Report;

use YcPca\Analysis\Issue\Issue;
use YcPca\Model\AnalysisResult;

/**
 * Generates reports in various formats from analysis results
 * 
 * Features:
 * - Multiple output formats (console, JSON, XML, HTML)
 * - Severity filtering
 * - Statistics and metrics
 * - Baseline comparison
 */
class ReportGenerator
{
    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Generate report from analysis results
     */
    public function generate(array $results, array $options = []): string
    {
        $format = $options['format'] ?? 'console';
        $severityThreshold = $options['severity_threshold'] ?? 'medium';
        $includeStats = $options['include_stats'] ?? false;
        $baseline = $options['baseline'] ?? null;
        
        // Filter results by severity threshold
        $filteredResults = $this->filterResultsBySeverity($results, $severityThreshold);
        
        // Generate statistics
        $stats = $this->generateStatistics($filteredResults);
        
        // Compare with baseline if provided
        $comparison = null;
        if ($baseline) {
            $comparison = $this->compareWithBaseline($filteredResults, $baseline);
        }
        
        return match ($format) {
            'json' => $this->generateJsonReport($filteredResults, $stats, $comparison, $options),
            'xml' => $this->generateXmlReport($filteredResults, $stats, $comparison, $options),
            'html' => $this->generateHtmlReport($filteredResults, $stats, $comparison, $options),
            'console' => $this->generateConsoleReport($filteredResults, $stats, $comparison, $options),
            default => $this->generateConsoleReport($filteredResults, $stats, $comparison, $options)
        };
    }

    /**
     * Filter results by severity threshold
     */
    private function filterResultsBySeverity(array $results, string $severityThreshold): array
    {
        $severityLevels = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $threshold = $severityLevels[$severityThreshold] ?? 2;
        
        $filteredResults = [];
        
        foreach ($results as $filePath => $result) {
            $filteredIssues = array_filter($result->getIssues(), function (Issue $issue) use ($severityLevels, $threshold) {
                $issueSeverity = $severityLevels[$issue->getSeverity()] ?? 0;
                return $issueSeverity >= $threshold;
            });
            
            if (!empty($filteredIssues)) {
                $filteredResult = clone $result;
                $filteredResult->setIssues($filteredIssues);
                $filteredResults[$filePath] = $filteredResult;
            }
        }
        
        return $filteredResults;
    }

    /**
     * Generate comprehensive statistics
     */
    private function generateStatistics(array $results): array
    {
        $stats = [
            'total_files' => count($results),
            'total_issues' => 0,
            'issues_by_severity' => [],
            'issues_by_category' => [],
            'issues_by_analyzer' => [],
            'files_with_issues' => 0,
            'most_problematic_files' => [],
            'analyzer_performance' => []
        ];
        
        foreach ($results as $filePath => $result) {
            $issues = $result->getIssues();
            $issueCount = count($issues);
            
            if ($issueCount > 0) {
                $stats['files_with_issues']++;
                $stats['most_problematic_files'][] = [
                    'file' => $filePath,
                    'issues' => $issueCount
                ];
            }
            
            $stats['total_issues'] += $issueCount;
            
            foreach ($issues as $issue) {
                // By severity
                $severity = $issue->getSeverity();
                $stats['issues_by_severity'][$severity] = ($stats['issues_by_severity'][$severity] ?? 0) + 1;
                
                // By category
                $category = $issue->getCategory();
                $stats['issues_by_category'][$category] = ($stats['issues_by_category'][$category] ?? 0) + 1;
                
                // By analyzer/rule
                $ruleId = $issue->getRuleId();
                $stats['issues_by_analyzer'][$ruleId] = ($stats['issues_by_analyzer'][$ruleId] ?? 0) + 1;
            }
            
            // Analyzer performance
            $metadata = $result->getMetadata();
            if (isset($metadata['execution_time'])) {
                $stats['analyzer_performance'][] = [
                    'file' => $filePath,
                    'execution_time' => $metadata['execution_time'],
                    'memory_usage' => $metadata['memory_usage'] ?? 0
                ];
            }
        }
        
        // Sort most problematic files
        usort($stats['most_problematic_files'], fn($a, $b) => $b['issues'] - $a['issues']);
        $stats['most_problematic_files'] = array_slice($stats['most_problematic_files'], 0, 10);
        
        return $stats;
    }

    /**
     * Compare results with baseline
     */
    private function compareWithBaseline(array $results, string $baselineFile): array
    {
        if (!file_exists($baselineFile)) {
            return ['error' => 'Baseline file not found'];
        }
        
        try {
            $baseline = json_decode(file_get_contents($baselineFile), true, 512, JSON_THROW_ON_ERROR);
            
            $current = $this->generateStatistics($results);
            $previous = $baseline['statistics'] ?? [];
            
            return [
                'total_issues_change' => ($current['total_issues'] ?? 0) - ($previous['total_issues'] ?? 0),
                'new_files_with_issues' => ($current['files_with_issues'] ?? 0) - ($previous['files_with_issues'] ?? 0),
                'severity_changes' => $this->calculateSeverityChanges($current, $previous),
                'new_issue_types' => $this->findNewIssueTypes($current, $previous)
            ];
            
        } catch (\JsonException $e) {
            return ['error' => 'Invalid baseline file format'];
        }
    }

    /**
     * Generate JSON report
     */
    private function generateJsonReport(array $results, array $stats, ?array $comparison, array $options): string
    {
        $report = [
            'metadata' => [
                'generated_at' => date('c'),
                'analyzer_version' => '1.0.0',
                'severity_threshold' => $options['severity_threshold'] ?? 'medium'
            ],
            'statistics' => $stats,
            'results' => []
        ];
        
        if ($comparison) {
            $report['baseline_comparison'] = $comparison;
        }
        
        foreach ($results as $filePath => $result) {
            $fileReport = [
                'file' => $filePath,
                'issues_count' => count($result->getIssues()),
                'issues' => []
            ];
            
            foreach ($result->getIssues() as $issue) {
                $fileReport['issues'][] = [
                    'id' => $issue->getId(),
                    'title' => $issue->getTitle(),
                    'description' => $issue->getDescription(),
                    'severity' => $issue->getSeverity(),
                    'category' => $issue->getCategory(),
                    'line' => $issue->getLine(),
                    'column' => $issue->getColumn(),
                    'rule_id' => $issue->getRuleId(),
                    'rule_name' => $issue->getRuleName(),
                    'tags' => $issue->getTags(),
                    'suggestions' => $issue->getSuggestions(),
                    'metadata' => $issue->getMetadata()
                ];
            }
            
            $report['results'][] = $fileReport;
        }
        
        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate XML report
     */
    private function generateXmlReport(array $results, array $stats, ?array $comparison, array $options): string
    {
        $xml = new \SimpleXMLElement('<analysis_report/>');
        
        // Metadata
        $metadata = $xml->addChild('metadata');
        $metadata->addChild('generated_at', date('c'));
        $metadata->addChild('analyzer_version', '1.0.0');
        $metadata->addChild('severity_threshold', $options['severity_threshold'] ?? 'medium');
        
        // Statistics
        $statisticsNode = $xml->addChild('statistics');
        $this->addArrayToXml($stats, $statisticsNode);
        
        // Baseline comparison
        if ($comparison) {
            $comparisonNode = $xml->addChild('baseline_comparison');
            $this->addArrayToXml($comparison, $comparisonNode);
        }
        
        // Results
        $resultsNode = $xml->addChild('results');
        
        foreach ($results as $filePath => $result) {
            $fileNode = $resultsNode->addChild('file');
            $fileNode->addAttribute('path', $filePath);
            $fileNode->addAttribute('issues_count', (string) count($result->getIssues()));
            
            $issuesNode = $fileNode->addChild('issues');
            
            foreach ($result->getIssues() as $issue) {
                $issueNode = $issuesNode->addChild('issue');
                $issueNode->addAttribute('id', $issue->getId());
                $issueNode->addChild('title', htmlspecialchars($issue->getTitle()));
                $issueNode->addChild('description', htmlspecialchars($issue->getDescription()));
                $issueNode->addChild('severity', $issue->getSeverity());
                $issueNode->addChild('category', $issue->getCategory());
                $issueNode->addChild('line', (string) $issue->getLine());
                $issueNode->addChild('column', (string) $issue->getColumn());
                $issueNode->addChild('rule_id', $issue->getRuleId());
                $issueNode->addChild('rule_name', htmlspecialchars($issue->getRuleName()));
            }
        }
        
        return $xml->asXML();
    }

    /**
     * Generate HTML report
     */
    private function generateHtmlReport(array $results, array $stats, ?array $comparison, array $options): string
    {
        $html = $this->getHtmlTemplate();
        
        // Replace placeholders
        $html = str_replace('{{TITLE}}', 'PHP Code Analysis Report', $html);
        $html = str_replace('{{GENERATED_AT}}', date('Y-m-d H:i:s'), $html);
        $html = str_replace('{{STATISTICS}}', $this->generateHtmlStatistics($stats), $html);
        $html = str_replace('{{RESULTS}}', $this->generateHtmlResults($results), $html);
        
        return $html;
    }

    /**
     * Generate console report
     */
    private function generateConsoleReport(array $results, array $stats, ?array $comparison, array $options): string
    {
        $output = [];
        
        $output[] = "\n" . str_repeat('=', 60);
        $output[] = "          PHP CODE ANALYSIS REPORT";
        $output[] = str_repeat('=', 60);
        
        if ($stats['total_issues'] === 0) {
            $output[] = "\n✅ No issues found!";
            $output[] = sprintf("Analyzed %d files successfully.", $stats['total_files']);
        } else {
            $output[] = sprintf("\n📊 Found %d issues in %d files", $stats['total_issues'], $stats['files_with_issues']);
            
            // Issues by severity
            if (!empty($stats['issues_by_severity'])) {
                $output[] = "\n📋 Issues by Severity:";
                foreach (['critical', 'high', 'medium', 'low', 'info'] as $severity) {
                    if (isset($stats['issues_by_severity'][$severity])) {
                        $icon = $this->getSeverityIcon($severity);
                        $output[] = sprintf("  %s %s: %d", $icon, ucfirst($severity), $stats['issues_by_severity'][$severity]);
                    }
                }
            }
            
            // Most problematic files
            if (!empty($stats['most_problematic_files'])) {
                $output[] = "\n🚨 Most Problematic Files:";
                foreach (array_slice($stats['most_problematic_files'], 0, 5) as $file) {
                    $output[] = sprintf("  • %s (%d issues)", $file['file'], $file['issues']);
                }
            }
            
            // Detailed issues
            $output[] = "\n" . str_repeat('-', 60);
            $output[] = "DETAILED ISSUES";
            $output[] = str_repeat('-', 60);
            
            foreach ($results as $filePath => $result) {
                $issues = $result->getIssues();
                if (empty($issues)) continue;
                
                $output[] = "\n📁 {$filePath}";
                
                foreach ($issues as $issue) {
                    $icon = $this->getSeverityIcon($issue->getSeverity());
                    $output[] = sprintf("  %s Line %d: %s", $icon, $issue->getLine(), $issue->getTitle());
                    $output[] = sprintf("     %s", $issue->getDescription());
                    
                    if (!empty($issue->getSuggestions())) {
                        $output[] = "     💡 Suggestions:";
                        foreach (array_slice($issue->getSuggestions(), 0, 2) as $suggestion) {
                            $output[] = "       • {$suggestion}";
                        }
                    }
                    $output[] = "";
                }
            }
        }
        
        if ($comparison) {
            $output[] = $this->generateConsoleComparison($comparison);
        }
        
        $output[] = str_repeat('=', 60);
        
        return implode("\n", $output);
    }

    /**
     * Get severity icon for console output
     */
    private function getSeverityIcon(string $severity): string
    {
        return match ($severity) {
            'critical' => '🔥',
            'high' => '🚨',
            'medium' => '⚠️',
            'low' => '💡',
            'info' => 'ℹ️',
            default => '•'
        };
    }

    /**
     * Helper methods for report generation
     */
    private function addArrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->addArrayToXml($value, $child);
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }

    private function calculateSeverityChanges(array $current, array $previous): array
    {
        $changes = [];
        $severities = ['critical', 'high', 'medium', 'low', 'info'];
        
        foreach ($severities as $severity) {
            $currentCount = $current['issues_by_severity'][$severity] ?? 0;
            $previousCount = $previous['issues_by_severity'][$severity] ?? 0;
            $changes[$severity] = $currentCount - $previousCount;
        }
        
        return $changes;
    }

    private function findNewIssueTypes(array $current, array $previous): array
    {
        $currentTypes = array_keys($current['issues_by_analyzer'] ?? []);
        $previousTypes = array_keys($previous['issues_by_analyzer'] ?? []);
        
        return array_diff($currentTypes, $previousTypes);
    }

    private function generateHtmlStatistics(array $stats): string
    {
        // Simple HTML statistics generation
        $html = '<div class="statistics">';
        $html .= '<h3>Statistics</h3>';
        $html .= '<p>Total Files: ' . $stats['total_files'] . '</p>';
        $html .= '<p>Total Issues: ' . $stats['total_issues'] . '</p>';
        $html .= '<p>Files with Issues: ' . $stats['files_with_issues'] . '</p>';
        $html .= '</div>';
        
        return $html;
    }

    private function generateHtmlResults(array $results): string
    {
        $html = '<div class="results">';
        
        foreach ($results as $filePath => $result) {
            $html .= '<div class="file">';
            $html .= '<h4>' . htmlspecialchars($filePath) . '</h4>';
            
            foreach ($result->getIssues() as $issue) {
                $html .= '<div class="issue ' . $issue->getSeverity() . '">';
                $html .= '<strong>Line ' . $issue->getLine() . ':</strong> ';
                $html .= htmlspecialchars($issue->getTitle());
                $html .= '<p>' . htmlspecialchars($issue->getDescription()) . '</p>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    private function generateConsoleComparison(?array $comparison): string
    {
        if (!$comparison || isset($comparison['error'])) {
            return "\n❌ Baseline comparison failed: " . ($comparison['error'] ?? 'Unknown error');
        }
        
        $output = [];
        $output[] = "\n📈 Baseline Comparison:";
        
        $totalChange = $comparison['total_issues_change'] ?? 0;
        if ($totalChange > 0) {
            $output[] = "  📈 {$totalChange} more issues than baseline";
        } elseif ($totalChange < 0) {
            $output[] = "  📉 " . abs($totalChange) . " fewer issues than baseline";
        } else {
            $output[] = "  ✅ Same number of issues as baseline";
        }
        
        return implode("\n", $output);
    }

    private function getHtmlTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <title>{{TITLE}}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .critical { color: #d32f2f; }
        .high { color: #f57c00; }
        .medium { color: #fbc02d; }
        .low { color: #388e3c; }
        .info { color: #1976d2; }
        .issue { margin: 10px 0; padding: 10px; border-left: 4px solid #ccc; }
        .statistics { background: #f5f5f5; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>{{TITLE}}</h1>
    <p>Generated at: {{GENERATED_AT}}</p>
    {{STATISTICS}}
    {{RESULTS}}
</body>
</html>';
    }

    private function getDefaultConfig(): array
    {
        return [
            'include_suggestions' => true,
            'include_metadata' => false,
            'max_issues_per_file' => 100
        ];
    }
}