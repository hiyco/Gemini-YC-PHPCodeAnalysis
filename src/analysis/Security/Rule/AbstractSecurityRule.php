<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Abstract base class for security rules
 */

namespace YcPca\Analysis\Security\Rule;

use PhpParser\Node;
use YcPca\Analysis\Issue\Issue;
use YcPca\Model\FileContext;

/**
 * Abstract base class providing common functionality for security rules
 * 
 * Features:
 * - OWASP Top 10 compliance framework
 * - Risk-based vulnerability assessment
 * - Context-aware security analysis
 * - Configuration management
 */
abstract class AbstractSecurityRule implements SecurityRuleInterface
{
    protected bool $enabled = true;
    protected array $config = [];
    protected array $supportedNodeTypes = [];
    
    // Security-specific properties
    protected string $vulnerabilityType = 'unknown';
    protected string $owaspCategory = 'other';
    protected string $riskLevel = Issue::SEVERITY_MEDIUM;
    protected array $cweIds = [];
    protected bool $contextDependent = true;
    protected float $falsePositiveProbability = 0.1;
    protected string $performanceImpact = 'medium';
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->initializeSecurityProperties();
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
     * Get vulnerability type
     */
    public function getVulnerabilityType(): string
    {
        return $this->vulnerabilityType;
    }

    /**
     * Get OWASP category
     */
    public function getOwaspCategory(): string
    {
        return $this->owaspCategory;
    }

    /**
     * Get risk level
     */
    public function getRiskLevel(): string
    {
        return $this->riskLevel;
    }

    /**
     * Get CWE IDs
     */
    public function getCweIds(): array
    {
        return $this->cweIds;
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
        // Security rules have higher base priority
        return match ($this->riskLevel) {
            Issue::SEVERITY_CRITICAL => 100,
            Issue::SEVERITY_HIGH => 80,
            Issue::SEVERITY_MEDIUM => 60,
            Issue::SEVERITY_LOW => 40,
            Issue::SEVERITY_INFO => 20,
            default => 50
        };
    }

    /**
     * Get rule tags
     */
    public function getTags(): array
    {
        return array_merge(
            ['security', $this->vulnerabilityType],
            $this->getOwaspTags(),
            $this->getCweTags()
        );
    }

