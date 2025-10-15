<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Syntax Rule Engine for managing and executing syntax validation rules
 */

namespace YcPca\Analysis\Syntax;

use PhpParser\Node;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YcPca\Analysis\Issue\Issue;
use YcPca\Analysis\Syntax\Rule\SyntaxRuleInterface;
use YcPca\Model\FileContext;

/**
 * Rule engine for executing syntax validation rules
 * 
 * Features:
 * - Rule management and caching
 * - Performance optimization
 * - Built-in rule loading
 * - Rule execution statistics
 */
class SyntaxRuleEngine
{
    private LoggerInterface $logger;
    
    /** @var SyntaxRuleInterface[] */
    private array $rules = [];
    
    /** @var array<string, SyntaxRuleInterface[]> */
    private array $rulesByNodeType = [];
    
    private array $ruleStats = [
        'rules_executed' => 0,
        'issues_found' => 0,
        'execution_time' => 0.0,
        'rule_cache_hits' => 0
    ];
    
    private bool $cachingEnabled = true;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Add syntax rule
     */
    public function addRule(SyntaxRuleInterface $rule): self
    {
        $ruleId = $rule->getRuleId();
        
        if (isset($this->rules[$ruleId])) {
            $this->logger->warning('Rule already exists, replacing', [
                'rule_id' => $ruleId
            ]);
        }
        
        $this->rules[$ruleId] = $rule;
        
        // Update node type cache
        $this->updateNodeTypeCache($rule);
        
        $this->logger->debug('Rule added', [
            'rule_id' => $ruleId,
            'rule_name' => $rule->getRuleName(),
            'supported_types' => $rule->getSupportedNodeTypes()
        ]);
        
        return $this;
    }

    /**
     * Remove rule by ID
     */
    public function removeRule(string $ruleId): self
    {
        if (!isset($this->rules[$ruleId])) {
            $this->logger->warning('Rule not found for removal', [
                'rule_id' => $ruleId
            ]);
            return $this;
        }
        
        $rule = $this->rules[$ruleId];
        unset($this->rules[$ruleId]);
        
        // Rebuild node type cache
        $this->rebuildNodeTypeCache();
        
        $this->logger->debug('Rule removed', [
            'rule_id' => $ruleId,
            'rule_name' => $rule->getRuleName()
        ]);
        
        return $this;
    }

    /**
     * Get rule by ID
     */
    public function getRule(string $ruleId): ?SyntaxRuleInterface
    {
        return $this->rules[$ruleId] ?? null;
    }

    /**
     * Get all rules
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Get enabled rules
     */
    public function getEnabledRules(): array
    {
        return array_filter($this->rules, fn(SyntaxRuleInterface $rule) => $rule->isEnabled());
    }

    /**
     * Get rule count
     */
    public function getRuleCount(): int
    {
        return count($this->rules);
    }

    /**
     * Get enabled rule count
     */
    public function getEnabledRuleCount(): int
    {
        return count($this->getEnabledRules());
    }

