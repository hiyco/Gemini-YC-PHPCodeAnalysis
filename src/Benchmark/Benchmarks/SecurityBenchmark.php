<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Security analysis performance benchmark
 */

namespace YcPca\Benchmark\Benchmarks;

use YcPca\Benchmark\AbstractBenchmark;
use YcPca\Ast\PhpAstParser;
use YcPca\Analysis\AnalysisEngine;
use YcPca\Model\FileContext;

/**
 * Benchmark for measuring security analysis performance
 */
class SecurityBenchmark extends AbstractBenchmark
{
    private array $securityTestCases = [];

    public function __construct()
    {
        parent::__construct(
            'security_analysis_performance',
            'Measures security analysis performance against OWASP Top 10 vulnerabilities',
            'security'
        );

        $this->expectedExecutionTime = 2.0; // 2 seconds
        $this->expectedMemoryUsage = 25 * 1024 * 1024; // 25MB
    }

    public function setUp(): void
    {
        $this->securityTestCases = $this->generateSecurityTestCases();
    }

    public function tearDown(): void
    {
        // Clean up temporary files
        foreach ($this->securityTestCases as $testCase) {
            if (isset($testCase['is_temporary']) && $testCase['is_temporary'] && file_exists($testCase['file_path'])) {
                unlink($testCase['file_path']);
            }
        }
    }

    public function execute(PhpAstParser $astParser, AnalysisEngine $analysisEngine): mixed
    {
        $results = [];
        
        foreach ($this->securityTestCases as $testCase) {
            $context = new FileContext($testCase['file_path']);
            
            // Parse AST
            $ast = $astParser->parse($context);
            if ($ast === null) {
                $results[] = [
                    'vulnerability_type' => $testCase['vulnerability_type'],
                    'success' => false,
                    'error' => 'AST parsing failed'
                ];
                continue;
            }
            
            // Measure security analysis performance
            $startTime = microtime(true);
            $startMemory = memory_get_usage();
            
            $analysisResult = $analysisEngine->analyze($context, $ast);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage();
            
            $securityIssues = array_filter($analysisResult->getIssues(), function($issue) {
                return $issue->getCategory() === 'security';
            });

            $results[] = [
                'vulnerability_type' => $testCase['vulnerability_type'],
                'owasp_category' => $testCase['owasp_category'],
                'file_size' => filesize($testCase['file_path']),
                'analysis_time' => $endTime - $startTime,
                'memory_used' => $endMemory - $startMemory,
                'total_issues' => count($analysisResult->getIssues()),
                'security_issues' => count($securityIssues),
                'expected_detections' => $testCase['expected_detections'],
                'detection_rate' => $testCase['expected_detections'] > 0 ? (count($securityIssues) / $testCase['expected_detections']) : 0,
                'security_issues_by_severity' => $this->categorizeSecurityIssuesBySeverity($securityIssues),
                'vulnerability_coverage' => $this->assessVulnerabilityCoverage($securityIssues, $testCase),
                'success' => true
            ];
        }
        
        return [
            'individual_results' => $results,
            'summary' => $this->calculateSecuritySummary($results),
            'owasp_coverage' => $this->calculateOwaspCoverage($results)
        ];
    }

