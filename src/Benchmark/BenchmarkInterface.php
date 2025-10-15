<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Interface for performance benchmarks
 */

namespace YcPca\Benchmark;

use YcPca\Ast\PhpAstParser;
use YcPca\Analysis\AnalysisEngine;

/**
 * Interface for all performance benchmark implementations
 * 
 * Defines the contract for benchmarks that measure system performance
 */
interface BenchmarkInterface
{
    /**
     * Get unique benchmark name
     */
    public function getName(): string;

    /**
     * Get benchmark description
     */
    public function getDescription(): string;

    /**
     * Get benchmark category
     */
    public function getCategory(): string;

    /**
     * Set up benchmark data and environment
     */
    public function setUp(): void;

    /**
     * Clean up after benchmark execution
     */
    public function tearDown(): void;

    /**
     * Execute the benchmark and return result data
     * 
     * @param PhpAstParser $astParser Parser instance for AST operations
     * @param AnalysisEngine $analysisEngine Analysis engine for processing
     * @return mixed Benchmark execution result
     */
    public function execute(PhpAstParser $astParser, AnalysisEngine $analysisEngine): mixed;

    /**
     * Get expected execution time in seconds (for validation)
     */
    public function getExpectedExecutionTime(): float;

    /**
     * Get expected memory usage in bytes (for validation)
     */
    public function getExpectedMemoryUsage(): int;

    /**
     * Check if benchmark is enabled
     */
    public function isEnabled(): bool;

    /**
     * Enable or disable the benchmark
     */
    public function setEnabled(bool $enabled): self;
}