<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Abstract base class for syntax rules
 */

namespace YcPca\Analysis\Syntax\Rule;

use PhpParser\Node;
use YcPca\Analysis\Issue\Issue;
use YcPca\Model\FileContext;

/**
 * Abstract base class providing common functionality for syntax rules
 * 
 * Features:
 * - Configuration management
 * - Issue creation helpers
 * - Node utilities
 */
abstract class AbstractSyntaxRule implements SyntaxRuleInterface
{
    protected bool $enabled = true;
    protected array $config = [];
    protected array $supportedNodeTypes = [];
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Check if rule applies to given node type
     */
    public function appliesToNodeType(string $nodeType): bool
    {
        return in_array($nodeType, $this->getSupportedNodeTypes(), true);
    }

    /**
     * Get supported node types
     */
    public function getSupportedNodeTypes(): array
    {
        return $this->supportedNodeTypes;
    }

    /**
     * Check if rule is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable/disable rule
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Get rule configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set rule configuration
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Get rule priority (higher runs first)
     */
    public function getPriority(): int
    {
        return 50; // Default priority
    }

    /**
     * Get rule tags
     */
    public function getTags(): array
    {
        return ['syntax'];
    }

    /**
     * Validate rule configuration
     */
    public function validateConfig(array $config): array
    {
        // Default implementation - no validation errors
        return [];
    }

    /**
     * Reset rule state
     */
    public function reset(): self
    {
        // Default implementation - nothing to reset
        return $this;
    }

    /**
     * Create issue for this rule
     */
    protected function createIssue(
        string $title,
        string $description,
        Node $node,
        array $suggestions = [],
        ?string $codeSnippet = null,
        array $metadata = []
    ): Issue {
        $issueId = $this->getRuleId() . '_' . $node->getStartLine() . '_' . md5($description);
        
        return new Issue(
            id: $issueId,
            title: $title,
            description: $description,
            severity: $this->getSeverity(),
            category: $this->getCategory(),
            line: $node->getStartLine(),
            column: $node->getStartColumn(),
            endLine: $node->getEndLine(),
            endColumn: $node->getEndColumn(),
            ruleId: $this->getRuleId(),
            ruleName: $this->getRuleName(),
            tags: $this->getTags(),
            suggestions: $suggestions,
            codeSnippet: $codeSnippet,
            metadata: array_merge(['rule_category' => $this->getCategory()], $metadata)
        );
    }

    /**
     * Get code snippet from node
     */
    protected function getCodeSnippet(Node $node, FileContext $context, int $extraLines = 2): string
    {
        $startLine = max(1, $node->getStartLine() - $extraLines);
        $endLine = min(count($context->getLines()), ($node->getEndLine() ?? $node->getStartLine()) + $extraLines);
        
        $lines = [];
        for ($i = $startLine; $i <= $endLine; $i++) {
            $line = $context->getLine($i);
            $prefix = ($i === $node->getStartLine()) ? '>>> ' : '    ';
            $lines[] = sprintf('%s%d: %s', $prefix, $i, $line);
        }
        
        return implode("\n", $lines);
    }

    /**
     * Check if node has specific attribute
     */
    protected function nodeHasAttribute(Node $node, string $attribute): bool
    {
        return $node->hasAttribute($attribute);
    }

    /**
     * Get node attribute value
     */
    protected function getNodeAttribute(Node $node, string $attribute, mixed $default = null): mixed
    {
        return $node->getAttribute($attribute, $default);
    }

    /**
     * Check if node is inside specific parent type
     */
    protected function isInsideNodeType(Node $node, string $parentType): bool
    {
        $parent = $node->getAttribute('parent');
        
        while ($parent !== null) {
            if ($parent instanceof Node && $parent->getType() === $parentType) {
                return true;
            }
            $parent = $parent->getAttribute('parent');
        }
        
        return false;
    }

    /**
     * Get configuration value with default
     */
    protected function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Check if configuration value is enabled
     */
    protected function isConfigEnabled(string $key): bool
    {
        return (bool) $this->getConfigValue($key, false);
    }

    /**
     * Get default configuration - to be overridden by subclasses
     */
    protected function getDefaultConfig(): array
    {
        return [];
    }

    /**
     * Format node type for display
     */
    protected function formatNodeType(string $nodeType): string
    {
        // Convert "Expr_BinaryOp_Plus" to "Binary Operation (Plus)"
        $parts = explode('_', $nodeType);
        $formatted = [];
        
        foreach ($parts as $part) {
            $formatted[] = ucfirst(strtolower($part));
        }
        
        return implode(' ', $formatted);
    }

    /**
     * Get line content for node
     */
    protected function getLineContent(Node $node, FileContext $context): string
    {
        return $context->getLine($node->getStartLine()) ?? '';
    }

    /**
     * Check if line exceeds maximum length
     */
    protected function isLineTooLong(string $line, int $maxLength): bool
    {
        return strlen($line) > $maxLength;
    }

    /**
     * Count leading whitespace
     */
    protected function countLeadingWhitespace(string $line): int
    {
        return strlen($line) - strlen(ltrim($line));
    }

    /**
     * Check if line has trailing whitespace
     */
    protected function hasTrailingWhitespace(string $line): bool
    {
        return $line !== rtrim($line);
    }
}