    /**
     * Generate security test cases covering OWASP Top 10
     */
    private function generateSecurityTestCases(): array
    {
        $testCases = [];
        $tempDir = sys_get_temp_dir() . '/pca_security_benchmark_' . uniqid();
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // A01:2021 – Broken Access Control
        $accessControlFile = $tempDir . '/access_control.php';
        file_put_contents($accessControlFile, $this->generateAccessControlVulnerabilities());
        $testCases[] = [
            'vulnerability_type' => 'broken_access_control',
            'owasp_category' => 'A01:2021',
            'file_path' => $accessControlFile,
            'expected_detections' => 3,
            'is_temporary' => true
        ];

        // A02:2021 – Cryptographic Failures
        $cryptoFile = $tempDir . '/crypto_failures.php';
        file_put_contents($cryptoFile, $this->generateCryptographicFailures());
        $testCases[] = [
            'vulnerability_type' => 'cryptographic_failures',
            'owasp_category' => 'A02:2021',
            'file_path' => $cryptoFile,
            'expected_detections' => 4,
            'is_temporary' => true
        ];

        // A03:2021 – Injection
        $injectionFile = $tempDir . '/injection.php';
        file_put_contents($injectionFile, $this->generateInjectionVulnerabilities());
        $testCases[] = [
            'vulnerability_type' => 'injection',
            'owasp_category' => 'A03:2021',
            'file_path' => $injectionFile,
            'expected_detections' => 6,
            'is_temporary' => true
        ];

        // A04:2021 – Insecure Design
        $insecureDesignFile = $tempDir . '/insecure_design.php';
        file_put_contents($insecureDesignFile, $this->generateInsecureDesign());
        $testCases[] = [
            'vulnerability_type' => 'insecure_design',
            'owasp_category' => 'A04:2021',
            'file_path' => $insecureDesignFile,
            'expected_detections' => 3,
            'is_temporary' => true
        ];

        // A05:2021 – Security Misconfiguration
        $misconfigFile = $tempDir . '/security_misconfig.php';
        file_put_contents($misconfigFile, $this->generateSecurityMisconfiguration());
        $testCases[] = [
            'vulnerability_type' => 'security_misconfiguration',
            'owasp_category' => 'A05:2021',
            'file_path' => $misconfigFile,
            'expected_detections' => 4,
            'is_temporary' => true
        ];

        return $testCases;
    }

    private function generateAccessControlVulnerabilities(): string
    {
        return '<?php
class UserController
{
    // Insecure Direct Object Reference
    public function getUserData()
    {
        $userId = $_GET["user_id"];
        $query = "SELECT * FROM users WHERE id = " . $userId;
        return $this->db->query($query);
    }

    // Missing Authorization Check
    public function deleteUser()
    {
        $userId = $_POST["user_id"];
        $query = "DELETE FROM users WHERE id = " . $userId;
        $this->db->query($query);
    }

    // Privilege Escalation
    public function promoteUser()
    {
        $userId = $_POST["user_id"];
        $role = $_POST["role"]; // No validation if user can set admin role
        $query = "UPDATE users SET role = \'$role\' WHERE id = $userId";
        $this->db->query($query);
    }
}';
    }

    private function generateCryptographicFailures(): string
    {
        return '<?php
class CryptoService
{
    // Weak hashing algorithm
    public function hashPassword($password)
    {
        return md5($password);
    }

    // Hardcoded encryption key
    public function encryptData($data)
    {
        $key = "hardcoded_key_123";
        return openssl_encrypt($data, "AES-128-ECB", $key);
    }

    // Weak random number generation
    public function generateToken()
    {
        return rand(1000, 9999);
    }

    // Storing sensitive data in plain text
    public function storeCredentials($username, $password)
    {
        file_put_contents("credentials.txt", "$username:$password\n", FILE_APPEND);
    }
}';
    }

    private function generateInjectionVulnerabilities(): string
    {
        return '<?php
class InjectionVulns
{
    // SQL Injection
    public function getUserByName($name)
    {
        $query = "SELECT * FROM users WHERE name = \'" . $_GET["name"] . "\'";
        return mysql_query($query);
    }

    // Command Injection
    public function pingHost($host)
    {
        $command = "ping -c 4 " . $_POST["host"];
        return exec($command);
    }

    // LDAP Injection
    public function searchUser($username)
    {
        $filter = "(uid=" . $username . ")";
        return ldap_search($this->connection, "dc=example,dc=com", $filter);
    }

    // XPath Injection
    public function findUser($name)
    {
        $xpath = "//user[name=\'" . $_GET["name"] . "\']";
        return $this->xml->xpath($xpath);
    }

    // NoSQL Injection
    public function findDocument($criteria)
    {
        $query = json_decode($_POST["query"], true);
        return $this->mongodb->find($query);
    }

    // Code Injection
    public function evaluateExpression($expression)
    {
        eval("$result = " . $_GET["expr"] . ";");
        return $result;
    }
}';
    }

