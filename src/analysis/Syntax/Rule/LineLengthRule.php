<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Line Length Validation Rule
 */

namespace YcPca\Analysis\Syntax\Rule;

use PhpParser\Node;
use YcPca\Analysis\Issue\Issue;
use YcPca\Model\FileContext;

/**
 * Rule to validate line length limits
 * 
 * Features:
 * - Configurable line length limit
 * - Exception for specific patterns
 * - Detailed suggestions
 */
class LineLengthRule extends AbstractSyntaxRule
{
    protected array $supportedNodeTypes = ['*']; // Applies to all nodes

    public function getRuleId(): string
    {
        return 'line_length';
    }

    public function getRuleName(): string
    {
        return 'Line Length Limit';
    }

    public function getDescription(): string
    {
        return 'Validates that lines do not exceed the configured maximum length';
    }

    public function getCategory(): string
    {
        return Issue::CATEGORY_STYLE;
    }

    public function getSeverity(): string
    {
        return Issue::SEVERITY_LOW;
    }

    public function appliesToNodeType(string $nodeType): bool
    {
        // This rule applies to all node types since we check line content
        return true;
    }

    public function validate(Node $node, FileContext $context): array
    {
        $issues = [];
        $maxLength = $this->getConfigValue('max_length', 120);
        
        // Only check the starting line of each node to avoid duplicates
        $lineNumber = $node->getStartLine();
        $lineContent = $context->getLine($lineNumber);
        
        if ($lineContent === null) {
            return $issues;
        }
        
        $actualLength = strlen($lineContent);
        
        if ($actualLength > $maxLength) {
            // Check for exceptions
            if ($this->hasExceptions($lineContent)) {
                return $issues;
            }
            
            $overageLength = $actualLength - $maxLength;
            
            $issue = $this->createIssue(
                title: "Line too long ({$actualLength} characters)",
                description: "Line exceeds maximum length of {$maxLength} characters by {$overageLength} characters.",
                node: $node,
                suggestions: $this->generateSuggestions($lineContent, $maxLength),
                codeSnippet: $this->getCodeSnippet($node, $context, 0), // Just the current line
                metadata: [
                    'actual_length' => $actualLength,
                    'max_length' => $maxLength,
                    'overage' => $overageLength,
                    'line_content_preview' => substr($lineContent, 0, 50) . '...'
                ]
            );
            
            $issues[] = $issue;
        }
        
        return $issues;
    }

    public function getPriority(): int
    {
        return 30; // Lower priority for style issues
    }

    public function getTags(): array
    {
        return ['syntax', 'style', 'readability'];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];
        
        if (isset($config['max_length'])) {
            if (!is_int($config['max_length']) || $config['max_length'] <= 0) {
                $errors[] = 'max_length must be a positive integer';
            } elseif ($config['max_length'] < 80) {
                $errors[] = 'max_length should not be less than 80 characters';
            }
        }
        
        if (isset($config['exceptions']) && !is_array($config['exceptions'])) {
            $errors[] = 'exceptions must be an array of patterns';
        }
        
        return $errors;
    }

    protected function getDefaultConfig(): array
    {
        return [
            'max_length' => 120,
            'exceptions' => [
                // Common patterns that can be longer
                '/^\s*\/\/.*$/',  // Single-line comments
                '/^\s*\*.*$/',    // Multi-line comment lines
                '/^\s*use\s+/',   // Use statements
                '/https?:\/\//',  // URLs
                '/^\s*\'[^\']*\'.*$/', // Long string literals
                '/^\s*"[^"]*".*$/',    // Long string literals
            ],
            'ignore_whitespace_lines' => true
        ];
    }

    /**
     * Check if line has exceptions that allow it to be longer
     */
    private function hasExceptions(string $line): bool
    {
        $exceptions = $this->getConfigValue('exceptions', []);
        $ignoreWhitespace = $this->getConfigValue('ignore_whitespace_lines', true);
        
        // Ignore lines with only whitespace
        if ($ignoreWhitespace && trim($line) === '') {
            return true;
        }
        
        foreach ($exceptions as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate helpful suggestions for fixing long lines
     */
    private function generateSuggestions(string $line, int $maxLength): array
    {
        $suggestions = [
            'Break the line at a logical point (after operators, commas, etc.)',
            'Extract complex expressions into variables',
            'Consider refactoring to reduce complexity'
        ];
        
        // Specific suggestions based on line content
        if (str_contains($line, '&&') || str_contains($line, '||')) {
            $suggestions[] = 'Break logical conditions across multiple lines';
        }
        
        if (str_contains($line, '->') && substr_count($line, '->') > 2) {
            $suggestions[] = 'Break method chaining across multiple lines';
        }
        
        if (str_contains($line, ',') && substr_count($line, ',') > 3) {
            $suggestions[] = 'Break function arguments or array elements across multiple lines';
        }
        
        if (preg_match('/\s*function\s+\w+\s*\([^)]*\)\s*\{/', $line)) {
            $suggestions[] = 'Break function signature across multiple lines';
        }
        
        if (str_contains($line, 'if') && str_contains($line, '(')) {
            $suggestions[] = 'Break complex if conditions across multiple lines';
        }
        
        return $suggestions;
    }
}