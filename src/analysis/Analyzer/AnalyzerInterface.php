<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Analyzer Interface for pluggable analysis components
 */

namespace YcPca\Analysis\Analyzer;

use YcPca\Model\AnalysisResult;

/**
 * Interface for all code analyzers
 * 
 * Defines the contract that all analyzers must implement:
 * - Analysis execution
 * - Capability reporting
 * - Configuration support
 */
interface AnalyzerInterface
{
    /**
     * Get analyzer unique name
     */
    public function getName(): string;

    /**
     * Get analyzer version
     */
    public function getVersion(): string;

    /**
     * Get analyzer description
     */
    public function getDescription(): string;

    /**
     * Analyze parsed PHP code and return results
     */
    public function analyze(AnalysisResult $parseResult): AnalyzerResult;

    /**
     * Check if analyzer supports error recovery
     */
    public function supportsErrorRecovery(): bool;

    /**
     * Check if analyzer supports specific file type
     */
    public function supportsFileType(string $extension): bool;

    /**
     * Get supported file extensions
     */
    public function getSupportedExtensions(): array;

    /**
     * Get analyzer configuration requirements
     */
    public function getConfigSchema(): array;

    /**
     * Set analyzer configuration
     */
    public function setConfig(array $config): self;

    /**
     * Get current analyzer configuration
     */
    public function getConfig(): array;

    /**
     * Check if analyzer is enabled
     */
    public function isEnabled(): bool;

    /**
     * Enable/disable analyzer
     */
    public function setEnabled(bool $enabled): self;

    /**
     * Get analyzer priority (higher runs first)
     */
    public function getPriority(): int;

    /**
     * Get analyzer categories/tags
     */
    public function getCategories(): array;

    /**
     * Validate analyzer configuration
     */
    public function validateConfig(array $config): array;

    /**
     * Reset analyzer state
     */
    public function reset(): self;
}