<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Security Analyzer for detecting security vulnerabilities
 */

namespace YcPca\Analysis\Analyzer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YcPca\Analysis\Issue\Issue;
use YcPca\Analysis\Security\SecurityRuleEngine;
use YcPca\Analysis\Security\SecurityVisitor;
use YcPca\Analysis\Security\Rule\SecurityRuleInterface;
use YcPca\Model\AnalysisResult;

/**
 * Security analyzer implementing comprehensive vulnerability detection
 * 
 * Features:
 * - OWASP Top 10 coverage
 * - Custom security rules
 * - Severity-based reporting
 * - Context-aware analysis
 */
class SecurityAnalyzer implements AnalyzerInterface
{
    private const NAME = 'security';
    private const VERSION = '1.0.0';
    private const DESCRIPTION = 'PHP security vulnerability scanner with OWASP Top 10 coverage';

    private LoggerInterface $logger;
    private SecurityRuleEngine $ruleEngine;
    private array $config;
    private bool $enabled = true;

    // OWASP Top 10 categories
    public const OWASP_INJECTION = 'A01_injection';
    public const OWASP_AUTH_FAILURES = 'A02_authentication_failures';
    public const OWASP_DATA_EXPOSURE = 'A03_data_exposure';
    public const OWASP_XML_ENTITIES = 'A04_xml_entities';
    public const OWASP_ACCESS_CONTROL = 'A05_access_control';
    public const OWASP_SECURITY_CONFIG = 'A06_security_configuration';
    public const OWASP_XSS = 'A07_xss';
    public const OWASP_DESERIALIZATION = 'A08_deserialization';
    public const OWASP_COMPONENTS = 'A09_vulnerable_components';
    public const OWASP_LOGGING = 'A10_logging_monitoring';

