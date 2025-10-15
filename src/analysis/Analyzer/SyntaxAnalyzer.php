<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Syntax Analyzer for PHP code syntax validation
 */

namespace YcPca\Analysis\Analyzer;

use PhpParser\Node;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YcPca\Analysis\Issue\Issue;
use YcPca\Analysis\Syntax\SyntaxRuleEngine;
use YcPca\Analysis\Syntax\Rule\SyntaxRuleInterface;
use YcPca\Ast\BaseVisitor;
use YcPca\Model\AnalysisResult;

/**
 * Syntax analyzer implementing comprehensive PHP syntax validation
 * 
 * Features:
 * - PHP 8.x syntax support
 * - Configurable rule engine
 * - Performance optimizations
 * - Detailed error reporting
 */
class SyntaxAnalyzer implements AnalyzerInterface
{
    private const NAME = 'syntax';
    private const VERSION = '1.0.0';
    private const DESCRIPTION = 'PHP syntax validation and compliance checker';

    private LoggerInterface $logger;
    private SyntaxRuleEngine $ruleEngine;
    private array $config;
    private bool $enabled = true;

    public function __construct(
        ?SyntaxRuleEngine $ruleEngine = null,
        ?LoggerInterface $logger = null,
        array $config = []
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->ruleEngine = $ruleEngine ?? new SyntaxRuleEngine($this->logger);
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        $this->initializeRules();
        
        $this->logger->info('SyntaxAnalyzer initialized', [
            'version' => self::VERSION,
            'rules_count' => $this->ruleEngine->getRuleCount()
        ]);
    }

    /**
     * Get analyzer unique name
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Get analyzer version
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Get analyzer description
     */
    public function getDescription(): string
    {
        return self::DESCRIPTION;
    }

    /**
     * Analyze parsed PHP code and return syntax issues
     */
    public function analyze(AnalysisResult $parseResult): AnalyzerResult
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $this->logger->info('Starting syntax analysis', [
            'file' => $parseResult->getFilePath(),
            'node_count' => count($parseResult->getAst())
        ]);
        
        $result = new AnalyzerResult(
            analyzerName: $this->getName(),
            filePath: $parseResult->getFilePath()
        );
        