    private function generateInsecureDesign(): string
    {
        return '<?php
class InsecureDesign
{
    // No rate limiting on authentication
    public function login($username, $password)
    {
        // No attempt limiting - allows brute force
        if ($this->authenticate($username, $password)) {
            return $this->generateSession($username);
        }
        return false;
    }

    // Insecure password recovery
    public function resetPassword($email)
    {
        // Weak security question implementation
        $question = $this->getSecurityQuestion($email);
        echo "Your security question: " . $question;
        
        if ($_POST["answer"] === $this->getSecurityAnswer($email)) {
            $newPassword = "password123"; // Predictable password
            $this->updatePassword($email, $newPassword);
        }
    }

    // Business logic bypass
    public function purchaseItem($itemId, $price)
    {
        // Price comes from client - can be manipulated
        $clientPrice = $_POST["price"];
        if ($clientPrice > 0) {
            $this->processPayment($clientPrice);
            return $this->fulfillOrder($itemId);
        }
    }
}';
    }

    private function generateSecurityMisconfiguration(): string
    {
        return '<?php
// Debug mode enabled in production
error_reporting(E_ALL);
ini_set("display_errors", 1);

class SecurityMisconfig
{
    // Information disclosure
    public function showSystemInfo()
    {
        phpinfo(); // Exposes system information
        print_r($_SERVER); // Exposes server variables
    }

    // Default credentials
    private $adminPassword = "admin";
    private $dbPassword = "password";

    // Unnecessary features enabled
    public function executeCommand()
    {
        // Shell execution enabled without restrictions
        if (isset($_GET["cmd"])) {
            return shell_exec($_GET["cmd"]);
        }
    }

    // Missing security headers
    public function serveContent()
    {
        // No X-Frame-Options, X-XSS-Protection, etc.
        echo "<html><body>Content</body></html>";
    }
}';
    }

    /**
     * Categorize security issues by severity
     */
    private function categorizeSecurityIssuesBySeverity(array $securityIssues): array
    {
        $categories = [];
        foreach ($securityIssues as $issue) {
            $severity = $issue->getSeverity();
            $categories[$severity] = ($categories[$severity] ?? 0) + 1;
        }
        return $categories;
    }

    /**
     * Assess vulnerability coverage for a test case
     */
    private function assessVulnerabilityCoverage(array $securityIssues, array $testCase): array
    {
        // This would be more sophisticated in a real implementation
        // For now, we'll do basic categorization
        $coverageMap = [
            'broken_access_control' => ['insecure_direct_object_reference', 'missing_authorization', 'privilege_escalation'],
            'cryptographic_failures' => ['weak_hashing', 'hardcoded_keys', 'weak_random', 'plain_text_storage'],
            'injection' => ['sql_injection', 'command_injection', 'ldap_injection', 'xpath_injection', 'nosql_injection', 'code_injection'],
            'insecure_design' => ['no_rate_limiting', 'insecure_recovery', 'business_logic_bypass'],
            'security_misconfiguration' => ['information_disclosure', 'default_credentials', 'unnecessary_features', 'missing_security_headers']
        ];

        $vulnerabilityType = $testCase['vulnerability_type'];
        $expectedVulns = $coverageMap[$vulnerabilityType] ?? [];
        
        $detectedVulns = [];
        foreach ($securityIssues as $issue) {
            $ruleId = $issue->getRuleId();
            foreach ($expectedVulns as $expectedVuln) {
                if (strpos($ruleId, $expectedVuln) !== false || strpos($issue->getTitle(), $expectedVuln) !== false) {
                    $detectedVulns[] = $expectedVuln;
                }
            }
        }

        return [
            'expected_vulnerabilities' => $expectedVulns,
            'detected_vulnerabilities' => array_unique($detectedVulns),
            'coverage_percentage' => count($expectedVulns) > 0 ? (count(array_unique($detectedVulns)) / count($expectedVulns) * 100) : 0
        ];
    }