    public function __construct(
        ?SecurityRuleEngine $ruleEngine = null,
        ?LoggerInterface $logger = null,
        array $config = []
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->ruleEngine = $ruleEngine ?? new SecurityRuleEngine($this->logger);
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        $this->initializeSecurityRules();
        
        $this->logger->info('SecurityAnalyzer initialized', [
            'version' => self::VERSION,
            'rules_count' => $this->ruleEngine->getRuleCount(),
            'owasp_coverage' => $this->getOwaspCoverage()
        ]);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getVersion(): string
    {
        return self::VERSION;
    }

    public function getDescription(): string
    {
        return self::DESCRIPTION;
    }

    /**
     * Analyze for security vulnerabilities
     */
    public function analyze(AnalysisResult $parseResult): AnalyzerResult
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $this->logger->info('Starting security analysis', [
            'file' => $parseResult->getFilePath(),
            'node_count' => count($parseResult->getAst()),
            'enabled_rules' => $this->ruleEngine->getEnabledRuleCount()
        ]);
        
        $result = new AnalyzerResult(
            analyzerName: $this->getName(),
            filePath: $parseResult->getFilePath()
        );
        
        try {
            // Only analyze if we have a valid AST and analyzer is enabled
            if (!empty($parseResult->getAst()) && $this->enabled) {
                $this->runSecurityAnalysis($parseResult, $result);
            }
            
            // Add parse errors as critical security issues
            if ($parseResult->hasErrors()) {
                foreach ($parseResult->getErrors() as $error) {
                    $issue = $this->createSecurityParseErrorIssue($error);
                    $result->addIssue($issue);
                }
            }
            
            $executionTime = microtime(true) - $startTime;
            $memoryUsage = memory_get_usage(true) - $startMemory;
            
            $result->setExecutionTime($executionTime);
            $result->setMemoryUsage($memoryUsage);
            
            // Add security-specific metadata
            $this->addSecurityMetadata($result);
            
            $this->logger->info('Security analysis completed', [
                'file' => $parseResult->getFilePath(),
                'vulnerabilities_found' => $result->getIssueCount(),
                'critical_issues' => $result->getCriticalIssuesCount(),
                'duration' => $executionTime,
                'memory_used' => $memoryUsage
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->error('Security analysis failed', [
                'file' => $parseResult->getFilePath(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $result->addError($e->getMessage());
        }
        
        return $result;
    }

    public function supportsErrorRecovery(): bool
    {
        return true; // Can analyze partial ASTs for security issues
    }

    public function supportsFileType(string $extension): bool
    {
        return in_array(strtolower($extension), ['php', 'phtml', 'php5', 'php7', 'php8'], true);
    }

    public function getSupportedExtensions(): array
    {
        return ['php', 'phtml', 'php5', 'php7', 'php8'];
    }

    public function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'detect_sql_injection' => ['type' => 'boolean', 'default' => true],
                'detect_xss' => ['type' => 'boolean', 'default' => true],
                'detect_csrf' => ['type' => 'boolean', 'default' => true],
                'detect_file_inclusion' => ['type' => 'boolean', 'default' => true],
                'detect_command_injection' => ['type' => 'boolean', 'default' => true],
                'detect_deserialization' => ['type' => 'boolean', 'default' => true],
                'detect_weak_crypto' => ['type' => 'boolean', 'default' => true],
                'detect_sensitive_data' => ['type' => 'boolean', 'default' => true],
                'strict_mode' => ['type' => 'boolean', 'default' => false],
                'check_user_input_validation' => ['type' => 'boolean', 'default' => true],
                'scan_file_operations' => ['type' => 'boolean', 'default' => true]
            ]
        ];
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        
        // Reinitialize rules with new config
        $this->initializeSecurityRules();
        
        $this->logger->info('Security analyzer configuration updated');
        
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        
        $this->logger->info('Security analyzer ' . ($enabled ? 'enabled' : 'disabled'));
        
        return $this;
    }

    public function getPriority(): int
    {
        return 90; // High priority for security issues
    }

    public function getCategories(): array
    {
        return ['security', 'vulnerability', 'owasp'];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];
        
        foreach ($this->getConfigSchema()['properties'] as $key => $schema) {
            if (isset($config[$key]) && $schema['type'] === 'boolean' && !is_bool($config[$key])) {
                $errors[] = "{$key} must be a boolean value";
            }
        }
        
        return $errors;
    }

    public function reset(): self
    {
        $this->ruleEngine->reset();
        return $this;
    }

    /**
     * Add custom security rule
     */
    public function addSecurityRule(SecurityRuleInterface $rule): self
    {
        $this->ruleEngine->addRule($rule);
        return $this;
    }

    /**
     * Get OWASP Top 10 coverage information
     */
    public function getOwaspCoverage(): array
    {
        return [
            self::OWASP_INJECTION => $this->isConfigEnabled('detect_sql_injection'),
            self::OWASP_AUTH_FAILURES => $this->isConfigEnabled('detect_auth_failures'),
            self::OWASP_DATA_EXPOSURE => $this->isConfigEnabled('detect_sensitive_data'),
            self::OWASP_XML_ENTITIES => $this->isConfigEnabled('detect_xml_entities'),
            self::OWASP_ACCESS_CONTROL => $this->isConfigEnabled('detect_access_control'),
            self::OWASP_SECURITY_CONFIG => $this->isConfigEnabled('detect_security_config'),
            self::OWASP_XSS => $this->isConfigEnabled('detect_xss'),
            self::OWASP_DESERIALIZATION => $this->isConfigEnabled('detect_deserialization'),
            self::OWASP_COMPONENTS => $this->isConfigEnabled('detect_vulnerable_components'),
            self::OWASP_LOGGING => $this->isConfigEnabled('detect_logging_issues')
        ];
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'detect_sql_injection' => true,
            'detect_xss' => true,
            'detect_csrf' => true,
            'detect_file_inclusion' => true,
            'detect_command_injection' => true,
            'detect_deserialization' => true,
            'detect_weak_crypto' => true,
            'detect_sensitive_data' => true,
            'detect_auth_failures' => true,
            'detect_xml_entities' => true,
            'detect_access_control' => true,
            'detect_security_config' => true,
            'detect_vulnerable_components' => true,
            'detect_logging_issues' => true,
            'strict_mode' => false,
            'check_user_input_validation' => true,
            'scan_file_operations' => true,
            'max_severity_level' => 'critical'
        ];
    }