        try {
            // Check for parse errors first
            if ($parseResult->hasErrors()) {
                foreach ($parseResult->getErrors() as $error) {
                    $issue = $this->createParseErrorIssue($error);
                    $result->addIssue($issue);
                }
            }
            
            // Only run syntax rules if we have a valid AST
            if (!empty($parseResult->getAst()) && $this->enabled) {
                $this->runSyntaxRules($parseResult, $result);
            }
            
            $executionTime = microtime(true) - $startTime;
            $memoryUsage = memory_get_usage(true) - $startMemory;
            
            $result->setExecutionTime($executionTime);
            $result->setMemoryUsage($memoryUsage);
            
            $this->logger->info('Syntax analysis completed', [
                'file' => $parseResult->getFilePath(),
                'issues_found' => $result->getIssueCount(),
                'duration' => $executionTime,
                'memory_used' => $memoryUsage
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->error('Syntax analysis failed', [
                'file' => $parseResult->getFilePath(),
                'error' => $e->getMessage()
            ]);
            
            $result->addError($e->getMessage());
        }
        
        return $result;
    }

    /**
     * Check if analyzer supports error recovery
     */
    public function supportsErrorRecovery(): bool
    {
        return true; // Can analyze partial ASTs
    }

    /**
     * Check if analyzer supports specific file type
     */
    public function supportsFileType(string $extension): bool
    {
        return in_array(strtolower($extension), ['php', 'phtml', 'php5', 'php7', 'php8'], true);
    }

    /**
     * Get supported file extensions
     */
    public function getSupportedExtensions(): array
    {
        return ['php', 'phtml', 'php5', 'php7', 'php8'];
    }

    /**
     * Get analyzer configuration requirements
     */
    public function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'strict_types' => ['type' => 'boolean', 'default' => true],
                'php_version' => ['type' => 'string', 'default' => '8.1'],
                'max_line_length' => ['type' => 'integer', 'default' => 120],
                'require_declare_strict' => ['type' => 'boolean', 'default' => false],
                'check_unused_variables' => ['type' => 'boolean', 'default' => true],
                'check_unused_functions' => ['type' => 'boolean', 'default' => true],
                'validate_docblocks' => ['type' => 'boolean', 'default' => false]
            ]
        ];
    }

    /**
     * Set analyzer configuration
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        
        // Reinitialize rules with new config
        $this->initializeRules();
        
        $this->logger->info('Configuration updated', [
            'analyzer' => $this->getName()
        ]);
        
        return $this;
    }

    /**
     * Get current analyzer configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if analyzer is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable/disable analyzer
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        
        $this->logger->info('Analyzer ' . ($enabled ? 'enabled' : 'disabled'), [
            'analyzer' => $this->getName()
        ]);
        
        return $this;
    }

    /**
     * Get analyzer priority (higher runs first)
     */
    public function getPriority(): int
    {
        return 100; // High priority for syntax checking
    }

    /**
     * Get analyzer categories/tags
     */
    public function getCategories(): array
    {
        return ['syntax', 'validation', 'quality'];
    }

    /**
     * Validate analyzer configuration
     */
    public function validateConfig(array $config): array
    {
        $errors = [];
        
        if (isset($config['max_line_length']) && (!is_int($config['max_line_length']) || $config['max_line_length'] <= 0)) {
            $errors[] = 'max_line_length must be a positive integer';
        }
        
        if (isset($config['php_version']) && !preg_match('/^\d+\.\d+$/', $config['php_version'])) {
            $errors[] = 'php_version must be in format "X.Y"';
        }
        
        return $errors;
    }

    /**
     * Reset analyzer state
     */
    public function reset(): self
    {
        $this->ruleEngine->reset();
        return $this;
    }

    /**
     * Add custom syntax rule
     */
    public function addRule(SyntaxRuleInterface $rule): self
    {
        $this->ruleEngine->addRule($rule);
        return $this;
    }

    /**
     * Remove syntax rule by name
     */
    public function removeRule(string $ruleName): self
    {
        $this->ruleEngine->removeRule($ruleName);
        return $this;
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'strict_types' => true,
            'php_version' => '8.1',
            'max_line_length' => 120,
            'require_declare_strict' => false,
            'check_unused_variables' => true,
            'check_unused_functions' => true,
            'validate_docblocks' => false
        ];
    }

    /**
     * Initialize built-in syntax rules
     */
    private function initializeRules(): void
    {
        $this->ruleEngine->loadBuiltinRules($this->config);
    }

    /**
     * Create issue from parse error
     */
    private function createParseErrorIssue(string $error): Issue
    {
        // Extract line number from error message if possible
        preg_match('/line (\d+)/', $error, $matches);
        $line = isset($matches[1]) ? (int) $matches[1] : null;
        
        return new Issue(
            id: 'syntax_parse_error_' . md5($error),
            title: 'Parse Error',
            description: $error,
            severity: Issue::SEVERITY_CRITICAL,
            category: Issue::CATEGORY_SYNTAX,
            line: $line,
            ruleId: 'parse_error',
            ruleName: 'PHP Parse Error',
            tags: ['parse', 'critical'],
            suggestions: [
                'Check syntax near the indicated line',
                'Ensure proper closing of brackets and statements',
                'Verify correct PHP version compatibility'
            ]
        );
    }

    /**
     * Run syntax rules on the AST
     */
    private function runSyntaxRules(AnalysisResult $parseResult, AnalyzerResult $result): void
    {
        $ast = $parseResult->getAst();
        $context = $parseResult->getContext();
        
        // Create visitor for AST traversal
        $visitor = new SyntaxVisitor($this->ruleEngine, $context, $this->logger);
        
        // Set up AST parser with the visitor
        $parser = $parseResult->getContext();
        // Note: In a real implementation, we would use the parser from AnalysisResult
        // For now, we'll simulate the traversal
        
        // Traverse AST and collect issues
        $this->traverseNodes($ast, $visitor, $result);
        
        // Get issues from the visitor
        $issues = $visitor->getCollectedIssues();
        foreach ($issues as $issue) {
            $result->addIssue($issue);
        }
        
        // Add metadata
        $result->addMetadata('rules_executed', $this->ruleEngine->getRuleCount());
        $result->addMetadata('nodes_visited', $visitor->getNodesVisited());
    }

    /**
     * Traverse AST nodes and apply syntax rules
     */
    private function traverseNodes(array $nodes, SyntaxVisitor $visitor, AnalyzerResult $result): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Node) {
                try {
                    $visitor->visitNode($node);
                    
                    // Recursively visit child nodes
                    $this->traverseNodes($node->getSubNodeNames(), $visitor, $result);
                } catch (\Throwable $e) {
                    $this->logger->warning('Error visiting node', [
                        'node_type' => $node->getType(),
                        'error' => $e->getMessage()
                    ]);
                    
                    $result->addWarning("Rule execution error for {$node->getType()}: {$e->getMessage()}");
                }
            }
        }
    }
}