    /**
     * Calculate overall security analysis summary
     */
    private function calculateSecuritySummary(array $results): array
    {
        $successfulResults = array_filter($results, fn($r) => $r['success']);
        $totalTime = 0;
        $totalMemory = 0;
        $totalSecurityIssues = 0;
        $totalExpectedDetections = 0;
        $times = [];
        $detectionRates = [];

        foreach ($successfulResults as $result) {
            $totalTime += $result['analysis_time'];
            $totalMemory += $result['memory_used'];
            $totalSecurityIssues += $result['security_issues'];
            $totalExpectedDetections += $result['expected_detections'];
            
            $times[] = $result['analysis_time'];
            $detectionRates[] = $result['detection_rate'];
        }

        $successCount = count($successfulResults);

        return [
            'test_cases_processed' => count($results),
            'successful_analyses' => $successCount,
            'total_analysis_time' => $totalTime,
            'average_analysis_time' => $successCount > 0 ? ($totalTime / $successCount) : 0,
            'total_memory_used' => $totalMemory,
            'average_memory_used' => $successCount > 0 ? ($totalMemory / $successCount) : 0,
            'total_security_issues_found' => $totalSecurityIssues,
            'total_expected_detections' => $totalExpectedDetections,
            'overall_detection_rate' => $totalExpectedDetections > 0 ? ($totalSecurityIssues / $totalExpectedDetections * 100) : 0,
            'average_detection_rate' => !empty($detectionRates) ? (array_sum($detectionRates) / count($detectionRates) * 100) : 0,
            'fastest_analysis_time' => !empty($times) ? min($times) : 0,
            'slowest_analysis_time' => !empty($times) ? max($times) : 0,
            'best_detection_rate' => !empty($detectionRates) ? max($detectionRates) * 100 : 0,
            'worst_detection_rate' => !empty($detectionRates) ? min($detectionRates) * 100 : 0
        ];
    }

    /**
     * Calculate OWASP Top 10 coverage
     */
    private function calculateOwaspCoverage(array $results): array
    {
        $owaspCategories = [];
        $totalDetectionsByCategory = [];
        $totalExpectedByCategory = [];

        foreach ($results as $result) {
            if (!$result['success']) continue;

            $category = $result['owasp_category'];
            $owaspCategories[$category] = [
                'vulnerability_type' => $result['vulnerability_type'],
                'security_issues_found' => $result['security_issues'],
                'expected_detections' => $result['expected_detections'],
                'detection_rate' => $result['detection_rate'] * 100,
                'analysis_time' => $result['analysis_time']
            ];

            $totalDetectionsByCategory[$category] = ($totalDetectionsByCategory[$category] ?? 0) + $result['security_issues'];
            $totalExpectedByCategory[$category] = ($totalExpectedByCategory[$category] ?? 0) + $result['expected_detections'];
        }

        // Calculate overall category performance
        foreach ($owaspCategories as $category => &$data) {
            $data['category_coverage'] = $totalExpectedByCategory[$category] > 0 
                ? ($totalDetectionsByCategory[$category] / $totalExpectedByCategory[$category] * 100) 
                : 0;
        }

        return [
            'categories_tested' => count($owaspCategories),
            'category_details' => $owaspCategories,
            'overall_owasp_coverage' => array_sum($totalExpectedByCategory) > 0 
                ? (array_sum($totalDetectionsByCategory) / array_sum($totalExpectedByCategory) * 100) 
                : 0
        ];
    }
}