<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Base class for performance benchmarks
 */

namespace YcPca\Benchmark;

use YcPca\Ast\PhpAstParser;
use YcPca\Analysis\AnalysisEngine;

/**
 * Abstract base class for benchmark implementations
 * 
 * Provides common functionality for all benchmarks
 */
abstract class AbstractBenchmark implements BenchmarkInterface
{
    protected string $name;
    protected string $description;
    protected string $category;
    protected bool $enabled = true;
    protected float $expectedExecutionTime = 1.0; // 1 second default
    protected int $expectedMemoryUsage = 10 * 1024 * 1024; // 10MB default
    protected array $metadata = [];

    public function __construct(string $name, string $description, string $category = 'general')
    {
        $this->name = $name;
        $this->description = $description;
        $this->category = $category;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getExpectedExecutionTime(): float
    {
        return $this->expectedExecutionTime;
    }

    public function getExpectedMemoryUsage(): int
    {
        return $this->expectedMemoryUsage;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function setUp(): void
    {
        // Default implementation - can be overridden
    }

    public function tearDown(): void
    {
        // Default implementation - can be overridden
    }

    /**
     * Set expected performance thresholds
     */
    public function setExpectedPerformance(float $executionTime, int $memoryUsage): self
    {
        $this->expectedExecutionTime = $executionTime;
        $this->expectedMemoryUsage = $memoryUsage;
        return $this;
    }

    /**
     * Add metadata to benchmark
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * Get benchmark metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Abstract method that must be implemented by concrete benchmarks
     */
    abstract public function execute(PhpAstParser $astParser, AnalysisEngine $analysisEngine): mixed;
}