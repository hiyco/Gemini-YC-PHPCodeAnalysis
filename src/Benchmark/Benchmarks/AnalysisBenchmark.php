<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Analysis engine performance benchmark
 */

namespace YcPca\Benchmark\Benchmarks;

use YcPca\Benchmark\AbstractBenchmark;
use YcPca\Ast\PhpAstParser;
use YcPca\Analysis\AnalysisEngine;
use YcPca\Model\FileContext;

/**
 * Benchmark for measuring analysis engine performance
 */
class AnalysisBenchmark extends AbstractBenchmark
{
    private array $testScenarios = [];

    public function __construct(array $testScenarios = [])
    {
        parent::__construct(
            'analysis_engine_performance',
            'Measures analysis engine performance across different code patterns',
            'analysis'
        );

        $this->testScenarios = $testScenarios;
        $this->expectedExecutionTime = 1.0; // 1 second
        $this->expectedMemoryUsage = 30 * 1024 * 1024; // 30MB
    }

    public function setUp(): void
    {
        if (empty($this->testScenarios)) {
            $this->testScenarios = $this->generateTestScenarios();
        }
    }

    public function tearDown(): void
    {
        // Clean up temporary files
        foreach ($this->testScenarios as $scenario) {
            if (isset($scenario['is_temporary']) && $scenario['is_temporary'] && file_exists($scenario['file_path'])) {
                unlink($scenario['file_path']);
            }
        }
    }

    public function execute(PhpAstParser $astParser, AnalysisEngine $analysisEngine): mixed
    {
        $results = [];
        
        foreach ($this->testScenarios as $scenario) {
            $context = new FileContext($scenario['file_path']);
            
            // Parse AST first
            $ast = $astParser->parse($context);
            if ($ast === null) {
                $results[] = [
                    'scenario' => $scenario['name'],
                    'success' => false,
                    'error' => 'AST parsing failed'
                ];
                continue;
            }
            
            // Measure analysis performance
            $startTime = microtime(true);
            $startMemory = memory_get_usage();
            
            $analysisResult = $analysisEngine->analyze($context, $ast);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage();
            
            $results[] = [
                'scenario' => $scenario['name'],
                'scenario_type' => $scenario['type'],
                'file_size' => filesize($scenario['file_path']),
                'analysis_time' => $endTime - $startTime,
                'memory_used' => $endMemory - $startMemory,
                'issues_found' => count($analysisResult->getIssues()),
                'issues_by_severity' => $this->categorizeIssuesBySeverity($analysisResult->getIssues()),
                'issues_by_category' => $this->categorizeIssuesByCategory($analysisResult->getIssues()),
                'success' => true
            ];
        }
        
        return [
            'individual_results' => $results,
            'summary' => $this->calculateAnalysisSummary($results)
        ];
    }

    /**
     * Generate test scenarios with different code patterns
     */
    private function generateTestScenarios(): array
    {
        $scenarios = [];
        $tempDir = sys_get_temp_dir() . '/pca_analysis_benchmark_' . uniqid();
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Clean code scenario
        $cleanFile = $tempDir . '/clean.php';
        file_put_contents($cleanFile, $this->generateCleanCode());
        $scenarios[] = [
            'name' => 'clean_code',
            'type' => 'clean',
            'file_path' => $cleanFile,
            'is_temporary' => true
        ];

        // Security vulnerability scenario
        $vulnFile = $tempDir . '/vulnerable.php';
        file_put_contents($vulnFile, $this->generateVulnerableCode());
        $scenarios[] = [
            'name' => 'security_vulnerabilities',
            'type' => 'security',
            'file_path' => $vulnFile,
            'is_temporary' => true
        ];

        // Code quality issues scenario
        $qualityFile = $tempDir . '/quality_issues.php';
        file_put_contents($qualityFile, $this->generateQualityIssuesCode());
        $scenarios[] = [
            'name' => 'quality_issues',
            'type' => 'quality',
            'file_path' => $qualityFile,
            'is_temporary' => true
        ];

        // Complex code scenario
        $complexFile = $tempDir . '/complex.php';
        file_put_contents($complexFile, $this->generateComplexCode());
        $scenarios[] = [
            'name' => 'complex_code',
            'type' => 'complex',
            'file_path' => $complexFile,
            'is_temporary' => true
        ];

        // Large file scenario
        $largeFile = $tempDir . '/large_file.php';
        file_put_contents($largeFile, $this->generateLargeFileCode());
        $scenarios[] = [
            'name' => 'large_file',
            'type' => 'large',
            'file_path' => $largeFile,
            'is_temporary' => true
        ];

        return $scenarios;
    }

