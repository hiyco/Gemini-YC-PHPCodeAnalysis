<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Base test case class with common testing utilities
 */

namespace YcPca\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use YcPca\Ast\PhpAstParser;
use YcPca\Analysis\AnalysisEngine;
use YcPca\Model\FileContext;

/**
 * Base test case with common testing utilities and fixtures
 * 
 * Features:
 * - File fixture management
 * - Mock object creation
 * - Common assertions
 * - Test data helpers
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected string $fixturesPath;
    protected string $outputPath;
    
    protected PhpAstParser $astParser;
    protected AnalysisEngine $analysisEngine;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fixturesPath = PCA_TEST_FIXTURES;
        $this->outputPath = PCA_TEST_OUTPUT;
        
        // Initialize common test objects
        $this->astParser = new PhpAstParser();
        $this->analysisEngine = new AnalysisEngine();
        
        // Clean output directory for each test
        $this->cleanOutputDirectory();
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        $this->cleanOutputDirectory();
        
        parent::tearDown();
    }

    /**
     * Create a temporary PHP file with given content
     */
    protected function createTempPhpFile(string $content, string $filename = null): string
    {
        $filename = $filename ?? 'temp_' . uniqid() . '.php';
        $filePath = $this->outputPath . '/' . $filename;
        
        file_put_contents($filePath, $content);
        
        return $filePath;
    }

    /**
     * Load fixture file content
     */
    protected function getFixture(string $filename): string
    {
        $filePath = $this->fixturesPath . '/' . $filename;
        
        if (!file_exists($filePath)) {
            $this->fail("Fixture file not found: {$filename}");
        }
        
        return file_get_contents($filePath);
    }

    /**
     * Create FileContext from fixture
     */
    protected function getFixtureContext(string $filename): FileContext
    {
        $filePath = $this->fixturesPath . '/' . $filename;
        
        if (!file_exists($filePath)) {
            $this->fail("Fixture file not found: {$filename}");
        }
        
        return new FileContext($filePath);
    }

    /**
     * Create FileContext from temporary content
     */
    protected function createFileContext(string $content, string $filename = null): FileContext
    {
        $filePath = $this->createTempPhpFile($content, $filename);
        return new FileContext($filePath);
    }

    /**
     * Assert that an issue exists with specific properties
     */
    protected function assertIssueExists(array $issues, array $expectedProperties): void
    {
        $found = false;
        
        foreach ($issues as $issue) {
            $matches = true;
            
            foreach ($expectedProperties as $property => $expectedValue) {
                $actualValue = match($property) {
                    'rule_id' => $issue->getRuleId(),
                    'severity' => $issue->getSeverity(),
                    'category' => $issue->getCategory(),
                    'line' => $issue->getLine(),
                    'title' => $issue->getTitle(),
                    'description' => $issue->getDescription(),
                    default => $issue->getMetadata()[$property] ?? null
                };
                
                if ($actualValue !== $expectedValue) {
                    $matches = false;
                    break;
                }
            }
            
            if ($matches) {
                $found = true;
                break;
            }
        }
        
        $this->assertTrue($found, 'Expected issue not found: ' . json_encode($expectedProperties));
    }

    /**
     * Assert that no issues exist with specific properties
     */
    protected function assertIssueNotExists(array $issues, array $properties): void
    {
        foreach ($issues as $issue) {
            $matches = true;
            
            foreach ($properties as $property => $expectedValue) {
                $actualValue = match($property) {
                    'rule_id' => $issue->getRuleId(),
                    'severity' => $issue->getSeverity(),
                    'category' => $issue->getCategory(),
                    'line' => $issue->getLine(),
                    default => $issue->getMetadata()[$property] ?? null
                };
                
                if ($actualValue !== $expectedValue) {
                    $matches = false;
                    break;
                }
            }
            
            if ($matches) {
                $this->fail('Unexpected issue found: ' . json_encode($properties));
            }
        }
    }

    /**
     * Assert issue count by severity
     */
    protected function assertIssueCountBySeverity(array $issues, array $expectedCounts): void
    {
        $actualCounts = [];
        
        foreach ($issues as $issue) {
            $severity = $issue->getSeverity();
            $actualCounts[$severity] = ($actualCounts[$severity] ?? 0) + 1;
        }
        
        foreach ($expectedCounts as $severity => $expectedCount) {
            $actualCount = $actualCounts[$severity] ?? 0;
            $this->assertEquals(
                $expectedCount,
                $actualCount,
                "Expected {$expectedCount} {$severity} issues, got {$actualCount}"
            );
        }
    }

    /**
     * Create mock analyzer for testing
     */
    protected function createMockAnalyzer(array $issues = []): object
    {
        $analyzer = $this->createMock(\YcPca\Analysis\AnalyzerInterface::class);
        
        $analyzer->method('analyze')
                 ->willReturn($issues);
        
        $analyzer->method('isEnabled')
                 ->willReturn(true);
        
        $analyzer->method('getAnalyzerId')
                 ->willReturn('mock_analyzer');
        
        return $analyzer;
    }

    /**
     * Create test PHP code with common patterns
     */
    protected function getTestPhpCode(string $type = 'basic'): string
    {
        return match($type) {
            'sql_injection' => '<?php
class TestClass {
    public function unsafeQuery($id) {
        $query = "SELECT * FROM users WHERE id = " . $id;
        return mysql_query($query);
    }
}',
            'xss_vulnerability' => '<?php
class TestClass {
    public function displayData($data) {
        echo "<div>" . $data . "</div>";
    }
}',
            'eval_usage' => '<?php
class TestClass {
    public function executeCode($code) {
        return eval($code);
    }
}',
            'unused_variable' => '<?php
class TestClass {
    public function processData($data) {
        $timestamp = time();
        return $data;
    }
}',
            'line_too_long' => '<?php
class TestClass {
    public function aVeryLongMethodNameThatExceedsTypicalLineLengthLimitsAndShouldBeRefactoredToSomethingShorterAndMoreMeaningful($param1, $param2, $param3, $param4) {
        return $param1 + $param2 + $param3 + $param4;
    }
}',
            'basic' => '<?php
declare(strict_types=1);

class TestClass {
    private string $property;
    
    public function __construct(string $value) {
        $this->property = $value;
    }
    
    public function getProperty(): string {
        return $this->property;
    }
}',
            default => '<?php echo "Hello, World!";'
        };
    }

    /**
     * Get performance test data
     */
    protected function getPerformanceTestData(): array
    {
        return [
            'small_file' => str_repeat($this->getTestPhpCode('basic'), 10),
            'medium_file' => str_repeat($this->getTestPhpCode('basic'), 100),
            'large_file' => str_repeat($this->getTestPhpCode('basic'), 1000),
        ];
    }

    /**
     * Clean output directory
     */
    private function cleanOutputDirectory(): void
    {
        if (is_dir($this->outputPath)) {
            $files = glob($this->outputPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Assert execution time is within acceptable limits
     */
    protected function assertExecutionTime(callable $callback, float $maxSeconds = 1.0): mixed
    {
        $startTime = microtime(true);
        $result = $callback();
        $executionTime = microtime(true) - $startTime;
        
        $this->assertLessThan(
            $maxSeconds,
            $executionTime,
            "Execution time ({$executionTime}s) exceeded maximum ({$maxSeconds}s)"
        );
        
        return $result;
    }

    /**
     * Assert memory usage is within acceptable limits
     */
    protected function assertMemoryUsage(callable $callback, int $maxBytes = 50 * 1024 * 1024): mixed
    {
        $initialMemory = memory_get_usage(true);
        $result = $callback();
        $finalMemory = memory_get_usage(true);
        $memoryUsed = $finalMemory - $initialMemory;
        
        $this->assertLessThan(
            $maxBytes,
            $memoryUsed,
            "Memory usage ({$memoryUsed} bytes) exceeded maximum ({$maxBytes} bytes)"
        );
        
        return $result;
    }

    /**
     * Skip test if requirements are not met
     */
    protected function skipIfRequirementsNotMet(array $requirements): void
    {
        foreach ($requirements as $requirement => $condition) {
            if (!$condition) {
                $this->markTestSkipped("Requirement not met: {$requirement}");
            }
        }
    }
}