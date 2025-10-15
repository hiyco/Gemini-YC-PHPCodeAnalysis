<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Interface for security validation rules
 */

namespace YcPca\Analysis\Security\Rule;

use PhpParser\Node;
use YcPca\Analysis\Issue\Issue;
use YcPca\Model\FileContext;

/**
 * Interface for security validation rules
 * 
 * Features:
 * - OWASP Top 10 compliance
 * - Risk-based vulnerability assessment
 * - Context-aware security analysis
 * - Configurable rule behavior
 */
interface SecurityRuleInterface
{
    /**
     * Get unique rule identifier
     */
    public function getRuleId(): string;

    /**
     * Get human-readable rule name
     */
    public function getRuleName(): string;

    /**
     * Get rule description
     */
    public function getDescription(): string;

    /**
     * Get vulnerability type this rule detects
     * (e.g., 'sql_injection', 'xss', 'csrf', 'authentication')
     */
    public function getVulnerabilityType(): string;

    /**
     * Get OWASP Top 10 category
     * (e.g., 'A01_injection', 'A02_broken_authentication')
     */
    public function getOwaspCategory(): string;

    /**
     * Get risk level for this vulnerability type
     * (critical, high, medium, low, info)
     */
    public function getRiskLevel(): string;

    /**
     * Get CWE (Common Weakness Enumeration) IDs
     */
    public function getCweIds(): array;

    /**
     * Get supported node types for AST analysis
     */
    public function getSupportedNodeTypes(): array;

    /**
     * Check if rule applies to given node type
     */
    public function appliesToNodeType(string $nodeType): bool;

    /**
     * Validate node for security vulnerabilities
     * 
     * @param Node $node AST node to validate
     * @param FileContext $context File and project context
     * @return Issue[] Array of security issues found
     */
    public function validate(Node $node, FileContext $context): array;

    /**
     * Check if rule is enabled
     */
    public function isEnabled(): bool;

    /**
     * Enable/disable rule
     */
    public function setEnabled(bool $enabled): self;

    /**
     * Get rule configuration
     */
    public function getConfig(): array;

    /**
     * Set rule configuration
     */
    public function setConfig(array $config): self;

    /**
     * Get rule priority (higher runs first)
     */
    public function getPriority(): int;

    /**
     * Get rule tags for categorization
     */
    public function getTags(): array;

    /**
     * Validate rule configuration
     * 
     * @param array $config Configuration to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfig(array $config): array;

    /**
     * Reset rule state
     */
    public function reset(): self;

    /**
     * Get remediation suggestions
     */
    public function getRemediationSuggestions(): array;

    /**
     * Get security best practices related to this rule
     */
    public function getBestPractices(): array;

    /**
     * Check if vulnerability is context-dependent
     */
    public function isContextDependent(): bool;

    /**
     * Get false positive probability
     * (0.0 = never false positive, 1.0 = always false positive)
     */
    public function getFalsePositiveProbability(): float;

    /**
     * Get performance impact of running this rule
     * (low, medium, high)
     */
    public function getPerformanceImpact(): string;
}