    /**
     * Validate rule configuration
     */
    public function validateConfig(array $config): array
    {
        $errors = [];
        
        // Validate security-specific configurations
        if (isset($config['risk_threshold']) && 
            !in_array($config['risk_threshold'], ['info', 'low', 'medium', 'high', 'critical'], true)) {
            $errors[] = 'risk_threshold must be one of: info, low, medium, high, critical';
        }
        
        if (isset($config['strict_mode']) && !is_bool($config['strict_mode'])) {
            $errors[] = 'strict_mode must be a boolean';
        }
        
        return $errors;
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
     * Get remediation suggestions
     */
    public function getRemediationSuggestions(): array
    {
        return $this->getDefaultRemediationSuggestions();
    }

    /**
     * Get security best practices
     */
    public function getBestPractices(): array
    {
        return $this->getDefaultBestPractices();
    }

    /**
     * Check if vulnerability is context-dependent
     */
    public function isContextDependent(): bool
    {
        return $this->contextDependent;
    }

    /**
     * Get false positive probability
     */
    public function getFalsePositiveProbability(): float
    {
        return $this->falsePositiveProbability;
    }

    /**
     * Get performance impact
     */
    public function getPerformanceImpact(): string
    {
        return $this->performanceImpact;
    }

    /**
     * Create security issue for this rule
     */
    protected function createSecurityIssue(
        string $title,
        string $description,
        Node $node,
        array $suggestions = [],
        ?string $codeSnippet = null,
        array $metadata = []
    ): Issue {
        $issueId = $this->getRuleId() . '_' . $node->getStartLine() . '_' . md5($description);
        
        // Add security-specific metadata
        $securityMetadata = array_merge([
            'vulnerability_type' => $this->getVulnerabilityType(),
            'owasp_category' => $this->getOwaspCategory(),
            'cwe_ids' => $this->getCweIds(),
            'risk_level' => $this->getRiskLevel(),
            'context_dependent' => $this->isContextDependent(),
            'false_positive_probability' => $this->getFalsePositiveProbability(),
            'performance_impact' => $this->getPerformanceImpact(),
            'remediation_suggestions' => $this->getRemediationSuggestions(),
            'best_practices' => $this->getBestPractices()
        ], $metadata);
        
        return new Issue(
            id: $issueId,
            title: $title,
            description: $description,
            severity: $this->getRiskLevel(),
            category: Issue::CATEGORY_SECURITY,
            line: $node->getStartLine(),
            column: $node->getStartColumn(),
            endLine: $node->getEndLine(),
            endColumn: $node->getEndColumn(),
            ruleId: $this->getRuleId(),
            ruleName: $this->getRuleName(),
            tags: $this->getTags(),
            suggestions: array_merge($suggestions, $this->getRemediationSuggestions()),
            codeSnippet: $codeSnippet ?? $this->getCodeSnippet($node, $context ?? new FileContext(''), 2),
            metadata: $securityMetadata
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
            $prefix = ($i === $node->getStartLine()) ? '⚠️  ' : '    ';
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
     * Check if node is inside specific security context
     */
    protected function isInSecurityContext(Node $node, string $contextType): bool
    {
        $securityContext = $node->getAttribute('security_context', []);
        return isset($securityContext[$contextType]) && $securityContext[$contextType];
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
     * Check if risk level meets threshold
     */
    protected function meetsRiskThreshold(string $riskLevel): bool
    {
        $threshold = $this->getConfigValue('risk_threshold', 'medium');
        $riskOrder = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        
        $riskScore = $riskOrder[$riskLevel] ?? 2;
        $thresholdScore = $riskOrder[$threshold] ?? 2;
        
        return $riskScore >= $thresholdScore;
    }

    /**
     * Check if node contains sensitive data patterns
     */
    protected function containsSensitiveData(Node $node): bool
    {
        if ($node instanceof Node\Scalar\String) {
            $value = strtolower($node->value);
            $sensitivePatterns = [
                'password', 'passwd', 'secret', 'key', 'token', 'api_key',
                'credential', 'auth', 'session', 'cookie', 'private'
            ];
            
            foreach ($sensitivePatterns as $pattern) {
                if (strpos($value, $pattern) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if function call is potentially dangerous
     */
    protected function isDangerousFunction(Node\Expr\FuncCall $funcCall): bool
    {
        if (!($funcCall->name instanceof Node\Name)) {
            return false;
        }
        
        $funcName = strtolower($funcCall->name->toString());
        $dangerousFunctions = [
            'eval', 'exec', 'system', 'shell_exec', 'passthru', 'proc_open',
            'mysql_query', 'mysqli_query', 'file_get_contents', 'file_put_contents',
            'unserialize', 'serialize', 'var_dump', 'print_r', 'extract'
        ];
        
        return in_array($funcName, $dangerousFunctions, true);
    }

    /**
     * Initialize security-specific properties - to be overridden
     */
    protected function initializeSecurityProperties(): void
    {
        // Override in subclasses to set specific properties
    }

    /**
     * Get default configuration - to be overridden by subclasses
     */
    protected function getDefaultConfig(): array
    {
        return [
            'strict_mode' => false,
            'risk_threshold' => 'medium',
            'context_aware' => true,
            'exclude_test_files' => true,
            'exclude_vendor' => true
        ];
    }

    /**
     * Get default remediation suggestions
     */
    protected function getDefaultRemediationSuggestions(): array
    {
        return [
            'Review the code for potential security vulnerabilities',
            'Follow OWASP security guidelines',
            'Implement proper input validation',
            'Use secure coding practices'
        ];
    }

    /**
     * Get default best practices
     */
    protected function getDefaultBestPractices(): array
    {
        return [
            'Always validate and sanitize user input',
            'Use prepared statements for database queries',
            'Implement proper authentication and authorization',
            'Keep dependencies and frameworks up to date',
            'Follow the principle of least privilege'
        ];
    }

    /**
     * Get OWASP-related tags
     */
    private function getOwaspTags(): array
    {
        $tags = [];
        if ($this->owaspCategory !== 'other') {
            $tags[] = 'owasp';
            $tags[] = $this->owaspCategory;
        }
        return $tags;
    }

    /**
     * Get CWE-related tags
     */
    private function getCweTags(): array
    {
        $tags = [];
        foreach ($this->cweIds as $cweId) {
            $tags[] = "cwe-{$cweId}";
        }
        return $tags;
    }
}