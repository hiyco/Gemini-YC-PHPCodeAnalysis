<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Performance tests for analysis system
 */

namespace YcPca\Tests\Performance;

use YcPca\Tests\TestCase;
use YcPca\Ast\PhpAstParser;
use YcPca\Analysis\AnalysisEngine;
use YcPca\Analysis\Analyzer\SecurityAnalyzer;
use YcPca\Analysis\Security\SecurityRuleEngine;
use YcPca\Analysis\Security\Rule\SqlInjectionRule;
use YcPca\Tests\Helpers\TestHelper;

/**
 * Performance tests for the analysis system
 * 
 * These tests verify that the system performs well under various loads
 */
class AnalysisPerformanceTest extends TestCase
{
    private PhpAstParser $astParser;
    private AnalysisEngine $analysisEngine;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->astParser = new PhpAstParser();
        $this->analysisEngine = new AnalysisEngine();
        
        // Set up security analyzer
        $securityRuleEngine = new SecurityRuleEngine();
        $securityRuleEngine->addRule(new SqlInjectionRule());
        $securityAnalyzer = new SecurityAnalyzer($securityRuleEngine);
        
        $this->analysisEngine->addAnalyzer($securityAnalyzer);
    }

    public function testSmallFilePerformance(): void
    {
        $code = $this->getTestPhpCode('basic');
        $context = $this->createFileContext($code);
        
        $performance = TestHelper::measurePerformance(function() use ($context) {
            $ast = $this->astParser->parse($context);
            return $this->analysisEngine->analyze($context, $ast);
        });
        
        $this->assertLessThan(0.1, $performance['execution_time'], 'Small file analysis should be under 100ms');
        $this->assertLessThan(5 * 1024 * 1024, $performance['memory_used'], 'Memory usage should be under 5MB');
    }

    public function testMediumFilePerformance(): void
    {
        $testData = $this->getPerformanceTestData();
        $code = $testData['medium_file'];
        $context = $this->createFileContext($code);
        
        $performance = TestHelper::measurePerformance(function() use ($context) {
            $ast = $this->astParser->parse($context);
            return $this->analysisEngine->analyze($context, $ast);
        });
        
        $this->assertLessThan(1.0, $performance['execution_time'], 'Medium file analysis should be under 1 second');
        $this->assertLessThan(20 * 1024 * 1024, $performance['memory_used'], 'Memory usage should be under 20MB');
    }

    public function testLargeFilePerformance(): void
    {
        $testData = $this->getPerformanceTestData();
        $code = $testData['large_file'];
        $context = $this->createFileContext($code);
        
        $performance = TestHelper::measurePerformance(function() use ($context) {
            $ast = $this->astParser->parse($context);
            return $this->analysisEngine->analyze($context, $ast);
        });
        
        $this->assertLessThan(5.0, $performance['execution_time'], 'Large file analysis should be under 5 seconds');
        $this->assertLessThan(50 * 1024 * 1024, $performance['memory_used'], 'Memory usage should be under 50MB');
    }

    public function testMultipleFilesPerformance(): void
    {
        $fileCount = 10;
        $files = [];
        
        // Create multiple test files
        for ($i = 0; $i < $fileCount; $i++) {
            $code = $this->generateTestFileContent($i);
            $files[] = $this->createFileContext($code, "test_file_{$i}.php");
        }
        
        $performance = TestHelper::measurePerformance(function() use ($files) {
            $results = [];
            foreach ($files as $context) {
                $ast = $this->astParser->parse($context);
                $results[] = $this->analysisEngine->analyze($context, $ast);
            }
            return $results;
        });
        
        $avgTimePerFile = $performance['execution_time'] / $fileCount;
        
        $this->assertLessThan(0.5, $avgTimePerFile, 'Average analysis time per file should be under 500ms');
        $this->assertLessThan(100 * 1024 * 1024, $performance['memory_used'], 'Total memory usage should be reasonable');
    }

    public function testCachingPerformance(): void
    {
        $this->analysisEngine->setCachingEnabled(true);
        
        $code = $this->getTestPhpCode('basic');
        $context = $this->createFileContext($code);
        $ast = $this->astParser->parse($context);
        
        // First run (cache miss)
        $firstRun = TestHelper::measurePerformance(function() use ($context, $ast) {
            return $this->analysisEngine->analyze($context, $ast);
        });
        
        // Second run (cache hit)
        $secondRun = TestHelper::measurePerformance(function() use ($context, $ast) {
            return $this->analysisEngine->analyze($context, $ast);
        });
        
        $this->assertLessThanOrEqual(
            $firstRun['execution_time'], 
            $secondRun['execution_time'],
            'Cached analysis should be faster or equal'
        );
    }

    public function testParallelProcessingPerformance(): void
    {
        $this->skipIfRequirementsNotMet(['parallel_processing_available' => function_exists('pcntl_fork')]);
        
        $fileCount = 5;
        $files = [];
        
        for ($i = 0; $i < $fileCount; $i++) {
            $code = $this->getPerformanceTestData()['medium_file'];
            $files[] = $this->createFileContext($code, "parallel_test_{$i}.php");
        }
        
        // Sequential processing
        $this->analysisEngine->setParallelProcessing(false);
        $sequentialPerformance = $this->measureMultiFileAnalysis($files);
        
        // Parallel processing
        $this->analysisEngine->setParallelProcessing(true);
        $parallelPerformance = $this->measureMultiFileAnalysis($files);
        
        // Parallel should be faster for multiple files
        $this->assertLessThan(
            $sequentialPerformance['execution_time'], 
            $parallelPerformance['execution_time'],
            'Parallel processing should be faster for multiple files'
        );
    }

    public function testMemoryLeakDetection(): void
    {
        $iterations = 10;
        $memoryUsages = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $code = $this->generateTestFileContent($i);
            $context = $this->createFileContext($code);
            
            $ast = $this->astParser->parse($context);
            $this->analysisEngine->analyze($context, $ast);
            
            // Force garbage collection
            gc_collect_cycles();
            
            $memoryUsages[] = memory_get_usage();
        }
        
        // Check for memory leaks - memory usage should not increase significantly
        $firstHalfAvg = array_sum(array_slice($memoryUsages, 0, 5)) / 5;
        $secondHalfAvg = array_sum(array_slice($memoryUsages, 5, 5)) / 5;
        
        $memoryGrowthPercentage = (($secondHalfAvg - $firstHalfAvg) / $firstHalfAvg) * 100;
        
        $this->assertLessThan(20, $memoryGrowthPercentage, 'Memory usage should not grow significantly over iterations');
    }

    public function testScalabilityWithManyRules(): void
    {
        // Create analyzer with many rules
        $securityRuleEngine = new SecurityRuleEngine();
        
        // Add multiple instances of the same rule with different configs
        for ($i = 0; $i < 20; $i++) {
            $rule = new SqlInjectionRule(['rule_instance' => $i]);
            $securityRuleEngine->addRule(clone $rule);
        }
        
        $securityAnalyzer = new SecurityAnalyzer($securityRuleEngine);
        $engine = new AnalysisEngine();
        $engine->addAnalyzer($securityAnalyzer);
        
        $code = $this->getTestPhpCode('sql_injection');
        $context = $this->createFileContext($code);
        
        $performance = TestHelper::measurePerformance(function() use ($engine, $context) {
            $ast = $this->astParser->parse($context);
            return $engine->analyze($context, $ast);
        });
        
        $this->assertLessThan(2.0, $performance['execution_time'], 'Many rules should not severely impact performance');
    }

    public function testComplexFileStructurePerformance(): void
    {
        $code = $this->generateComplexPhpFile();
        $context = $this->createFileContext($code);
        
        $performance = TestHelper::measurePerformance(function() use ($context) {
            $ast = $this->astParser->parse($context);
            return $this->analysisEngine->analyze($context, $ast);
        });
        
        $this->assertLessThan(3.0, $performance['execution_time'], 'Complex file structure should be analyzed efficiently');
        $this->assertLessThan(30 * 1024 * 1024, $performance['memory_used'], 'Memory usage should be reasonable for complex files');
    }

    public function testWorstCaseScenario(): void
    {
        // Create a file with many potential issues
        $code = TestHelper::generateVulnerablePhpCode([
            'sql_injection' => ['dangerous' => true],
            'xss' => ['escaped' => false],
            'eval_usage' => [],
            'file_inclusion' => ['validated' => false],
            'command_injection' => ['escaped' => false]
        ]);
        
        $context = $this->createFileContext($code);
        
        $performance = TestHelper::measurePerformance(function() use ($context) {
            $ast = $this->astParser->parse($context);
            return $this->analysisEngine->analyze($context, $ast);
        });
        
        $this->assertLessThan(5.0, $performance['execution_time'], 'Worst case scenario should complete within reasonable time');
        $this->assertLessThan(50 * 1024 * 1024, $performance['memory_used'], 'Memory usage should be bounded even for worst case');
    }

    private function measureMultiFileAnalysis(array $files): array
    {
        return TestHelper::measurePerformance(function() use ($files) {
            $results = [];
            foreach ($files as $context) {
                $ast = $this->astParser->parse($context);
                $results[] = $this->analysisEngine->analyze($context, $ast);
            }
            return $results;
        });
    }

    private function generateTestFileContent(int $seed): string
    {
        $random = mt_rand($seed, $seed + 1000);
        
        return "<?php
declare(strict_types=1);

class TestClass{$random} {
    private \$property{$random};
    
    public function method{$random}(\$param): string {
        \$query = \"SELECT * FROM table WHERE id = \" . \$param;
        return mysql_query(\$query);
    }
    
    public function anotherMethod{$random}(): array {
        \$data = [];
        for (\$i = 0; \$i < 100; \$i++) {
            \$data[] = \"Item \" . \$i;
        }
        return \$data;
    }
}";
    }

    private function generateComplexPhpFile(): string
    {
        $code = "<?php
declare(strict_types=1);

namespace Complex\\Test;

use Some\\Namespace\\Class1;
use Another\\Namespace\\Class2;

interface ComplexInterface {
    public function complexMethod(array \$data): ?string;
}

abstract class AbstractComplexClass implements ComplexInterface {
    protected const COMPLEX_CONSTANT = 'complex';
    
    abstract protected function abstractMethod(): void;
}

class ComplexClass extends AbstractComplexClass {
    private array \$complexProperty = [];
    
    public function __construct(private readonly string \$injected) {}
    
    public function complexMethod(array \$data): ?string {
        try {
            \$result = [];
            foreach (\$data as \$key => \$value) {
                if (\$value instanceof \\stdClass) {
                    \$result[\$key] = \$this->processObject(\$value);
                } elseif (is_array(\$value)) {
                    \$result[\$key] = \$this->processArray(\$value);
                } else {
                    \$result[\$key] = (string) \$value;
                }
            }
            
            return json_encode(\$result);
        } catch (\\JsonException \$e) {
            error_log('JSON encoding failed: ' . \$e->getMessage());
            return null;
        }
    }
    
    private function processObject(\\stdClass \$obj): array {
        return (array) \$obj;
    }
    
    private function processArray(array \$arr): array {
        return array_map(fn(\$item) => is_object(\$item) ? (array) \$item : \$item, \$arr);
    }
    
    protected function abstractMethod(): void {
        // Complex implementation
        \$closure = function(int \$x, int \$y) use (&\$result) {
            \$result = \$x + \$y;
        };
        
        \$numbers = range(1, 100);
        array_walk(\$numbers, \$closure);
    }
}

trait ComplexTrait {
    public function traitMethod(): void {
        \$anonymous = new class {
            public function method(): string {
                return 'anonymous';
            }
        };
        
        echo \$anonymous->method();
    }
}

enum ComplexEnum: string {
    case OPTION_A = 'a';
    case OPTION_B = 'b';
    
    public function getDescription(): string {
        return match(\$this) {
            self::OPTION_A => 'Option A',
            self::OPTION_B => 'Option B',
        };
    }
}

function complexFunction(mixed \$input): mixed {
    return match(gettype(\$input)) {
        'string' => strtoupper(\$input),
        'integer' => \$input * 2,
        'array' => array_reverse(\$input),
        default => \$input
    };
}";
        
        return $code;
    }
}