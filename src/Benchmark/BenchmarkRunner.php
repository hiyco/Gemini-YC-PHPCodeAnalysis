<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Benchmark Runner for performance testing
 */

namespace YcPca\Benchmark;

use YcPca\Ast\PhpAstParser;
use YcPca\Analysis\AnalysisEngine;
use YcPca\Model\FileContext;

/**
 * Benchmark runner for measuring and tracking performance metrics
 * 
 * Features:
 * - Multiple benchmark scenarios
 * - Performance regression detection
 * - Memory usage tracking
 * - Statistical analysis
 * - Results comparison
 */
class BenchmarkRunner
{
    private PhpAstParser $astParser;
    private AnalysisEngine $analysisEngine;
    private array $benchmarkSuites = [];
    private array $results = [];
    private array $config = [];

    public function __construct(PhpAstParser $astParser, AnalysisEngine $analysisEngine, array $config = [])
    {
        $this->astParser = $astParser;
        $this->analysisEngine = $analysisEngine;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Register a benchmark suite
     */
    public function addBenchmarkSuite(BenchmarkSuite $suite): self
    {
        $this->benchmarkSuites[$suite->getName()] = $suite;
        return $this;
    }

    /**
     * Run all benchmark suites
     */
    public function runAllBenchmarks(): BenchmarkResults
    {
        $startTime = microtime(true);
        $results = new BenchmarkResults();
        
        foreach ($this->benchmarkSuites as $suite) {
            $suiteResult = $this->runBenchmarkSuite($suite);
            $results->addSuiteResult($suite->getName(), $suiteResult);
        }
        
        $results->setTotalExecutionTime(microtime(true) - $startTime);
        $results->setSystemInfo($this->getSystemInfo());
        
        return $results;
    }

    /**
     * Run a specific benchmark suite
     */
    public function runBenchmarkSuite(BenchmarkSuite $suite): array
    {
        $suiteResults = [];
        $benchmarks = $suite->getBenchmarks();
        
        echo "Running benchmark suite: {$suite->getName()}\n";
        
        foreach ($benchmarks as $benchmark) {
            echo "  - Running {$benchmark->getName()}...";
            
            $result = $this->runSingleBenchmark($benchmark);
            $suiteResults[$benchmark->getName()] = $result;
            
            echo sprintf(" %.2fms (%.2fMB)\n", $result['avg_time'] * 1000, $result['avg_memory'] / 1024 / 1024);
        }
        
        return $suiteResults;
    }

    /**
     * Run a single benchmark multiple times and collect statistics
     */
    public function runSingleBenchmark(Benchmark $benchmark): array
    {
        $iterations = $this->config['iterations'];
        $warmupRuns = $this->config['warmup_runs'];
        
        // Warm-up runs
        for ($i = 0; $i < $warmupRuns; $i++) {
            $this->executeBenchmark($benchmark);
        }
        
        $measurements = [];
        
        // Actual benchmark runs
        for ($i = 0; $i < $iterations; $i++) {
            $measurements[] = $this->executeBenchmark($benchmark);
        }
        
        return $this->calculateStatistics($measurements);
    }

    /**
     * Execute a single benchmark iteration
     */
    private function executeBenchmark(Benchmark $benchmark): array
    {
        // Force garbage collection before measurement
        gc_collect_cycles();
        
        $initialMemory = memory_get_usage(true);
        $initialPeakMemory = memory_get_peak_usage(true);
        $startTime = microtime(true);
        
        // Execute the benchmark
        $result = $benchmark->execute($this->astParser, $this->analysisEngine);
        
        $endTime = microtime(true);
        $finalMemory = memory_get_usage(true);
        $finalPeakMemory = memory_get_peak_usage(true);
        
        return [
            'execution_time' => $endTime - $startTime,
            'memory_used' => max(0, $finalMemory - $initialMemory),
            'peak_memory' => max(0, $finalPeakMemory - $initialPeakMemory),
            'initial_memory' => $initialMemory,
            'final_memory' => $finalMemory,
            'result_data' => $result
        ];
    }

    /**
     * Calculate statistics from multiple measurements
     */
    private function calculateStatistics(array $measurements): array
    {
        $times = array_column($measurements, 'execution_time');
        $memories = array_column($measurements, 'memory_used');
        $peaks = array_column($measurements, 'peak_memory');
        
        return [
            'iterations' => count($measurements),
            'avg_time' => array_sum($times) / count($times),
            'min_time' => min($times),
            'max_time' => max($times),
            'median_time' => $this->calculateMedian($times),
            'stddev_time' => $this->calculateStandardDeviation($times),
            'avg_memory' => array_sum($memories) / count($memories),
            'min_memory' => min($memories),
            'max_memory' => max($memories),
            'avg_peak_memory' => array_sum($peaks) / count($peaks),
            'measurements' => $measurements
        ];
    }

    /**
     * Compare benchmark results with baseline
     */
    public function compareWithBaseline(BenchmarkResults $current, BenchmarkResults $baseline): BenchmarkComparison
    {
        $comparison = new BenchmarkComparison();
        
        foreach ($current->getSuiteResults() as $suiteName => $suiteResults) {
            $baselineSuite = $baseline->getSuiteResult($suiteName);
            
            if ($baselineSuite === null) {
                continue;
            }
            
            foreach ($suiteResults as $benchmarkName => $result) {
                $baselineResult = $baselineSuite[$benchmarkName] ?? null;
                
                if ($baselineResult === null) {
                    continue;
                }
                
                $timeChange = ($result['avg_time'] - $baselineResult['avg_time']) / $baselineResult['avg_time'] * 100;
                $memoryChange = ($result['avg_memory'] - $baselineResult['avg_memory']) / max(1, $baselineResult['avg_memory']) * 100;
                
                $comparison->addBenchmarkComparison($suiteName, $benchmarkName, [
                    'time_change_percent' => $timeChange,
                    'memory_change_percent' => $memoryChange,
                    'current_time' => $result['avg_time'],
                    'baseline_time' => $baselineResult['avg_time'],
                    'current_memory' => $result['avg_memory'],
                    'baseline_memory' => $baselineResult['avg_memory'],
                    'regression' => $this->detectRegression($timeChange, $memoryChange)
                ]);
            }
        }
        
        return $comparison;
    }

    /**
     * Export benchmark results to file
     */
    public function exportResults(BenchmarkResults $results, string $filepath): void
    {
        $data = [
            'timestamp' => date('c'),
            'system_info' => $results->getSystemInfo(),
            'total_execution_time' => $results->getTotalExecutionTime(),
            'suite_results' => $results->getSuiteResults(),
            'config' => $this->config
        ];
        
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Import benchmark results from file
     */
    public function importResults(string $filepath): ?BenchmarkResults
    {
        if (!file_exists($filepath)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($filepath), true);
        if ($data === null) {
            return null;
        }
        
        $results = new BenchmarkResults();
        $results->setTotalExecutionTime($data['total_execution_time']);
        $results->setSystemInfo($data['system_info']);
        
        foreach ($data['suite_results'] as $suiteName => $suiteResult) {
            $results->addSuiteResult($suiteName, $suiteResult);
        }
        
        return $results;
    }

    /**
     * Generate performance report
     */
    public function generateReport(BenchmarkResults $results, ?BenchmarkComparison $comparison = null): string
    {
        $report = [];
        $report[] = "=== YC PHP Code Analysis - Performance Benchmark Report ===";
        $report[] = "";
        $report[] = "Generated: " . date('Y-m-d H:i:s');
        $report[] = "Total Execution Time: " . sprintf('%.2fs', $results->getTotalExecutionTime());
        $report[] = "";
        
        // System information
        $systemInfo = $results->getSystemInfo();
        $report[] = "System Information:";
        $report[] = "  PHP Version: " . $systemInfo['php_version'];
        $report[] = "  Memory Limit: " . $systemInfo['memory_limit'];
        $report[] = "  CPU Info: " . $systemInfo['cpu_info'];
        $report[] = "  OS: " . $systemInfo['os'];
        $report[] = "";
        
        // Benchmark results
        foreach ($results->getSuiteResults() as $suiteName => $suiteResults) {
            $report[] = "Suite: {$suiteName}";
            $report[] = str_repeat('-', 40);
            
            foreach ($suiteResults as $benchmarkName => $result) {
                $report[] = sprintf(
                    "  %s:",
                    $benchmarkName
                );
                $report[] = sprintf(
                    "    Avg Time: %.2fms (±%.2fms)",
                    $result['avg_time'] * 1000,
                    $result['stddev_time'] * 1000
                );
                $report[] = sprintf(
                    "    Memory: %.2fMB (peak: %.2fMB)",
                    $result['avg_memory'] / 1024 / 1024,
                    $result['avg_peak_memory'] / 1024 / 1024
                );
                $report[] = sprintf(
                    "    Range: %.2f-%.2fms",
                    $result['min_time'] * 1000,
                    $result['max_time'] * 1000
                );
                $report[] = "";
            }
        }
        
        // Comparison with baseline
        if ($comparison) {
            $report[] = "Performance Comparison:";
            $report[] = str_repeat('-', 40);
            
            foreach ($comparison->getComparisons() as $key => $comp) {
                [$suiteName, $benchmarkName] = explode('.', $key, 2);
                
                $timeIcon = $comp['time_change_percent'] > 5 ? '🔴' : ($comp['time_change_percent'] < -5 ? '🟢' : '🟡');
                $memoryIcon = $comp['memory_change_percent'] > 10 ? '🔴' : ($comp['memory_change_percent'] < -10 ? '🟢' : '🟡');
                
                $report[] = sprintf(
                    "  %s.%s:",
                    $suiteName,
                    $benchmarkName
                );
                $report[] = sprintf(
                    "    Time: %s %+.1f%% (%.2fms → %.2fms)",
                    $timeIcon,
                    $comp['time_change_percent'],
                    $comp['baseline_time'] * 1000,
                    $comp['current_time'] * 1000
                );
                $report[] = sprintf(
                    "    Memory: %s %+.1f%% (%.2fMB → %.2fMB)",
                    $memoryIcon,
                    $comp['memory_change_percent'],
                    $comp['baseline_memory'] / 1024 / 1024,
                    $comp['current_memory'] / 1024 / 1024
                );
                
                if ($comp['regression']) {
                    $report[] = "    ⚠️  PERFORMANCE REGRESSION DETECTED";
                }
                
                $report[] = "";
            }
        }
        
        return implode("\n", $report);
    }

    /**
     * Get system information
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'cpu_info' => $this->getCpuInfo(),
            'os' => PHP_OS_FAMILY,
            'architecture' => php_uname('m'),
            'timestamp' => time()
        ];
    }

    /**
     * Get CPU information
     */
    private function getCpuInfo(): string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $cpu = shell_exec('sysctl -n machdep.cpu.brand_string 2>/dev/null');
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $cpu = shell_exec('cat /proc/cpuinfo | grep "model name" | head -1 | cut -d: -f2 2>/dev/null');
        } else {
            $cpu = 'Unknown';
        }
        
        return trim($cpu ?: 'Unknown');
    }

    /**
     * Calculate median of array
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        
        if ($count % 2 === 0) {
            return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        } else {
            return $values[intval($count / 2)];
        }
    }

    /**
     * Calculate standard deviation
     */
    private function calculateStandardDeviation(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $sum = 0;
        
        foreach ($values as $value) {
            $sum += pow($value - $mean, 2);
        }
        
        return sqrt($sum / count($values));
    }

    /**
     * Detect performance regression
     */
    private function detectRegression(float $timeChange, float $memoryChange): bool
    {
        $timeThreshold = $this->config['regression_time_threshold'];
        $memoryThreshold = $this->config['regression_memory_threshold'];
        
        return $timeChange > $timeThreshold || $memoryChange > $memoryThreshold;
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'iterations' => 10,
            'warmup_runs' => 3,
            'regression_time_threshold' => 10.0, // 10% slower
            'regression_memory_threshold' => 20.0, // 20% more memory
        ];
    }
}