    /**
     * Initialize security rules based on configuration
     */
    private function initializeSecurityRules(): void
    {
        $this->ruleEngine->loadSecurityRules($this->config);
    }

    /**
     * Run security analysis on the AST
     */
    private function runSecurityAnalysis(AnalysisResult $parseResult, AnalyzerResult $result): void
    {
        $ast = $parseResult->getAst();
        $context = $parseResult->getContext();
        
        // Create security visitor for AST traversal
        $visitor = new SecurityVisitor($this->ruleEngine, $context, $this->logger);
        
        // Configure visitor based on analyzer config
        $visitor->setConfig($this->config);
        
        // Traverse AST and collect security issues
        $this->traverseNodes($ast, $visitor, $result);
        
        // Get issues from the visitor
        $issues = $visitor->getCollectedIssues();
        foreach ($issues as $issue) {
            $result->addIssue($issue);
        }
        
        // Add security-specific metadata
        $result->addMetadata('security_rules_executed', $this->ruleEngine->getEnabledRuleCount());
        $result->addMetadata('nodes_analyzed', $visitor->getNodesAnalyzed());
        $result->addMetadata('owasp_coverage', $this->getOwaspCoverage());
        $result->addMetadata('vulnerability_categories', $this->categorizeIssues($issues));
    }

