<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Security Visitor for AST traversal and security rule execution
 */

namespace YcPca\Analysis\Security;

use PhpParser\Node;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YcPca\Analysis\Issue\Issue;
use YcPca\Ast\BaseVisitor;
use YcPca\Model\FileContext;

/**
 * Visitor for security rule execution during AST traversal
 * 
 * Features:
 * - OWASP Top 10 vulnerability detection
 * - Risk-based issue prioritization
 * - Security context tracking
 * - Performance monitoring
 */
class SecurityVisitor extends BaseVisitor
{
    private SecurityRuleEngine $ruleEngine;
    private FileContext $context;
    
    /** @var Issue[] */
    private array $collectedIssues = [];
    
    private int $nodesVisited = 0;
    private array $vulnerabilityStats = [];
    private array $riskLevelStats = [];
    private array $owaspStats = [];
    
    private array $securityContext = [];
    private array $sensitiveDataFlow = [];
    private array $authenticationContext = [];

    public function __construct(
        SecurityRuleEngine $ruleEngine,
        FileContext $context,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
        
        $this->ruleEngine = $ruleEngine;
        $this->context = $context;
        
        $this->initializeSecurityContext();
        
        $this->logger->debug('SecurityVisitor initialized', [
            'file' => $context->getFilePath(),
            'rules_count' => count($ruleEngine->getRules())
        ]);
    }

