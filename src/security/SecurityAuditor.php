<?php

declare(strict_types=1);

namespace YcPca\Security;

use YcPca\Ast\AstParser;
use YcPca\Security\Detectors\DetectorInterface;
use YcPca\Security\Detectors\SqlInjectionDetector;
use YcPca\Security\Detectors\XssDetector;
use YcPca\Security\Detectors\CsrfDetector;
use YcPca\Security\Detectors\AuthenticationDetector;
use YcPca\Security\Detectors\CodeExecutionDetector;
use YcPca\Security\Detectors\SensitiveDataDetector;
use YcPca\Security\Detectors\AccessControlDetector;
use YcPca\Security\Reports\VulnerabilityReport;
use YcPca\Security\Rules\RuleEngine;
use PhpParser\Node;

/**
 * Professional Security Auditor with AST-based analysis
 * OWASP TOP 10 compliance and PHP-specific vulnerability detection
 */
class SecurityAuditor
{
    private AstParser $parser;
    private RuleEngine $ruleEngine;
    private array $detectors = [];
    private array $vulnerabilities = [];
    private array $config;
    private float $startTime;
    private array $metrics = [];

    public function __construct(AstParser $parser, array $config = [])
    {
        $this->parser = $parser;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->ruleEngine = new RuleEngine($this->config['rules']);
        $this->initializeDetectors();
    }

    /**
     * Initialize all security detectors
     */
    private function initializeDetectors(): void
    {
        // OWASP TOP 10 Detectors
        $this->detectors = [
            'sql_injection' => new SqlInjectionDetector($this->ruleEngine),
            'xss' => new XssDetector($this->ruleEngine),
            'csrf' => new CsrfDetector($this->ruleEngine),
            'auth' => new AuthenticationDetector($this->ruleEngine),
            'code_execution' => new CodeExecutionDetector($this->ruleEngine),
            'sensitive_data' => new SensitiveDataDetector($this->ruleEngine),
            'access_control' => new AccessControlDetector($this->ruleEngine),
        ];

        // Load custom detectors if configured
        foreach ($this->config['custom_detectors'] as $name => $class) {
            if (class_exists($class) && is_subclass_of($class, DetectorInterface::class)) {
                $this->detectors[$name] = new $class($this->ruleEngine);
            }
        }
    }

