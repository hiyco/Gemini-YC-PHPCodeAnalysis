<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Benchmark comparison and regression analysis
 */

namespace YcPca\Benchmark;

/**
 * Handles comparison between benchmark results for regression detection
 * 
 * Provides statistical analysis and regression detection capabilities
 */
class BenchmarkComparison
{
    private array $comparisons = [];
    private BenchmarkResults $currentResults;
    private BenchmarkResults $baselineResults;
    private array $regressionThresholds;

    public function __construct(
        BenchmarkResults $currentResults = null, 
        BenchmarkResults $baselineResults = null,
        array $regressionThresholds = []
    ) {
        if ($currentResults) {
            $this->currentResults = $currentResults;
        }
        if ($baselineResults) {
            $this->baselineResults = $baselineResults;
        }
        
        $this->regressionThresholds = array_merge($this->getDefaultThresholds(), $regressionThresholds);
    }

    /**
     * Set current results
     */
    public function setCurrentResults(BenchmarkResults $results): self
    {
        $this->currentResults = $results;
        return $this;
    }

    /**
     * Set baseline results for comparison
     */
    public function setBaselineResults(BenchmarkResults $results): self
    {
        $this->baselineResults = $results;
        return $this;
    }

    /**
     * Add a benchmark comparison result
     */
    public function addBenchmarkComparison(string $suiteName, string $benchmarkName, array $comparison): self
    {
        $key = "{$suiteName}.{$benchmarkName}";
        $this->comparisons[$key] = $comparison;
        return $this;
    }

    /**
     * Get all comparison results
     */
    public function getComparisons(): array
    {
        return $this->comparisons;
    }

    /**
     * Get comparison for specific benchmark
     */
    public function getComparison(string $suiteName, string $benchmarkName): ?array
    {
        $key = "{$suiteName}.{$benchmarkName}";
        return $this->comparisons[$key] ?? null;
    }

    /**
     * Perform comprehensive comparison between current and baseline results
     */
    public function compare(): self
    {
        if (!isset($this->currentResults) || !isset($this->baselineResults)) {
            throw new \RuntimeException('Both current and baseline results must be set before comparison');
        }

        $this->comparisons = [];

        foreach ($this->currentResults->getSuiteResults() as $suiteName => $suiteResults) {
            $baselineSuite = $this->baselineResults->getSuiteResult($suiteName);
            
            if ($baselineSuite === null) {
                continue;
            }

            foreach ($suiteResults as $benchmarkName => $currentResult) {
                $baselineResult = $baselineSuite[$benchmarkName] ?? null;
                
                if ($baselineResult === null) {
                    continue;
                }

                $comparison = $this->compareIndividualBenchmark($currentResult, $baselineResult);
                $this->addBenchmarkComparison($suiteName, $benchmarkName, $comparison);
            }
        }

        return $this;
    }

    /**
     * Compare individual benchmark results
     */
    private function compareIndividualBenchmark(array $current, array $baseline): array
    {
        $comparison = [];

        // Time comparison
        if (isset($current['avg_time'], $baseline['avg_time']) && $baseline['avg_time'] > 0) {
            $timeChange = ($current['avg_time'] - $baseline['avg_time']) / $baseline['avg_time'] * 100;
            $comparison['time_change_percent'] = $timeChange;
            $comparison['current_time'] = $current['avg_time'];
            $comparison['baseline_time'] = $baseline['avg_time'];
            $comparison['time_ratio'] = $current['avg_time'] / $baseline['avg_time'];
        }

        // Memory comparison
        if (isset($current['avg_memory'], $baseline['avg_memory']) && $baseline['avg_memory'] > 0) {
            $memoryChange = ($current['avg_memory'] - $baseline['avg_memory']) / $baseline['avg_memory'] * 100;
            $comparison['memory_change_percent'] = $memoryChange;
            $comparison['current_memory'] = $current['avg_memory'];
            $comparison['baseline_memory'] = $baseline['avg_memory'];
            $comparison['memory_ratio'] = $current['avg_memory'] / $baseline['avg_memory'];
        }

        // Variability comparison
        if (isset($current['stddev_time'], $baseline['stddev_time'], $current['avg_time'], $baseline['avg_time'])) {
            $currentCV = $current['avg_time'] > 0 ? $current['stddev_time'] / $current['avg_time'] : 0;
            $baselineCV = $baseline['avg_time'] > 0 ? $baseline['stddev_time'] / $baseline['avg_time'] : 0;
            
            $comparison['current_variability'] = $currentCV;
            $comparison['baseline_variability'] = $baselineCV;
            $comparison['variability_change'] = $currentCV - $baselineCV;
        }

        // Regression detection
        $comparison['regression'] = $this->detectRegression($comparison);
        $comparison['regression_severity'] = $this->assessRegressionSeverity($comparison);

        // Statistical significance (basic implementation)
        $comparison['statistically_significant'] = $this->isStatisticallySignificant($current, $baseline);

        return $comparison;
    }