    /**
     * Visit a node and apply security rules
     */
    public function visitNode(Node $node): void
    {
        $this->nodesVisited++;
        $nodeType = $node->getType();
        
        $this->logger->debug('Visiting node for security analysis', [
            'type' => $nodeType,
            'line' => $node->getStartLine(),
            'node_count' => $this->nodesVisited
        ]);
        
        // Update security context based on node
        $this->updateSecurityContext($node);
        
        // Track sensitive data flow
        $this->trackDataFlow($node);
        
        // Validate node against security rules
        $startTime = microtime(true);
        
        try {
            $issues = $this->ruleEngine->validateNode($node, $this->context);
            
            // Add security context to issues
            foreach ($issues as $issue) {
                $this->enhanceIssueWithContext($issue, $node);
                $this->addIssue($issue);
            }
            
            $executionTime = microtime(true) - $startTime;
            
            // Update statistics
            $this->updateSecurityStats($nodeType, $issues, $executionTime);
            
            if (!empty($issues)) {
                $this->logger->warning('Security vulnerabilities detected', [
                    'type' => $nodeType,
                    'line' => $node->getStartLine(),
                    'vulnerability_count' => count($issues),
                    'highest_risk' => $this->getHighestRiskLevel($issues)
                ]);
            }
            
        } catch (\Throwable $e) {
            $this->logger->error('Error during security validation', [
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
        // Add parent reference and security context
        if (!empty($this->contextStack)) {
            $parent = $this->getCurrentContext()['node'] ?? null;
            if ($parent instanceof Node) {
                $node->setAttribute('parent', $parent);
                $node->setAttribute('security_context', $this->getCurrentSecurityContext($parent));
            }
        }
        
        // Visit node for security validation
        $this->visitNode($node);
        
        return null; // Don't modify the node
    }

    /**
     * Get all collected security issues
     */
    public function getCollectedIssues(): array
    {
        return $this->collectedIssues;
    }

    /**
     * Get critical security issues
     */
    public function getCriticalIssues(): array
    {
        return array_filter($this->collectedIssues, fn(Issue $issue) => 
            $issue->getSeverity() === Issue::SEVERITY_CRITICAL
        );
    }

    /**
     * Get high-risk security issues
     */
    public function getHighRiskIssues(): array
    {
        return array_filter($this->collectedIssues, fn(Issue $issue) => 
            in_array($issue->getSeverity(), [Issue::SEVERITY_CRITICAL, Issue::SEVERITY_HIGH], true)
        );
    }

    /**
     * Get issues by vulnerability type
     */
    public function getIssuesByVulnerabilityType(string $vulnerabilityType): array
    {
        return array_filter($this->collectedIssues, fn(Issue $issue) => 
            ($issue->getMetadata()['vulnerability_type'] ?? '') === $vulnerabilityType
        );
    }

    /**
     * Get security compliance score
     */
    public function getComplianceScore(): array
    {
        return $this->ruleEngine->getComplianceScore($this->collectedIssues);
    }

    /**
     * Get comprehensive security statistics
     */
    public function getSecurityStats(): array
    {
        $baseStats = $this->getStats();
        $complianceScore = $this->getComplianceScore();
        
        return array_merge($baseStats, [
            'issues_found' => count($this->collectedIssues),
            'critical_issues' => count($this->getCriticalIssues()),
            'high_risk_issues' => count($this->getHighRiskIssues()),
            'nodes_visited' => $this->nodesVisited,
            'vulnerability_distribution' => $this->vulnerabilityStats,
            'risk_level_distribution' => $this->riskLevelStats,
            'owasp_coverage' => $this->owaspStats,
            'compliance_score' => $complianceScore,
            'security_context' => $this->getSecurityContextSummary(),
            'data_flow_analysis' => $this->getDataFlowSummary()
        ]);
    }

    /**
     * Reset visitor state
     */
    public function resetVisitor(): self
    {
        $this->collectedIssues = [];
        $this->nodesVisited = 0;
        $this->vulnerabilityStats = [];
        $this->riskLevelStats = [];
        $this->owaspStats = [];
        $this->securityContext = [];
        $this->sensitiveDataFlow = [];
        $this->authenticationContext = [];
        
        $this->resetState(); // Reset base visitor state
        
        return $this;
    }

    /**
     * Add issue to collection with security enhancements
     */
    private function addIssue(Issue $issue): void
    {
        $this->collectedIssues[] = $issue;
        
        // Update vulnerability statistics
        $vulnerabilityType = $issue->getMetadata()['vulnerability_type'] ?? 'unknown';
        $this->vulnerabilityStats[$vulnerabilityType] = ($this->vulnerabilityStats[$vulnerabilityType] ?? 0) + 1;
        
        // Update risk level statistics
        $riskLevel = $issue->getSeverity();
        $this->riskLevelStats[$riskLevel] = ($this->riskLevelStats[$riskLevel] ?? 0) + 1;
        
        // Update OWASP statistics
        $owaspCategory = $issue->getMetadata()['owasp_category'] ?? 'other';
        $this->owaspStats[$owaspCategory] = ($this->owaspStats[$owaspCategory] ?? 0) + 1;
        
        $this->logger->debug('Security issue added', [
            'rule_id' => $issue->getRuleId(),
            'vulnerability_type' => $vulnerabilityType,
            'risk_level' => $riskLevel,
            'owasp_category' => $owaspCategory,
            'line' => $issue->getLine(),
            'title' => $issue->getTitle()
        ]);
    }

    /**
     * Initialize security context tracking
     */
    private function initializeSecurityContext(): void
    {
        $this->securityContext = [
            'authentication_methods' => [],
            'authorization_checks' => [],
            'input_validation' => [],
            'output_encoding' => [],
            'database_queries' => [],
            'file_operations' => [],
            'network_requests' => [],
            'crypto_operations' => [],
            'session_management' => [],
            'error_handling' => []
        ];
    }

    /**
     * Update security context based on current node
     */
    private function updateSecurityContext(Node $node): void
    {
        $nodeType = $node->getType();
        
        switch ($nodeType) {
            case 'Expr_FuncCall':
            case 'Expr_MethodCall':
                $this->analyzeMethodCall($node);
                break;
                
            case 'Expr_Variable':
                $this->analyzeVariable($node);
                break;
                
            case 'Scalar_String':
                $this->analyzeStringLiteral($node);
                break;
                
            case 'Expr_Array':
                $this->analyzeArrayUsage($node);
                break;
                
            case 'Stmt_Class':
            case 'Stmt_Function':
            case 'Stmt_ClassMethod':
                $this->analyzeCodeStructure($node);
                break;
        }
    }

    /**
     * Track sensitive data flow
     */
    private function trackDataFlow(Node $node): void
    {
        // Track variables that may contain sensitive data
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            $varName = $node->name;
            
            if ($this->isSensitiveVariableName($varName)) {
                $this->sensitiveDataFlow[] = [
                    'variable' => $varName,
                    'line' => $node->getStartLine(),
                    'context' => $this->getCurrentContextType($node)
                ];
            }
        }
        
        // Track function calls that handle sensitive data
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $funcName = $node->name->toString();
            
            if ($this->isSensitiveFunction($funcName)) {
                $this->sensitiveDataFlow[] = [
                    'function' => $funcName,
                    'line' => $node->getStartLine(),
                    'arguments' => count($node->args)
                ];
            }
        }
    }

    /**
     * Enhance issue with security context
     */
    private function enhanceIssueWithContext(Issue $issue, Node $node): void
    {
        $metadata = $issue->getMetadata();
        
        // Add security context
        $metadata['security_context'] = $this->getCurrentSecurityContext($node);
        
        // Add data flow information if relevant
        if (!empty($this->sensitiveDataFlow)) {
            $metadata['data_flow'] = array_slice($this->sensitiveDataFlow, -5); // Last 5 entries
        }
        
        // Add authentication context if relevant
        if (!empty($this->authenticationContext)) {
            $metadata['auth_context'] = $this->authenticationContext;
        }
        
        // Update issue metadata
        $issue->setMetadata($metadata);
    }

    /**
     * Get current security context for a node
     */
    private function getCurrentSecurityContext(Node $node): array
    {
        return [
            'file' => $this->context->getFilePath(),
            'line' => $node->getStartLine(),
            'function_scope' => $this->getCurrentFunction($node),
            'class_scope' => $this->getCurrentClass($node),
            'sensitive_data_nearby' => $this->hasSensitiveDataNearby($node),
            'database_context' => $this->isDatabaseContext($node),
            'user_input_context' => $this->isUserInputContext($node)
        ];
    }

    /**
     * Update security statistics
     */
    private function updateSecurityStats(string $nodeType, array $issues, float $executionTime): void
    {
        // Implementation for updating statistics
    }

    /**
     * Get highest risk level from issues
     */
    private function getHighestRiskLevel(array $issues): string
    {
        $riskOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1, 'info' => 0];
        $highestRisk = 'info';
        $highestLevel = 0;
        
        foreach ($issues as $issue) {
            $riskLevel = $issue->getSeverity();
            $level = $riskOrder[$riskLevel] ?? 0;
            
            if ($level > $highestLevel) {
                $highestLevel = $level;
                $highestRisk = $riskLevel;
            }
        }
        
        return $highestRisk;
    }