    /**
     * Perform comprehensive security audit on a file
     */
    public function auditFile(string $filePath): VulnerabilityReport
    {
        $this->startTime = microtime(true);
        $this->vulnerabilities = [];
        
        try {
            $ast = $this->parser->parseFile($filePath);
            $context = $this->buildSecurityContext($ast, $filePath);
            
            // Run all detectors
            foreach ($this->detectors as $name => $detector) {
                if ($this->isDetectorEnabled($name)) {
                    $detector->detect($ast, $context, $this->vulnerabilities);
                }
            }
            
            // Apply custom rules
            $this->ruleEngine->applyRules($ast, $context, $this->vulnerabilities);
            
            // Calculate risk scores
            $this->calculateRiskScores();
            
            // Generate report
            return $this->generateReport($filePath);
            
        } catch (\Exception $e) {
            throw new SecurityAuditException(
                "Failed to audit file {$filePath}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Perform incremental security audit (for CI/CD)
     */
    public function auditChanges(array $changedFiles, ?array $previousReport = null): VulnerabilityReport
    {
        $allVulnerabilities = [];
        $metrics = ['files_scanned' => 0, 'new_vulnerabilities' => 0];
        
        foreach ($changedFiles as $file) {
            if ($this->shouldScanFile($file)) {
                $report = $this->auditFile($file);
                $allVulnerabilities = array_merge($allVulnerabilities, $report->getVulnerabilities());
                $metrics['files_scanned']++;
            }
        }
        
        // Compare with previous report if available
        if ($previousReport !== null) {
            $metrics['new_vulnerabilities'] = $this->compareReports(
                $allVulnerabilities,
                $previousReport
            );
        }
        
        return new VulnerabilityReport($allVulnerabilities, $metrics);
    }

    /**
     * Build security context for analysis
     */
    private function buildSecurityContext(array $ast, string $filePath): SecurityContext
    {
        $context = new SecurityContext($filePath);
        
        // Extract security-relevant information
        $context->setFunctions($this->extractFunctions($ast));
        $context->setClasses($this->extractClasses($ast));
        $context->setVariables($this->extractVariables($ast));
        $context->setInputSources($this->identifyInputSources($ast));
        $context->setOutputSinks($this->identifyOutputSinks($ast));
        $context->setAuthenticationPoints($this->findAuthenticationPoints($ast));
        $context->setDatabaseQueries($this->extractDatabaseQueries($ast));
        $context->setFileOperations($this->extractFileOperations($ast));
        $context->setSensitivePatterns($this->detectSensitivePatterns($ast));
        
        // Analyze data flow
        $dataFlow = new DataFlowAnalyzer();
        $context->setTaintedPaths($dataFlow->analyzeTaintPropagation($ast));
        
        return $context;
    }

    /**
     * Calculate risk scores for detected vulnerabilities
     */
    private function calculateRiskScores(): void
    {
        foreach ($this->vulnerabilities as &$vuln) {
            $score = $this->calculateCVSSScore($vuln);
            $vuln['risk_score'] = $score;
            $vuln['severity'] = $this->getSeverityLevel($score);
            $vuln['priority'] = $this->calculatePriority($vuln);
        }
        
        // Sort by priority
        usort($this->vulnerabilities, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
    }

    /**
     * Calculate CVSS score for vulnerability
     */
    private function calculateCVSSScore(array $vulnerability): float
    {
        // Simplified CVSS v3.1 calculation
        $baseScore = 0.0;
        
        // Attack Vector (AV)
        $av = $vulnerability['attack_vector'] ?? 'network';
        $avScores = ['network' => 0.85, 'adjacent' => 0.62, 'local' => 0.55, 'physical' => 0.2];
        $baseScore += $avScores[$av] ?? 0.85;
        
        // Attack Complexity (AC)
        $ac = $vulnerability['attack_complexity'] ?? 'low';
        $acScores = ['low' => 0.77, 'high' => 0.44];
        $baseScore += $acScores[$ac] ?? 0.77;
        
        // Privileges Required (PR)
        $pr = $vulnerability['privileges_required'] ?? 'none';
        $prScores = ['none' => 0.85, 'low' => 0.62, 'high' => 0.27];
        $baseScore += $prScores[$pr] ?? 0.85;
        
        // User Interaction (UI)
        $ui = $vulnerability['user_interaction'] ?? 'none';
        $uiScores = ['none' => 0.85, 'required' => 0.62];
        $baseScore += $uiScores[$ui] ?? 0.85;
        
        // Impact scores
        $confidentiality = $vulnerability['confidentiality_impact'] ?? 'high';
        $integrity = $vulnerability['integrity_impact'] ?? 'high';
        $availability = $vulnerability['availability_impact'] ?? 'low';
        
        $impactScores = ['none' => 0, 'low' => 0.22, 'high' => 0.56];
        $impact = ($impactScores[$confidentiality] ?? 0.56) + 
                  ($impactScores[$integrity] ?? 0.56) + 
                  ($impactScores[$availability] ?? 0.22);
        
        // Calculate final score (0-10 scale)
        return min(10, round(($baseScore + $impact) * 2.5, 1));
    }

    /**
     * Get severity level based on CVSS score
     */
    private function getSeverityLevel(float $score): string
    {
        if ($score >= 9.0) return 'CRITICAL';
        if ($score >= 7.0) return 'HIGH';
        if ($score >= 4.0) return 'MEDIUM';
        if ($score >= 0.1) return 'LOW';
        return 'INFO';
    }

    /**
     * Calculate priority based on multiple factors
     */
    private function calculatePriority(array $vulnerability): int
    {
        $priority = 0;
        
        // Risk score weight
        $priority += $vulnerability['risk_score'] * 10;
        
        // Exploitability
        if ($vulnerability['exploitable'] ?? false) {
            $priority += 20;
        }
        
        // Known exploits
        if ($vulnerability['known_exploits'] ?? false) {
            $priority += 30;
        }
        
        // Reachability from user input
        if ($vulnerability['user_reachable'] ?? false) {
            $priority += 15;
        }
        
        // Fix complexity
        $fixComplexity = $vulnerability['fix_complexity'] ?? 'medium';
        $complexityScores = ['low' => 10, 'medium' => 5, 'high' => 0];
        $priority += $complexityScores[$fixComplexity] ?? 5;
        
        return (int)$priority;
    }

    /**
     * Generate comprehensive security report
     */
    private function generateReport(string $filePath): VulnerabilityReport
    {
        $executionTime = microtime(true) - $this->startTime;
        
        $metrics = [
            'total_vulnerabilities' => count($this->vulnerabilities),
            'critical' => $this->countBySeverity('CRITICAL'),
            'high' => $this->countBySeverity('HIGH'),
            'medium' => $this->countBySeverity('MEDIUM'),
            'low' => $this->countBySeverity('LOW'),
            'info' => $this->countBySeverity('INFO'),
            'execution_time' => $executionTime,
            'file_path' => $filePath,
            'timestamp' => time(),
            'false_positive_rate' => $this->estimateFalsePositiveRate(),
            'coverage' => $this->calculateCoverage(),
        ];
        
        return new VulnerabilityReport($this->vulnerabilities, $metrics);
    }

    /**
     * Count vulnerabilities by severity
     */
    private function countBySeverity(string $severity): int
    {
        return count(array_filter($this->vulnerabilities, function ($v) use ($severity) {
            return $v['severity'] === $severity;
        }));
    }

    /**
     * Estimate false positive rate based on heuristics
     */
    private function estimateFalsePositiveRate(): float
    {
        $totalDetections = count($this->vulnerabilities);
        if ($totalDetections === 0) return 0.0;
        
        $highConfidence = count(array_filter($this->vulnerabilities, function ($v) {
            return ($v['confidence'] ?? 0.5) >= 0.9;
        }));
        
        // Estimate based on confidence scores
        $estimatedFalsePositives = $totalDetections - $highConfidence;
        return round(($estimatedFalsePositives / $totalDetections) * 100, 2);
    }

    /**
     * Calculate security coverage percentage
     */
    private function calculateCoverage(): float
    {
        $checkedCategories = array_keys(array_filter($this->detectors, function ($d) {
            return $d->hasExecuted();
        }));
        
        $totalCategories = count($this->detectors);
        if ($totalCategories === 0) return 0.0;
        
        return round((count($checkedCategories) / $totalCategories) * 100, 2);
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'enabled_detectors' => [
                'sql_injection' => true,
                'xss' => true,
                'csrf' => true,
                'auth' => true,
                'code_execution' => true,
                'sensitive_data' => true,
                'access_control' => true,
            ],
            'severity_threshold' => 'LOW',
            'max_vulnerabilities' => 1000,
            'scan_depth' => 10,
            'follow_includes' => true,
            'custom_detectors' => [],
            'rules' => [],
            'whitelist_patterns' => [],
            'blacklist_patterns' => [],
        ];
    }

    /**
     * Check if detector is enabled
     */
    private function isDetectorEnabled(string $name): bool
    {
        return $this->config['enabled_detectors'][$name] ?? false;
    }

    /**
     * Check if file should be scanned
     */
    private function shouldScanFile(string $file): bool
    {
        // Check whitelist
        foreach ($this->config['whitelist_patterns'] as $pattern) {
            if (fnmatch($pattern, $file)) {
                return true;
            }
        }
        
        // Check blacklist
        foreach ($this->config['blacklist_patterns'] as $pattern) {
            if (fnmatch($pattern, $file)) {
                return false;
            }
        }
        
        // Default: scan PHP files
        return pathinfo($file, PATHINFO_EXTENSION) === 'php';
    }

    // Additional helper methods would go here...
}

class SecurityAuditException extends \Exception {}