    /**
     * Validate node against all applicable rules
     */
    public function validateNode(Node $node, FileContext $context): array
    {
        $startTime = microtime(true);
        $nodeType = $node->getType();
        $allIssues = [];
        
        // Get rules for this node type
        $applicableRules = $this->getRulesForNodeType($nodeType);
        
        if (empty($applicableRules)) {
            return $allIssues;
        }
        
        $this->logger->debug('Validating node', [
            'node_type' => $nodeType,
            'line' => $node->getStartLine(),
            'applicable_rules' => count($applicableRules)
        ]);
        
        foreach ($applicableRules as $rule) {
            if (!$rule->isEnabled()) {
                continue;
            }
            
            try {
                $issues = $rule->validate($node, $context);
                $allIssues = array_merge($allIssues, $issues);
                
                $this->ruleStats['rules_executed']++;
                $this->ruleStats['issues_found'] += count($issues);
                
                if (!empty($issues)) {
                    $this->logger->debug('Rule found issues', [
                        'rule_id' => $rule->getRuleId(),
                        'node_type' => $nodeType,
                        'line' => $node->getStartLine(),
                        'issue_count' => count($issues)
                    ]);
                }
                
            } catch (\Throwable $e) {
                $this->logger->error('Rule execution failed', [
                    'rule_id' => $rule->getRuleId(),
                    'node_type' => $nodeType,
                    'line' => $node->getStartLine(),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->ruleStats['execution_time'] += microtime(true) - $startTime;
        
        return $allIssues;
    }

    /**
     * Load built-in syntax rules
     */
    public function loadBuiltinRules(array $config = []): self
    {
        $this->logger->info('Loading built-in syntax rules');
        
        // Load rules based on configuration
        $this->loadBasicSyntaxRules($config);
        $this->loadPhpVersionRules($config);
        $this->loadStyleRules($config);
        
        $this->logger->info('Built-in rules loaded', [
            'total_rules' => $this->getRuleCount(),
            'enabled_rules' => $this->getEnabledRuleCount()
        ]);
        
        return $this;
    }

    /**
     * Get rules for specific node type
     */
    public function getRulesForNodeType(string $nodeType): array
    {
        if ($this->cachingEnabled && isset($this->rulesByNodeType[$nodeType])) {
            $this->ruleStats['rule_cache_hits']++;
            return $this->rulesByNodeType[$nodeType];
        }
        
        $applicableRules = [];
        
        foreach ($this->rules as $rule) {
            if ($rule->appliesToNodeType($nodeType)) {
                $applicableRules[] = $rule;
            }
        }
        
        // Sort by priority (higher first)
        usort($applicableRules, fn(SyntaxRuleInterface $a, SyntaxRuleInterface $b) => 
            $b->getPriority() - $a->getPriority()
        );
        
        if ($this->cachingEnabled) {
            $this->rulesByNodeType[$nodeType] = $applicableRules;
        }
        
        return $applicableRules;
    }

    /**
     * Get rule statistics
     */
    public function getStats(): array
    {
        return array_merge($this->ruleStats, [
            'total_rules' => $this->getRuleCount(),
            'enabled_rules' => $this->getEnabledRuleCount(),
            'caching_enabled' => $this->cachingEnabled,
            'cached_node_types' => count($this->rulesByNodeType)
        ]);
    }

    /**
     * Reset rule statistics
     */
    public function resetStats(): self
    {
        $this->ruleStats = array_fill_keys(array_keys($this->ruleStats), 0);
        return $this;
    }

    /**
     * Reset rule engine
     */
    public function reset(): self
    {
        foreach ($this->rules as $rule) {
            $rule->reset();
        }
        
        $this->resetStats();
        $this->clearCache();
        
        return $this;
    }

    /**
     * Enable/disable caching
     */
    public function setCachingEnabled(bool $enabled): self
    {
        $this->cachingEnabled = $enabled;
        
        if (!$enabled) {
            $this->clearCache();
        }
        
        return $this;
    }

    /**
     * Clear rule cache
     */
    public function clearCache(): self
    {
        $this->rulesByNodeType = [];
        return $this;
    }

    /**
     * Update node type cache for a rule
     */
    private function updateNodeTypeCache(SyntaxRuleInterface $rule): void
    {
        if (!$this->cachingEnabled) {
            return;
        }
        
        foreach ($rule->getSupportedNodeTypes() as $nodeType) {
            if (!isset($this->rulesByNodeType[$nodeType])) {
                $this->rulesByNodeType[$nodeType] = [];
            }
            
            // Check if rule is already cached for this node type
            $found = false;
            foreach ($this->rulesByNodeType[$nodeType] as $cachedRule) {
                if ($cachedRule->getRuleId() === $rule->getRuleId()) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $this->rulesByNodeType[$nodeType][] = $rule;
                
                // Re-sort by priority
                usort($this->rulesByNodeType[$nodeType], fn(SyntaxRuleInterface $a, SyntaxRuleInterface $b) => 
                    $b->getPriority() - $a->getPriority()
                );
            }
        }
    }

    /**
     * Rebuild node type cache from scratch
     */
    private function rebuildNodeTypeCache(): void
    {
        if (!$this->cachingEnabled) {
            return;
        }
        
        $this->rulesByNodeType = [];
        
        foreach ($this->rules as $rule) {
            $this->updateNodeTypeCache($rule);
        }
    }

    /**
     * Load basic syntax validation rules
     */
    private function loadBasicSyntaxRules(array $config): void
    {
        // Note: In a complete implementation, these would be actual rule classes
        // For now, we'll create placeholders to show the structure
        
        $this->logger->debug('Loading basic syntax rules');
        
        // Rules would be loaded here based on configuration
        // Example: $this->addRule(new StrictTypesRule($config));
        // Example: $this->addRule(new UnusedVariableRule($config));
        // Example: $this->addRule(new LineLengthRule($config));
    }

    /**
     * Load PHP version specific rules
     */
    private function loadPhpVersionRules(array $config): void
    {
        $phpVersion = $config['php_version'] ?? '8.1';
        
        $this->logger->debug('Loading PHP version rules', [
            'php_version' => $phpVersion
        ]);
        
        // Version-specific rules would be loaded here
        // Example: $this->addRule(new Php81CompatibilityRule($config));
    }

    /**
     * Load coding style rules
     */
    private function loadStyleRules(array $config): void
    {
        if (!($config['validate_style'] ?? false)) {
            return;
        }
        
        $this->logger->debug('Loading style rules');
        
        // Style rules would be loaded here
        // Example: $this->addRule(new IndentationRule($config));
        // Example: $this->addRule(new BraceStyleRule($config));
    }
}