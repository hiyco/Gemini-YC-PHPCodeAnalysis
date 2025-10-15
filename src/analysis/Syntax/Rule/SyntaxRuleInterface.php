<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Interface for syntax validation rules
 */

namespace YcPca\Analysis\Syntax\Rule;

use PhpParser\Node;
use YcPca\Analysis\Issue\Issue;
use YcPca\Model\FileContext;

/**
 * Interface for all syntax validation rules
 * 
 * Defines the contract for syntax rules:
 * - Node type support
 * - Issue detection
 * - Configuration
 */
interface SyntaxRuleInterface
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
     * Get rule category
     */
    public function getCategory(): string;

    /**
     * Get rule severity level
     */
    public function getSeverity(): string;

    /**
     * Check if rule applies to given node type
     */
    public function appliesToNodeType(string $nodeType): bool;

    /**
     * Get supported node types
     */
    public function getSupportedNodeTypes(): array;

    /**
     * Validate node and return issues if any
     * 
     * @return Issue[]
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
     * Get rule tags
     */
    public function getTags(): array;

    /**
     * Validate rule configuration
     */
    public function validateConfig(array $config): array;

    /**
     * Reset rule state
     */
    public function reset(): self;
}