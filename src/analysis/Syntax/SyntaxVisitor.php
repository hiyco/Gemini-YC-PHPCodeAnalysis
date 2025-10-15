<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Syntax Visitor for AST traversal and rule execution
 */

namespace YcPca\Analysis\Syntax;

use PhpParser\Node;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YcPca\Analysis\Issue\Issue;
use YcPca\Ast\BaseVisitor;
use YcPca\Model\FileContext;

/**
 * Visitor for syntax rule execution during AST traversal
 * 
 * Features:
 * - Rule-based validation
 * - Issue collection
 * - Performance tracking
 * - Context awareness
 */
class SyntaxVisitor extends BaseVisitor
{
    private SyntaxRuleEngine $ruleEngine;
    private FileContext $context;
    
    /** @var Issue[] */
    private array $collectedIssues = [];
    
    private int $nodesVisited = 0;
    private array $nodeTypeStats = [];
    private array $ruleExecutionStats = [];

    public function __construct(
        SyntaxRuleEngine $ruleEngine,
        FileContext $context,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
        
        $this->ruleEngine = $ruleEngine;
        $this->context = $context;
        
        $this->logger->debug('SyntaxVisitor initialized', [
            'file' => $context->getFilePath(),
            'rules_count' => $ruleEngine->getRuleCount()
        ]);
    }

    /**
     * Visit a node and apply syntax rules
     */
    public function visitNode(Node $node): void
    {
        $this->nodesVisited++;
        $nodeType = $node->getType();
        
        // Update node statistics
        $this->nodeTypeStats[$nodeType] = ($this->nodeTypeStats[$nodeType] ?? 0) + 1;
        
        $this->logger->debug('Visiting node', [
            'type' => $nodeType,
            'line' => $node->getStartLine(),
            'node_count' => $this->nodesVisited
        ]);
        
        // Validate node against all applicable rules
        $startTime = microtime(true);
        
        try {
            $issues = $this->ruleEngine->validateNode($node, $this->context);
            
            // Add any found issues to our collection
            foreach ($issues as $issue) {
                $this->addIssue($issue);
            }
            
            $executionTime = microtime(true) - $startTime;
            
            // Update rule execution statistics
            $this->updateRuleStats($nodeType, count($issues), $executionTime);
            
            if (!empty($issues)) {
                $this->logger->info('Issues found for node', [
                    'type' => $nodeType,
                    'line' => $node->getStartLine(),
                    'issue_count' => count($issues)
                ]);
            }
            
        } catch (\Throwable $e) {
            $this->logger->error('Error validating node', [
                'type' => $nodeType,
                'line' => $node->getStartLine(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Override node entry handling
     */
    protected function onEnterNode(Node $node): ?Node
    {
        // Add parent reference for rule context
        if (!empty($this->contextStack)) {
            $parent = $this->getCurrentContext()['node'] ?? null;
            if ($parent instanceof Node) {
                $node->setAttribute('parent', $parent);
            }
        }
        
        // Visit node for rule validation
        $this->visitNode($node);
        
        return null; // Don't modify the node
    }

    /**
     * Get all collected issues
     */
    public function getCollectedIssues(): array
    {
        return $this->collectedIssues;
    }

    /**
     * Get number of nodes visited
     */
    public function getNodesVisited(): int
    {
        return $this->nodesVisited;
    }

    /**
     * Get node type statistics
     */
    public function getNodeTypeStats(): array
    {
        return $this->nodeTypeStats;
    }

    /**
     * Get rule execution statistics
     */
    public function getRuleExecutionStats(): array
    {
        return $this->ruleExecutionStats;
    }

    /**
     * Get comprehensive visitor statistics
     */
    public function getVisitorStats(): array
    {
        $baseStats = $this->getStats();
        
        return array_merge($baseStats, [
            'issues_found' => count($this->collectedIssues),
            'nodes_visited' => $this->nodesVisited,
            'unique_node_types' => count($this->nodeTypeStats),
            'node_type_distribution' => $this->getNodeTypeDistribution(),
            'issues_by_severity' => $this->getIssuesBySeverity(),
            'issues_by_category' => $this->getIssuesByCategory(),
            'rule_execution_stats' => $this->ruleExecutionStats
        ]);
    }

    /**
     * Reset visitor state
     */
    public function resetVisitor(): self
    {
        $this->collectedIssues = [];
        $this->nodesVisited = 0;
        $this->nodeTypeStats = [];
        $this->ruleExecutionStats = [];
        
        $this->resetState(); // Reset base visitor state
        
        return $this;
    }

    /**
     * Add issue to collection
     */
    private function addIssue(Issue $issue): void
    {
        $this->collectedIssues[] = $issue;
        
        $this->logger->debug('Issue added', [
            'rule_id' => $issue->getRuleId(),
            'severity' => $issue->getSeverity(),
            'category' => $issue->getCategory(),
            'line' => $issue->getLine(),
            'title' => $issue->getTitle()
        ]);
    }

    /**
     * Update rule execution statistics
     */
    private function updateRuleStats(string $nodeType, int $issueCount, float $executionTime): void
    {
        if (!isset($this->ruleExecutionStats[$nodeType])) {
            $this->ruleExecutionStats[$nodeType] = [
                'executions' => 0,
                'total_issues' => 0,
                'total_time' => 0.0,
                'avg_time' => 0.0
            ];
        }
        
        $stats = &$this->ruleExecutionStats[$nodeType];
        $stats['executions']++;
        $stats['total_issues'] += $issueCount;
        $stats['total_time'] += $executionTime;
        $stats['avg_time'] = $stats['total_time'] / $stats['executions'];
    }

    /**
     * Get node type distribution as percentages
     */
    private function getNodeTypeDistribution(): array
    {
        if ($this->nodesVisited === 0) {
            return [];
        }
        
        $distribution = [];
        
        foreach ($this->nodeTypeStats as $nodeType => $count) {
            $percentage = round(($count / $this->nodesVisited) * 100, 2);
            $distribution[$nodeType] = [
                'count' => $count,
                'percentage' => $percentage
            ];
        }
        
        // Sort by count descending
        uasort($distribution, fn($a, $b) => $b['count'] - $a['count']);
        
        return $distribution;
    }

    /**
     * Get issues grouped by severity
     */
    private function getIssuesBySeverity(): array
    {
        $groups = [];
        
        foreach (Issue::getValidSeverities() as $severity) {
            $groups[$severity] = 0;
        }
        
        foreach ($this->collectedIssues as $issue) {
            $groups[$issue->getSeverity()]++;
        }
        
        return $groups;
    }

    /**
     * Get issues grouped by category
     */
    private function getIssuesByCategory(): array
    {
        $groups = [];
        
        foreach (Issue::getValidCategories() as $category) {
            $groups[$category] = 0;
        }
        
        foreach ($this->collectedIssues as $issue) {
            $groups[$issue->getCategory()]++;
        }
        
        return $groups;
    }
}