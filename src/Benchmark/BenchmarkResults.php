<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Benchmark results container and management
 */

namespace YcPca\Benchmark;

/**
 * Container for benchmark execution results
 * 
 * Stores and manages results from benchmark suite executions
 */
class BenchmarkResults
{
    private array $suiteResults = [];
    private float $totalExecutionTime = 0.0;
    private array $systemInfo = [];
    private int $timestamp;
    private array $metadata = [];

    public function __construct()
    {
        $this->timestamp = time();
    }

    /**
     * Add results for a benchmark suite
     */
    public function addSuiteResult(string $suiteName, array $results): self
    {
        $this->suiteResults[$suiteName] = $results;
        return $this;
    }

    /**
     * Get results for specific suite
     */
    public function getSuiteResult(string $suiteName): ?array
    {
        return $this->suiteResults[$suiteName] ?? null;
    }

    /**
     * Get all suite results
     */
    public function getSuiteResults(): array
    {
        return $this->suiteResults;
    }

    /**
     * Set total execution time for all benchmarks
     */
    public function setTotalExecutionTime(float $time): self
    {
        $this->totalExecutionTime = $time;
        return $this;
    }

    /**
     * Get total execution time
     */
    public function getTotalExecutionTime(): float
    {
        return $this->totalExecutionTime;
    }

    /**
     * Set system information
     */
    public function setSystemInfo(array $systemInfo): self
    {
        $this->systemInfo = $systemInfo;
        return $this;
    }

    /**
     * Get system information
     */
    public function getSystemInfo(): array
    {
        return $this->systemInfo;
    }

    /**
     * Get execution timestamp
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Set metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Get metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get total number of benchmarks executed
     */
    public function getTotalBenchmarkCount(): int
    {
        $count = 0;
        foreach ($this->suiteResults as $suiteResult) {
            $count += count($suiteResult);
        }
        return $count;
    }

    /**
     * Get total number of test suites
     */
    public function getSuiteCount(): int
    {
        return count($this->suiteResults);
    }

    /**
     * Get overall performance statistics
     */
    public function getOverallStatistics(): array
    {
        $totalBenchmarks = 0;
        $totalTime = 0.0;
        $totalMemory = 0;
        $avgTimes = [];
        $avgMemories = [];

        foreach ($this->suiteResults as $suiteName => $suiteResult) {
            foreach ($suiteResult as $benchmarkName => $result) {
                $totalBenchmarks++;
                $totalTime += $result['avg_time'] ?? 0;
                $totalMemory += $result['avg_memory'] ?? 0;
                
                if (isset($result['avg_time'])) {
                    $avgTimes[] = $result['avg_time'];
                }
                if (isset($result['avg_memory'])) {
                    $avgMemories[] = $result['avg_memory'];
                }
            }
        }

        return [
            'total_benchmarks' => $totalBenchmarks,
            'total_suites' => $this->getSuiteCount(),
            'total_execution_time' => $this->totalExecutionTime,
            'average_benchmark_time' => $totalBenchmarks > 0 ? ($totalTime / $totalBenchmarks) : 0,
            'average_memory_usage' => $totalBenchmarks > 0 ? ($totalMemory / $totalBenchmarks) : 0,
            'fastest_benchmark_time' => !empty($avgTimes) ? min($avgTimes) : 0,
            'slowest_benchmark_time' => !empty($avgTimes) ? max($avgTimes) : 0,
            'lowest_memory_usage' => !empty($avgMemories) ? min($avgMemories) : 0,
            'highest_memory_usage' => !empty($avgMemories) ? max($avgMemories) : 0,
            'timestamp' => $this->timestamp,
            'system_info' => $this->systemInfo
        ];
    }

    /**
     * Get benchmarks that exceed expected performance thresholds
     */
    public function getPerformanceViolations(float $timeThresholdMultiplier = 1.5, float $memoryThresholdMultiplier = 2.0): array
    {
        $violations = [];

        foreach ($this->suiteResults as $suiteName => $suiteResult) {
            foreach ($suiteResult as $benchmarkName => $result) {
                $violation = [];

                // Check time violations (if we had expected values)
                if (isset($result['expected_time']) && isset($result['avg_time'])) {
                    $expectedTime = $result['expected_time'];
                    $actualTime = $result['avg_time'];
                    
                    if ($actualTime > ($expectedTime * $timeThresholdMultiplier)) {
                        $violation['time_violation'] = [
                            'expected' => $expectedTime,
                            'actual' => $actualTime,
                            'ratio' => $actualTime / $expectedTime
                        ];
                    }
                }

                // Check memory violations
                if (isset($result['expected_memory']) && isset($result['avg_memory'])) {
                    $expectedMemory = $result['expected_memory'];
                    $actualMemory = $result['avg_memory'];
                    
                    if ($actualMemory > ($expectedMemory * $memoryThresholdMultiplier)) {
                        $violation['memory_violation'] = [
                            'expected' => $expectedMemory,
                            'actual' => $actualMemory,
                            'ratio' => $actualMemory / $expectedMemory
                        ];
                    }
                }

                if (!empty($violation)) {
                    $violations["{$suiteName}.{$benchmarkName}"] = $violation;
                }
            }
        }

        return $violations;
    }

    /**
     * Find benchmarks with high variability (unstable performance)
     */
    public function getUnstableBenchmarks(float $variabilityThreshold = 0.2): array
    {
        $unstable = [];

        foreach ($this->suiteResults as $suiteName => $suiteResult) {
            foreach ($suiteResult as $benchmarkName => $result) {
                if (isset($result['avg_time'], $result['stddev_time']) && $result['avg_time'] > 0) {
                    $coefficientOfVariation = $result['stddev_time'] / $result['avg_time'];
                    
                    if ($coefficientOfVariation > $variabilityThreshold) {
                        $unstable["{$suiteName}.{$benchmarkName}"] = [
                            'avg_time' => $result['avg_time'],
                            'stddev_time' => $result['stddev_time'],
                            'coefficient_of_variation' => $coefficientOfVariation,
                            'min_time' => $result['min_time'] ?? 0,
                            'max_time' => $result['max_time'] ?? 0
                        ];
                    }
                }
            }
        }

        return $unstable;
    }

    /**
     * Export results to array format
     */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'total_execution_time' => $this->totalExecutionTime,
            'system_info' => $this->systemInfo,
            'suite_results' => $this->suiteResults,
            'metadata' => $this->metadata,
            'statistics' => $this->getOverallStatistics()
        ];
    }

    /**
     * Create results object from array data
     */
    public static function fromArray(array $data): self
    {
        $results = new self();
        
        if (isset($data['timestamp'])) {
            $results->timestamp = $data['timestamp'];
        }
        
        if (isset($data['total_execution_time'])) {
            $results->setTotalExecutionTime($data['total_execution_time']);
        }
        
        if (isset($data['system_info'])) {
            $results->setSystemInfo($data['system_info']);
        }
        
        if (isset($data['suite_results'])) {
            $results->suiteResults = $data['suite_results'];
        }
        
        if (isset($data['metadata'])) {
            $results->setMetadata($data['metadata']);
        }
        
        return $results;
    }
}