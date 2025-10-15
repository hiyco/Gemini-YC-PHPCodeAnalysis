<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Security Rule Engine for managing and executing security validation rules
 */

namespace YcPca\Analysis\Security;

use PhpParser\Node;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YcPca\Analysis\Issue\Issue;
use YcPca\Analysis\Security\Rule\SecurityRuleInterface;
use YcPca\Model\FileContext;

/**
 * Rule engine for executing security validation rules
 * 
 * Features:
 * - OWASP Top 10 rule management
 * - Risk-based rule prioritization
 * - Performance optimization with caching
 * - Security compliance tracking
 */
class SecurityRuleEngine
{
    private LoggerInterface $logger;
    
    /** @var SecurityRuleInterface[] */
    private array $rules = [];
    
    /** @var array<string, SecurityRuleInterface[]> */
    private array $rulesByVulnerabilityType = [];
    
    /** @var array<string, SecurityRuleInterface[]> */
    private array $rulesByNodeType = [];
    
    private array $ruleStats = [
        'rules_executed' => 0,
        'vulnerabilities_found' => 0,
        'execution_time' => 0.0,
        'high_risk_issues' => 0,
        'medium_risk_issues' => 0,
        'low_risk_issues' => 0
    ];
    
    private bool $cachingEnabled = true;
    private array $owaspConfig = [];

    public function __construct(?LoggerInterface $logger = null, array $owaspConfig = [])
    {
        $this->logger = $logger ?? new NullLogger();
        $this->owaspConfig = array_merge($this->getDefaultOwaspConfig(), $owaspConfig);
    }

    /**
     * Add security rule
     */
    public function addRule(SecurityRuleInterface $rule): self
    {
        $ruleId = $rule->getRuleId();
        
        if (isset($this->rules[$ruleId])) {
            $this->logger->warning('Security rule already exists, replacing', [
                'rule_id' => $ruleId
            ]);
        }
        
        $this->rules[$ruleId] = $rule;
        
        // Update vulnerability type cache
        $this->updateVulnerabilityTypeCache($rule);
        
        // Update node type cache
        $this->updateNodeTypeCache($rule);
        
        $this->logger->debug('Security rule added', [
            'rule_id' => $ruleId,
            'rule_name' => $rule->getRuleName(),
            'vulnerability_type' => $rule->getVulnerabilityType(),
            'risk_level' => $rule->getRiskLevel()
        ]);
        
        return $this;
    }

    /**
     * Remove rule by ID
     */
    public function removeRule(string $ruleId): self
    {
        if (!isset($this->rules[$ruleId])) {
            $this->logger->warning('Security rule not found for removal', [
                'rule_id' => $ruleId
            ]);
            return $this;
        }
        
        $rule = $this->rules[$ruleId];
        unset($this->rules[$ruleId]);
        
        // Rebuild caches
        $this->rebuildCaches();
        
        $this->logger->debug('Security rule removed', [
            'rule_id' => $ruleId,
            'rule_name' => $rule->getRuleName()
        ]);
        
        return $this;
    }