    /**
     * Generate clean, well-written PHP code
     */
    private function generateCleanCode(): string
    {
        return '<?php
declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Clean, well-documented service class
 */
class UserService
{
    private LoggerInterface $logger;
    private array $users = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get user by ID with proper validation
     */
    public function getUserById(int $id): ?array
    {
        if ($id <= 0) {
            $this->logger->warning("Invalid user ID provided", ["id" => $id]);
            return null;
        }

        return $this->users[$id] ?? null;
    }

    /**
     * Create new user with validation
     */
    public function createUser(array $userData): bool
    {
        if (empty($userData["name"]) || empty($userData["email"])) {
            $this->logger->error("Invalid user data provided");
            return false;
        }

        $id = count($this->users) + 1;
        $this->users[$id] = [
            "id" => $id,
            "name" => htmlspecialchars($userData["name"]),
            "email" => filter_var($userData["email"], FILTER_VALIDATE_EMAIL),
            "created_at" => date("Y-m-d H:i:s")
        ];

        $this->logger->info("User created successfully", ["id" => $id]);
        return true;
    }
}';
    }

    /**
     * Generate code with security vulnerabilities
     */
    private function generateVulnerableCode(): string
    {
        return '<?php
declare(strict_types=1);

class VulnerableUserService
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    // SQL Injection vulnerability
    public function getUserById($id)
    {
        $query = "SELECT * FROM users WHERE id = " . $id;
        return mysql_query($query);
    }

    // XSS vulnerability
    public function displayUser($user)
    {
        echo "<h1>Welcome " . $_GET["name"] . "!</h1>";
        echo "<p>Email: " . $user["email"] . "</p>";
    }

    // Command injection
    public function backupUser($userId)
    {
        $command = "mysqldump -u root users > backup_" . $_POST["filename"];
        exec($command);
    }

    // File inclusion vulnerability
    public function loadTemplate($template)
    {
        include($_GET["template"] . ".php");
    }

    // Insecure direct object reference
    public function deleteUser()
    {
        $userId = $_GET["user_id"];
        $query = "DELETE FROM users WHERE id = " . $userId;
        mysql_query($query);
    }

    // Weak cryptography
    public function hashPassword($password)
    {
        return md5($password);
    }

    // Information disclosure
    public function debugInfo()
    {
        phpinfo();
        print_r($_SESSION);
        var_dump($this->db);
    }
}';
    }

    /**
     * Generate code with quality issues
     */
    private function generateQualityIssuesCode(): string
    {
        return '<?php
// Missing declare(strict_types=1)

// Poor class naming and structure
class usr_mgr
{
    public $data; // Public property instead of private
    var $legacy_var; // Using var instead of proper visibility

    // No type hints, poor naming
    function do_stuff($x, $y, $z)
    {
        // Long parameter list, no validation
        global $global_var; // Using global variables
        
        // Deep nesting, complex conditions
        if ($x) {
            if ($y) {
                if ($z) {
                    for ($i = 0; $i < 100; $i++) {
                        if ($i % 2 == 0) {
                            if ($i > 50) {
                                // Code duplication
                                $result = $x + $y + $z;
                                $result = $result * 2;
                                $result = $result - 10;
                                return $result;
                            } else {
                                // Same code duplicated
                                $result = $x + $y + $z;
                                $result = $result * 2;
                                $result = $result - 10;
                                return $result;
                            }
                        }
                    }
                }
            }
        }
    }

    // Magic numbers everywhere
    public function calculate($value)
    {
        if ($value > 42) {
            return $value * 3.14159 + 1337;
        }
        return $value / 2.71828;
    }

    // Unused parameters, dead code
    public function unused_method($param1, $param2, $param3)
    {
        $unused_var = "this is never used";
        return $param1; // param2 and param3 never used
        
        // Dead code after return
        echo "This will never execute";
        $another_unused = true;
    }

    // Poor error handling
    public function risky_operation()
    {
        $file = fopen("nonexistent.txt", "r"); // No error checking
        $content = fread($file, 1000);
        fclose($file);
        return $content;
    }
}';
    }

    /**
     * Generate complex code with many constructs
     */
    private function generateComplexCode(): string
    {
        return '<?php
declare(strict_types=1);

namespace Complex\System;

use Iterator;
use Countable;
use ArrayAccess;

interface ComplexInterface
{
    public function complexOperation(array $data): mixed;
}

trait ComplexTrait
{
    protected array $traitData = [];
    
    public function traitMethod(): string
    {
        return json_encode($this->traitData);
    }
}

abstract class AbstractComplex implements ComplexInterface
{
    use ComplexTrait;
    
    protected const COMPLEX_CONSTANT = "complex_value";
    protected static int $instanceCounter = 0;
    
    abstract protected function abstractMethod(): void;
    
    public function templateMethod(): mixed
    {
        $this->stepOne();
        $this->stepTwo();
        return $this->stepThree();
    }
    
    protected function stepOne(): void {}
    protected function stepTwo(): void {}
    abstract protected function stepThree(): mixed;
}

class ComplexDataStructure extends AbstractComplex implements Iterator, Countable, ArrayAccess
{
    private array $data = [];
    private int $position = 0;
    private ?callable $transformer = null;
    
    public function __construct(array $initialData = [])
    {
        $this->data = $initialData;
        self::$instanceCounter++;
    }
    
    public function complexOperation(array $data): mixed
    {
        return array_reduce($data, function($carry, $item) {
            if ($this->transformer) {
                $item = ($this->transformer)($item);
            }
            
            if (is_array($item)) {
                $carry = array_merge($carry, $this->complexOperation($item));
            } else {
                $carry[] = $item;
            }
            
            return $carry;
        }, []);
    }
    
    public function setTransformer(callable $transformer): self
    {
        $this->transformer = $transformer;
        return $this;
    }
    
    // Iterator interface
    public function rewind(): void
    {
        $this->position = 0;
    }
    
    public function current(): mixed
    {
        return $this->data[$this->position] ?? null;
    }
    
    public function key(): int
    {
        return $this->position;
    }
    
    public function next(): void
    {
        ++$this->position;
    }
    
    public function valid(): bool
    {
        return isset($this->data[$this->position]);
    }
    
    // Countable interface
    public function count(): int
    {
        return count($this->data);
    }
    
    // ArrayAccess interface
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }
    
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }
    
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }
    
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
    
    protected function abstractMethod(): void
    {
        // Complex implementation with closures and generators
        $generator = function() {
            foreach ($this->data as $key => $value) {
                yield $key => $value;
            }
        };
        
        foreach ($generator() as $key => $value) {
            $this->traitData[$key] = $value;
        }
    }
    
    protected function stepThree(): mixed
    {
        return match(count($this->data)) {
            0 => null,
            1 => $this->data[0],
            default => array_slice($this->data, 0, 10)
        };
    }
}';
    }

    /**
     * Generate large file with many classes and methods
     */
    private function generateLargeFileCode(): string
    {
        $code = "<?php\ndeclare(strict_types=1);\n\n";
        
        // Generate multiple classes
        for ($classIndex = 0; $classIndex < 20; $classIndex++) {
            $code .= "class LargeClass{$classIndex}\n{\n";
            $code .= "    private array \$data{$classIndex} = [];\n\n";
            
            // Generate many methods per class
            for ($methodIndex = 0; $methodIndex < 25; $methodIndex++) {
                $code .= "    public function method{$methodIndex}(\$param{$methodIndex}): mixed\n";
                $code .= "    {\n";
                $code .= "        if (is_array(\$param{$methodIndex})) {\n";
                $code .= "            return array_map(function(\$item) {\n";
                $code .= "                return \$item * 2;\n";
                $code .= "            }, \$param{$methodIndex});\n";
                $code .= "        }\n";
                $code .= "        return \$param{$methodIndex};\n";
                $code .= "    }\n\n";
            }
            
            $code .= "}\n\n";
        }
        
        return $code;
    }

    /**
     * Categorize issues by severity
     */
    private function categorizeIssuesBySeverity(array $issues): array
    {
        $categories = [];
        foreach ($issues as $issue) {
            $severity = $issue->getSeverity();
            $categories[$severity] = ($categories[$severity] ?? 0) + 1;
        }
        return $categories;
    }

    /**
     * Categorize issues by category
     */
    private function categorizeIssuesByCategory(array $issues): array
    {
        $categories = [];
        foreach ($issues as $issue) {
            $category = $issue->getCategory();
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }
        return $categories;
    }

    /**
     * Calculate summary statistics from analysis results
     */
    private function calculateAnalysisSummary(array $results): array
    {
        if (empty($results)) {
            return [];
        }

        $successfulResults = array_filter($results, fn($r) => $r['success']);
        $totalTime = 0;
        $totalMemory = 0;
        $totalIssues = 0;
        $times = [];
        $memories = [];
        $scenarioStats = [];

        foreach ($successfulResults as $result) {
            $totalTime += $result['analysis_time'];
            $totalMemory += $result['memory_used'];
            $totalIssues += $result['issues_found'];
            
            $times[] = $result['analysis_time'];
            $memories[] = $result['memory_used'];
            
            $scenarioStats[$result['scenario_type']] = [
                'avg_time' => ($scenarioStats[$result['scenario_type']]['avg_time'] ?? 0) + $result['analysis_time'],
                'avg_issues' => ($scenarioStats[$result['scenario_type']]['avg_issues'] ?? 0) + $result['issues_found'],
                'count' => ($scenarioStats[$result['scenario_type']]['count'] ?? 0) + 1
            ];
        }

        // Calculate averages for scenario types
        foreach ($scenarioStats as $type => &$stats) {
            if ($stats['count'] > 0) {
                $stats['avg_time'] = $stats['avg_time'] / $stats['count'];
                $stats['avg_issues'] = $stats['avg_issues'] / $stats['count'];
            }
        }

        $successCount = count($successfulResults);
        
        return [
            'scenarios_processed' => count($results),
            'successful_analyses' => $successCount,
            'success_rate' => count($results) > 0 ? ($successCount / count($results) * 100) : 0,
            'total_analysis_time' => $totalTime,
            'average_analysis_time' => $successCount > 0 ? ($totalTime / $successCount) : 0,
            'total_memory_used' => $totalMemory,
            'average_memory_used' => $successCount > 0 ? ($totalMemory / $successCount) : 0,
            'total_issues_found' => $totalIssues,
            'average_issues_per_file' => $successCount > 0 ? ($totalIssues / $successCount) : 0,
            'fastest_analysis_time' => !empty($times) ? min($times) : 0,
            'slowest_analysis_time' => !empty($times) ? max($times) : 0,
            'lowest_memory_usage' => !empty($memories) ? min($memories) : 0,
            'highest_memory_usage' => !empty($memories) ? max($memories) : 0,
            'scenario_statistics' => $scenarioStats
        ];
    }
}