    /**
     * Helper methods for security analysis
     */
    private function analyzeMethodCall(Node $node): void { /* Implementation needed */ }
    private function analyzeVariable(Node $node): void { /* Implementation needed */ }
    private function analyzeStringLiteral(Node $node): void { /* Implementation needed */ }
    private function analyzeArrayUsage(Node $node): void { /* Implementation needed */ }
    private function analyzeCodeStructure(Node $node): void { /* Implementation needed */ }
    
    private function isSensitiveVariableName(string $varName): bool 
    {
        $sensitivePatterns = ['password', 'token', 'key', 'secret', 'api', 'auth', 'credential', 'session'];
        return preg_match('/(' . implode('|', $sensitivePatterns) . ')/i', $varName);
    }
    
    private function isSensitiveFunction(string $funcName): bool 
    {
        $sensitiveFunctions = ['mysql_query', 'mysqli_query', 'exec', 'shell_exec', 'eval', 'file_get_contents', 'curl_exec'];
        return in_array(strtolower($funcName), $sensitiveFunctions, true);
    }
    
    private function getCurrentContextType(Node $node): string { return 'general'; }
    private function getCurrentFunction(Node $node): ?string { return null; }
    private function getCurrentClass(Node $node): ?string { return null; }
    private function hasSensitiveDataNearby(Node $node): bool { return false; }
    private function isDatabaseContext(Node $node): bool { return false; }
    private function isUserInputContext(Node $node): bool { return false; }
    private function getSecurityContextSummary(): array { return $this->securityContext; }
    private function getDataFlowSummary(): array { return $this->sensitiveDataFlow; }
}