    /**
     * Get rule by ID
     */
    public function getRule(string $ruleId): ?SecurityRuleInterface
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
        return array_filter($this->rules, fn(SecurityRuleInterface $rule) => $rule->isEnabled());
    }

    /**
     * Get rules by vulnerability type (e.g., 'sql_injection', 'xss')
     */
    public function getRulesByVulnerabilityType(string $vulnerabilityType): array
    {
        return $this->rulesByVulnerabilityType[$vulnerabilityType] ?? [];
    }

    /**
     * Get rules by risk level
     */
    public function getRulesByRiskLevel(string $riskLevel): array
    {
        return array_filter($this->rules, fn(SecurityRuleInterface $rule) => 
            $rule->getRiskLevel() === $riskLevel
        );
    }

    /**
     * Validate node against security rules
     */
    public function validateNode(Node $node, FileContext $context): array
    {
        $startTime = microtime(true);
        $nodeType = $node->getType();
        $allIssues = [];
        
        // Get applicable rules for this node type
        $applicableRules = $this->getRulesForNodeType($nodeType);
        
        if (empty($applicableRules)) {
            return $allIssues;
        }
        
        $this->logger->debug('Validating node for security', [
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
                $this->ruleStats['vulnerabilities_found'] += count($issues);
                
                // Update risk-based statistics
                foreach ($issues as $issue) {
                    $this->updateRiskStats($issue);
                }
                
                if (!empty($issues)) {
                    $this->logger->info('Security vulnerabilities found', [
                        'rule_id' => $rule->getRuleId(),
                        'vulnerability_type' => $rule->getVulnerabilityType(),
                        'node_type' => $nodeType,
                        'line' => $node->getStartLine(),
                        'issue_count' => count($issues)
                    ]);
                }
                
            } catch (\Throwable $e) {
                $this->logger->error('Security rule execution failed', [
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
     * Load built-in security rules based on OWASP Top 10
     */
    public function loadOwaspRules(): self
    {
        $this->logger->info('Loading OWASP Top 10 security rules');
        
        // Load rules based on OWASP configuration
        $this->loadInjectionRules();
        $this->loadAuthenticationRules();
        $this->loadDataExposureRules();
        $this->loadXmlExternalEntityRules();
        $this->loadAccessControlRules();
        $this->loadSecurityMisconfigurationRules();
        $this->loadXssRules();
        $this->loadDeserializationRules();
        $this->loadComponentVulnerabilityRules();
        $this->loadLoggingMonitoringRules();
        
        $this->logger->info('OWASP rules loaded', [
            'total_rules' => count($this->rules),
            'enabled_rules' => count($this->getEnabledRules())
        ]);
        
        return $this;
    }

    /**
     * Get security compliance score
     */
    public function getComplianceScore(array $issues): array
    {
        $totalRules = count($this->getEnabledRules());
        $rulesPassed = $totalRules;
        $riskScore = 0;
        
        $vulnerabilityTypes = [];
        $riskLevels = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];
        
        foreach ($issues as $issue) {
            $vulnerabilityType = $issue->getMetadata()['vulnerability_type'] ?? 'unknown';
            $riskLevel = $issue->getSeverity();
            
            if (!isset($vulnerabilityTypes[$vulnerabilityType])) {
                $vulnerabilityTypes[$vulnerabilityType] = 0;
                $rulesPassed--; // Rule failed if vulnerabilities found
            }
            
            $vulnerabilityTypes[$vulnerabilityType]++;
            $riskLevels[$riskLevel] = ($riskLevels[$riskLevel] ?? 0) + 1;
            
            // Calculate risk score
            $riskScore += $this->getRiskScoreWeight($riskLevel);
        }
        
        $compliancePercentage = $totalRules > 0 ? ($rulesPassed / $totalRules) * 100 : 100;
        
        return [
            'compliance_percentage' => round($compliancePercentage, 2),
            'total_vulnerabilities' => count($issues),
            'vulnerability_types' => $vulnerabilityTypes,
            'risk_levels' => $riskLevels,
            'risk_score' => $riskScore,
            'security_grade' => $this->getSecurityGrade($compliancePercentage, $riskScore),
            'owasp_coverage' => $this->getOwaspCoverage($vulnerabilityTypes)
        ];
    }

    /**
     * Get rule statistics
     */
    public function getStats(): array
    {
        return array_merge($this->ruleStats, [
            'total_rules' => count($this->rules),
            'enabled_rules' => count($this->getEnabledRules()),
            'vulnerability_types' => count($this->rulesByVulnerabilityType),
            'caching_enabled' => $this->cachingEnabled
        ]);
    }

    /**
     * Reset statistics
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
     * Get rules for specific node type
     */
    private function getRulesForNodeType(string $nodeType): array
    {
        if ($this->cachingEnabled && isset($this->rulesByNodeType[$nodeType])) {
            return $this->rulesByNodeType[$nodeType];
        }
        
        $applicableRules = [];
        
        foreach ($this->rules as $rule) {
            if ($rule->appliesToNodeType($nodeType)) {
                $applicableRules[] = $rule;
            }
        }
        
        // Sort by risk level and priority (critical/high first)
        usort($applicableRules, function(SecurityRuleInterface $a, SecurityRuleInterface $b) {
            $riskOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1, 'info' => 0];
            $aRisk = $riskOrder[$a->getRiskLevel()] ?? 0;
            $bRisk = $riskOrder[$b->getRiskLevel()] ?? 0;
            
            if ($aRisk === $bRisk) {
                return $b->getPriority() - $a->getPriority();
            }
            
            return $bRisk - $aRisk;
        });
        
        if ($this->cachingEnabled) {
            $this->rulesByNodeType[$nodeType] = $applicableRules;
        }
        
        return $applicableRules;
    }

    /**
     * Update vulnerability type cache
     */
    private function updateVulnerabilityTypeCache(SecurityRuleInterface $rule): void
    {
        $vulnerabilityType = $rule->getVulnerabilityType();
        
        if (!isset($this->rulesByVulnerabilityType[$vulnerabilityType])) {
            $this->rulesByVulnerabilityType[$vulnerabilityType] = [];
        }
        
        $this->rulesByVulnerabilityType[$vulnerabilityType][] = $rule;
    }

    /**
     * Update node type cache
     */
    private function updateNodeTypeCache(SecurityRuleInterface $rule): void
    {
        foreach ($rule->getSupportedNodeTypes() as $nodeType) {
            if (!isset($this->rulesByNodeType[$nodeType])) {
                $this->rulesByNodeType[$nodeType] = [];
            }
            
            $this->rulesByNodeType[$nodeType][] = $rule;
        }
    }

    /**
     * Rebuild all caches
     */
    private function rebuildCaches(): void
    {
        $this->rulesByVulnerabilityType = [];
        $this->rulesByNodeType = [];
        
        foreach ($this->rules as $rule) {
            $this->updateVulnerabilityTypeCache($rule);
            $this->updateNodeTypeCache($rule);
        }
    }

    /**
     * Clear cache
     */
    private function clearCache(): self
    {
        $this->rulesByVulnerabilityType = [];
        $this->rulesByNodeType = [];
        return $this;
    }

    /**
     * Update risk-based statistics
     */
    private function updateRiskStats(Issue $issue): void
    {
        $severity = $issue->getSeverity();
        
        switch ($severity) {
            case Issue::SEVERITY_CRITICAL:
            case Issue::SEVERITY_HIGH:
                $this->ruleStats['high_risk_issues']++;
                break;
            case Issue::SEVERITY_MEDIUM:
                $this->ruleStats['medium_risk_issues']++;
                break;
            case Issue::SEVERITY_LOW:
            case Issue::SEVERITY_INFO:
                $this->ruleStats['low_risk_issues']++;
                break;
        }
    }

    /**
     * Get risk score weight for severity level
     */
    private function getRiskScoreWeight(string $severity): int
    {
        return match ($severity) {
            Issue::SEVERITY_CRITICAL => 100,
            Issue::SEVERITY_HIGH => 50,
            Issue::SEVERITY_MEDIUM => 25,
            Issue::SEVERITY_LOW => 10,
            Issue::SEVERITY_INFO => 5,
            default => 0
        };
    }

    /**
     * Get security grade based on compliance and risk
     */
    private function getSecurityGrade(float $compliance, int $riskScore): string
    {
        if ($compliance >= 95 && $riskScore === 0) return 'A+';
        if ($compliance >= 90 && $riskScore < 50) return 'A';
        if ($compliance >= 85 && $riskScore < 100) return 'B+';
        if ($compliance >= 80 && $riskScore < 150) return 'B';
        if ($compliance >= 70 && $riskScore < 200) return 'C+';
        if ($compliance >= 60 && $riskScore < 300) return 'C';
        if ($compliance >= 50) return 'D';
        return 'F';
    }

    /**
     * Get OWASP Top 10 coverage
     */
    private function getOwaspCoverage(array $vulnerabilityTypes): array
    {
        $owaspTop10 = [
            'injection', 'broken_authentication', 'sensitive_data_exposure',
            'xml_external_entities', 'broken_access_control', 'security_misconfiguration',
            'xss', 'insecure_deserialization', 'known_vulnerabilities', 'insufficient_logging'
        ];
        
        $coverage = [];
        foreach ($owaspTop10 as $owaspType) {
            $coverage[$owaspType] = isset($vulnerabilityTypes[$owaspType]) ? 'VULNERABLE' : 'SECURE';
        }
        
        return $coverage;
    }

    /**
     * Get default OWASP configuration
     */
    private function getDefaultOwaspConfig(): array
    {
        return [
            'enable_injection_rules' => true,
            'enable_authentication_rules' => true,
            'enable_data_exposure_rules' => true,
            'enable_xxe_rules' => true,
            'enable_access_control_rules' => true,
            'enable_misconfiguration_rules' => true,
            'enable_xss_rules' => true,
            'enable_deserialization_rules' => true,
            'enable_component_rules' => true,
            'enable_logging_rules' => true,
            'strict_mode' => false,
            'risk_threshold' => 'medium'
        ];
    }

    // Placeholder methods for loading specific OWASP rule categories
    private function loadInjectionRules(): void { /* Implementation needed */ }
    private function loadAuthenticationRules(): void { /* Implementation needed */ }
    private function loadDataExposureRules(): void { /* Implementation needed */ }
    private function loadXmlExternalEntityRules(): void { /* Implementation needed */ }
    private function loadAccessControlRules(): void { /* Implementation needed */ }
    private function loadSecurityMisconfigurationRules(): void { /* Implementation needed */ }
    private function loadXssRules(): void { /* Implementation needed */ }
    private function loadDeserializationRules(): void { /* Implementation needed */ }
    private function loadComponentVulnerabilityRules(): void { /* Implementation needed */ }
    private function loadLoggingMonitoringRules(): void { /* Implementation needed */ }
}