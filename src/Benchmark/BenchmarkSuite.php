<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Benchmark suite for organizing related benchmarks
 */

namespace YcPca\Benchmark;

/**
 * Collection of related benchmarks
 * 
 * Groups benchmarks by category or feature area for organized execution
 */
class BenchmarkSuite
{
    private string $name;
    private string $description;
    private array $benchmarks = [];
    private array $config = [];

    public function __construct(string $name, string $description = '', array $config = [])
    {
        $this->name = $name;
        $this->description = $description;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Get suite name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get suite description
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Add benchmark to suite
     */
    public function addBenchmark(BenchmarkInterface $benchmark): self
    {
        $this->benchmarks[$benchmark->getName()] = $benchmark;
        return $this;
    }

    /**
     * Remove benchmark from suite
     */
    public function removeBenchmark(string $name): self
    {
        unset($this->benchmarks[$name]);
        return $this;
    }

    /**
     * Get specific benchmark by name
     */
    public function getBenchmark(string $name): ?BenchmarkInterface
    {
        return $this->benchmarks[$name] ?? null;
    }

    /**
     * Get all benchmarks
     * 
     * @return BenchmarkInterface[]
     */
    public function getBenchmarks(): array
    {
        return array_filter($this->benchmarks, fn($benchmark) => $benchmark->isEnabled());
    }

    /**
     * Get all benchmarks including disabled ones
     * 
     * @return BenchmarkInterface[]
     */
    public function getAllBenchmarks(): array
    {
        return $this->benchmarks;
    }

    /**
     * Get benchmarks by category
     * 
     * @return BenchmarkInterface[]
     */
    public function getBenchmarksByCategory(string $category): array
    {
        return array_filter(
            $this->benchmarks, 
            fn($benchmark) => $benchmark->getCategory() === $category && $benchmark->isEnabled()
        );
    }

    /**
     * Get benchmark count (enabled only)
     */
    public function getCount(): int
    {
        return count($this->getBenchmarks());
    }

    /**
     * Get total benchmark count (including disabled)
     */
    public function getTotalCount(): int
    {
        return count($this->benchmarks);
    }

    /**
     * Get suite configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set suite configuration
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Check if suite has any enabled benchmarks
     */
    public function hasEnabledBenchmarks(): bool
    {
        return $this->getCount() > 0;
    }

    /**
     * Enable all benchmarks in suite
     */
    public function enableAll(): self
    {
        foreach ($this->benchmarks as $benchmark) {
            $benchmark->setEnabled(true);
        }
        return $this;
    }

    /**
     * Disable all benchmarks in suite
     */
    public function disableAll(): self
    {
        foreach ($this->benchmarks as $benchmark) {
            $benchmark->setEnabled(false);
        }
        return $this;
    }

    /**
     * Get suite statistics
     */
    public function getStatistics(): array
    {
        $categories = [];
        $totalExpectedTime = 0;
        $totalExpectedMemory = 0;

        foreach ($this->getBenchmarks() as $benchmark) {
            $category = $benchmark->getCategory();
            $categories[$category] = ($categories[$category] ?? 0) + 1;
            
            $totalExpectedTime += $benchmark->getExpectedExecutionTime();
            $totalExpectedMemory += $benchmark->getExpectedMemoryUsage();
        }

        return [
            'name' => $this->name,
            'enabled_benchmarks' => $this->getCount(),
            'total_benchmarks' => $this->getTotalCount(),
            'categories' => $categories,
            'estimated_execution_time' => $totalExpectedTime,
            'estimated_memory_usage' => $totalExpectedMemory,
        ];
    }

    /**
     * Create a new suite from existing suite with filtered benchmarks
     */
    public function filterByCategory(string $category): self
    {
        $newSuite = new self(
            $this->name . '_' . $category,
            "Filtered suite: {$this->description} (Category: {$category})",
            $this->config
        );

        foreach ($this->getBenchmarksByCategory($category) as $benchmark) {
            $newSuite->addBenchmark($benchmark);
        }

        return $newSuite;
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'timeout' => 60, // 60 seconds per benchmark
            'memory_limit' => 256 * 1024 * 1024, // 256MB
            'parallel_execution' => false,
            'stop_on_failure' => false,
            'warmup_enabled' => true,
        ];
    }
}