    /**
     * Traverse AST nodes with security visitor
     */
    private function traverseNodes(array $nodes, SecurityVisitor $visitor, AnalyzerResult $result): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof \PhpParser\Node) {
                try {
                    $visitor->visitNode($node);
                    
                    // Recursively visit child nodes
                    $childNodes = [];
                    foreach ($node->getSubNodeNames() as $subNodeName) {
                        $subNode = $node->$subNodeName;
                        if ($subNode instanceof \PhpParser\Node) {
                            $childNodes[] = $subNode;
                        } elseif (is_array($subNode)) {
                            foreach ($subNode as $arrayNode) {
                                if ($arrayNode instanceof \PhpParser\Node) {
                                    $childNodes[] = $arrayNode;
                                }
                            }
                        }
                    }
                    
                    if (!empty($childNodes)) {
                        $this->traverseNodes($childNodes, $visitor, $result);
                    }
                    
                } catch (\Throwable $e) {
                    $this->logger->warning('Error analyzing node for security', [
                        'node_type' => $node->getType(),
                        'line' => $node->getStartLine(),
                        'error' => $e->getMessage()
                    ]);
                    
                    $result->addWarning("Security analysis error for {$node->getType()}: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Create security issue from parse error
     */
    private function createSecurityParseErrorIssue(string $error): Issue
    {
        // Parse errors can indicate potential security issues
        preg_match('/line (\d+)/', $error, $matches);
        $line = isset($matches[1]) ? (int) $matches[1] : null;
        
        return new Issue(
            id: 'security_parse_error_' . md5($error),
            title: 'Parse Error (Security Risk)',
            description: "Parse error detected: {$error}. This could indicate potential code injection or malformed input.",
            severity: Issue::SEVERITY_HIGH,
            category: Issue::CATEGORY_SECURITY,
            line: $line,
            ruleId: 'security_parse_error',
            ruleName: 'Security Parse Error Detection',
            tags: ['security', 'parse-error', 'potential-injection'],
            suggestions: [
                'Verify input validation is working correctly',
                'Check for potential code injection attacks',
                'Ensure proper error handling is in place',
                'Review recent code changes for syntax issues'
            ]
        );
    }

    /**
     * Add security-specific metadata to results
     */
    private function addSecurityMetadata(AnalyzerResult $result): void
    {
        $issues = $result->getIssues();
        
        $result->addMetadata('security_score', $this->calculateSecurityScore($issues));
        $result->addMetadata('owasp_violations', $this->getOwaspViolations($issues));
        $result->addMetadata('risk_level', $this->calculateRiskLevel($issues));
        $result->addMetadata('compliance_status', $this->getComplianceStatus($issues));
    }

    /**
     * Calculate security score (0-100, higher is better)
     */
    private function calculateSecurityScore(array $issues): int
    {
        if (empty($issues)) {
            return 100;
        }
        
        $penalty = 0;
        foreach ($issues as $issue) {
            $penalty += match ($issue->getSeverity()) {
                Issue::SEVERITY_CRITICAL => 40,
                Issue::SEVERITY_HIGH => 20,
                Issue::SEVERITY_MEDIUM => 10,
                Issue::SEVERITY_LOW => 5,
                default => 1
            };
        }
        
        return max(0, 100 - $penalty);
    }

    /**
     * Get OWASP Top 10 violations
     */
    private function getOwaspViolations(array $issues): array
    {
        $violations = [];
        
        foreach ($issues as $issue) {
            $tags = $issue->getTags();
            foreach ($tags as $tag) {
                if (str_starts_with($tag, 'owasp-')) {
                    $owaspCategory = substr($tag, 6);
                    $violations[$owaspCategory] = ($violations[$owaspCategory] ?? 0) + 1;
                }
            }
        }
        
        return $violations;
    }

    /**
     * Calculate overall risk level
     */
    private function calculateRiskLevel(array $issues): string
    {
        $criticalCount = 0;
        $highCount = 0;
        
        foreach ($issues as $issue) {
            if ($issue->getSeverity() === Issue::SEVERITY_CRITICAL) {
                $criticalCount++;
            } elseif ($issue->getSeverity() === Issue::SEVERITY_HIGH) {
                $highCount++;
            }
        }
        
        if ($criticalCount > 0) {
            return 'CRITICAL';
        } elseif ($highCount > 2) {
            return 'HIGH';
        } elseif ($highCount > 0 || count($issues) > 5) {
            return 'MEDIUM';
        } elseif (count($issues) > 0) {
            return 'LOW';
        }
        
        return 'MINIMAL';
    }

    /**
     * Get compliance status
     */
    private function getComplianceStatus(array $issues): array
    {
        return [
            'owasp_compliant' => $this->isOwaspCompliant($issues),
            'pci_dss_compliant' => $this->isPciDssCompliant($issues),
            'gdpr_compliant' => $this->isGdprCompliant($issues)
        ];
    }

    /**
     * Check if code is OWASP compliant (no critical security issues)
     */
    private function isOwaspCompliant(array $issues): bool
    {
        foreach ($issues as $issue) {
            if ($issue->getSeverity() === Issue::SEVERITY_CRITICAL && $issue->isSecurityIssue()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check PCI DSS compliance (basic check)
     */
    private function isPciDssCompliant(array $issues): bool
    {
        // Basic PCI DSS compliance check
        return $this->isOwaspCompliant($issues);
    }

    /**
     * Check GDPR compliance (basic check)
     */
    private function isGdprCompliant(array $issues): bool
    {
        // Basic GDPR compliance check
        foreach ($issues as $issue) {
            if (in_array('gdpr', $issue->getTags())) {
                return false;
            }
        }
        return true;
    }

    /**
     * Categorize issues by type
     */
    private function categorizeIssues(array $issues): array
    {
        $categories = [];
        
        foreach ($issues as $issue) {
            $category = $issue->getCategory();
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }
        
        return $categories;
    }

    /**
     * Check if config value is enabled
     */
    private function isConfigEnabled(string $key): bool
    {
        return (bool) ($this->config[$key] ?? false);
    }
}