    /**
     * Detect if there's a performance regression
     */
    private function detectRegression(array $comparison): bool
    {
        $timeRegression = false;
        $memoryRegression = false;

        if (isset($comparison['time_change_percent'])) {
            $timeRegression = $comparison['time_change_percent'] > $this->regressionThresholds['time_threshold'];
        }

        if (isset($comparison['memory_change_percent'])) {
            $memoryRegression = $comparison['memory_change_percent'] > $this->regressionThresholds['memory_threshold'];
        }

        return $timeRegression || $memoryRegression;
    }

    /**
     * Assess regression severity
     */
    private function assessRegressionSeverity(array $comparison): string
    {
        if (!$comparison['regression']) {
            return 'none';
        }

        $timeChange = $comparison['time_change_percent'] ?? 0;
        $memoryChange = $comparison['memory_change_percent'] ?? 0;

        $maxChange = max($timeChange, $memoryChange);

        if ($maxChange >= $this->regressionThresholds['critical_threshold']) {
            return 'critical';
        } elseif ($maxChange >= $this->regressionThresholds['major_threshold']) {
            return 'major';
        } elseif ($maxChange >= $this->regressionThresholds['minor_threshold']) {
            return 'minor';
        }

        return 'negligible';
    }

    /**
     * Basic statistical significance test (simplified)
     */
    private function isStatisticallySignificant(array $current, array $baseline): bool
    {
        // Simplified implementation - in practice, you'd want proper statistical tests
        if (!isset($current['stddev_time'], $baseline['stddev_time'], $current['avg_time'], $baseline['avg_time'])) {
            return false;
        }

        $timeDiff = abs($current['avg_time'] - $baseline['avg_time']);
        $combinedStddev = sqrt(pow($current['stddev_time'], 2) + pow($baseline['stddev_time'], 2));

        // Simple threshold: difference should be more than 2 combined standard deviations
        return $combinedStddev > 0 && ($timeDiff / $combinedStddev) > 2.0;
    }

    /**
     * Get regressions by severity
     */
    public function getRegressionsBySeverity(string $severity): array
    {
        return array_filter(
            $this->comparisons,
            fn($comparison) => $comparison['regression'] && $comparison['regression_severity'] === $severity
        );
    }

    /**
     * Get all detected regressions
     */
    public function getAllRegressions(): array
    {
        return array_filter($this->comparisons, fn($comparison) => $comparison['regression']);
    }

    /**
     * Get improvements (negative regressions)
     */
    public function getImprovements(): array
    {
        return array_filter($this->comparisons, function($comparison) {
            $timeImproved = isset($comparison['time_change_percent']) && $comparison['time_change_percent'] < -$this->regressionThresholds['improvement_threshold'];
            $memoryImproved = isset($comparison['memory_change_percent']) && $comparison['memory_change_percent'] < -$this->regressionThresholds['improvement_threshold'];
            
            return $timeImproved || $memoryImproved;
        });
    }

    /**
     * Generate comparison summary
     */
    public function getSummary(): array
    {
        $totalComparisons = count($this->comparisons);
        $regressions = $this->getAllRegressions();
        $improvements = $this->getImprovements();

        $regressionsBySeverity = [
            'critical' => count($this->getRegressionsBySeverity('critical')),
            'major' => count($this->getRegressionsBySeverity('major')),
            'minor' => count($this->getRegressionsBySeverity('minor')),
            'negligible' => count($this->getRegressionsBySeverity('negligible'))
        ];

        return [
            'total_benchmarks_compared' => $totalComparisons,
            'regressions_detected' => count($regressions),
            'improvements_detected' => count($improvements),
            'stable_benchmarks' => $totalComparisons - count($regressions) - count($improvements),
            'regressions_by_severity' => $regressionsBySeverity,
            'regression_rate' => $totalComparisons > 0 ? (count($regressions) / $totalComparisons * 100) : 0,
            'improvement_rate' => $totalComparisons > 0 ? (count($improvements) / $totalComparisons * 100) : 0,
            'comparison_timestamp' => time(),
            'baseline_timestamp' => isset($this->baselineResults) ? $this->baselineResults->getTimestamp() : null,
            'current_timestamp' => isset($this->currentResults) ? $this->currentResults->getTimestamp() : null
        ];
    }

    /**
     * Get default regression thresholds
     */
    private function getDefaultThresholds(): array
    {
        return [
            'time_threshold' => 10.0, // 10% slower is a regression
            'memory_threshold' => 20.0, // 20% more memory is a regression
            'improvement_threshold' => 5.0, // 5% better is considered an improvement
            'minor_threshold' => 10.0, // 10% worse is minor
            'major_threshold' => 25.0, // 25% worse is major
            'critical_threshold' => 50.0, // 50% worse is critical
        ];
    }
}