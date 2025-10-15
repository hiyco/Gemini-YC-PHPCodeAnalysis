<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Unit tests for Analysis Engine
 */

namespace YcPca\Tests\Unit\Analysis;

use YcPca\Tests\TestCase;
use YcPca\Analysis\AnalysisEngine;
use YcPca\Analysis\AnalyzerInterface;
use YcPca\Analysis\Issue\Issue;
use YcPca\Model\AnalysisResult;
use YcPca\Tests\Helpers\TestHelper;

/**
 * Test Analysis Engine functionality
 * 
 * @covers \YcPca\Analysis\AnalysisEngine
 */
class AnalysisEngineTest extends TestCase
{
    private AnalysisEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new AnalysisEngine();
    }

    public function testEngineInitialization(): void
    {
        $this->assertInstanceOf(AnalysisEngine::class, $this->engine);
        $this->assertEmpty($this->engine->getAnalyzers());
        $this->assertFalse($this->engine->isParallelProcessingEnabled());
        $this->assertFalse($this->engine->isCachingEnabled());
    }

    public function testAddAnalyzer(): void
    {
        $analyzer = $this->createMockAnalyzer();
        
        $result = $this->engine->addAnalyzer($analyzer);
        
        $this->assertInstanceOf(AnalysisEngine::class, $result);
        $this->assertEquals($this->engine, $result); // Should return self for chaining
        
        $analyzers = $this->engine->getAnalyzers();
        $this->assertCount(1, $analyzers);
        $this->assertContains($analyzer, $analyzers);
    }

    public function testAddMultipleAnalyzers(): void
    {
        $analyzer1 = $this->createMockAnalyzer(['mock_analyzer_1']);
        $analyzer2 = $this->createMockAnalyzer(['mock_analyzer_2']);
        
        $this->engine->addAnalyzer($analyzer1)
                     ->addAnalyzer($analyzer2);
        
        $analyzers = $this->engine->getAnalyzers();
        $this->assertCount(2, $analyzers);
    }

    public function testRemoveAnalyzer(): void
    {
        $analyzer = $this->createMockAnalyzer();
        
        $this->engine->addAnalyzer($analyzer);
        $this->assertCount(1, $this->engine->getAnalyzers());
        
        $result = $this->engine->removeAnalyzer('mock_analyzer');
        
        $this->assertInstanceOf(AnalysisEngine::class, $result);
        $this->assertEmpty($this->engine->getAnalyzers());
    }

    public function testRemoveNonExistentAnalyzer(): void
    {
        $result = $this->engine->removeAnalyzer('non_existent');
        
        $this->assertInstanceOf(AnalysisEngine::class, $result);
        $this->assertEmpty($this->engine->getAnalyzers());
    }

    public function testGetAnalyzer(): void
    {
        $analyzer = $this->createMockAnalyzer();
        $this->engine->addAnalyzer($analyzer);
        
        $retrieved = $this->engine->getAnalyzer('mock_analyzer');
        
        $this->assertSame($analyzer, $retrieved);
    }

    public function testGetNonExistentAnalyzer(): void
    {
        $retrieved = $this->engine->getAnalyzer('non_existent');
        
        $this->assertNull($retrieved);
    }

    public function testAnalyzeWithoutAnalyzers(): void
    {
        $context = $this->createFileContext('<?php echo "test";');
        $ast = [];
        
        $result = $this->engine->analyze($context, $ast);
        
        $this->assertInstanceOf(AnalysisResult::class, $result);
        $this->assertEmpty($result->getIssues());
    }

    public function testAnalyzeWithSingleAnalyzer(): void
    {
        $issues = [
            TestHelper::createTestIssue(['title' => 'Test Issue 1']),
            TestHelper::createTestIssue(['title' => 'Test Issue 2'])
        ];
        
        $analyzer = $this->createMockAnalyzer($issues);
        $this->engine->addAnalyzer($analyzer);
        
        $context = $this->createFileContext('<?php echo "test";');
        $ast = [];
        
        $result = $this->engine->analyze($context, $ast);
        
        $this->assertInstanceOf(AnalysisResult::class, $result);
        $this->assertCount(2, $result->getIssues());
        $this->assertEquals('Test Issue 1', $result->getIssues()[0]->getTitle());
        $this->assertEquals('Test Issue 2', $result->getIssues()[1]->getTitle());
    }

    public function testAnalyzeWithMultipleAnalyzers(): void
    {
        $issues1 = [TestHelper::createTestIssue(['title' => 'Analyzer 1 Issue'])];
        $issues2 = [TestHelper::createTestIssue(['title' => 'Analyzer 2 Issue'])];
        
        $analyzer1 = $this->createMockAnalyzer($issues1, 'analyzer_1');
        $analyzer2 = $this->createMockAnalyzer($issues2, 'analyzer_2');
        
        $this->engine->addAnalyzer($analyzer1)
                     ->addAnalyzer($analyzer2);
        
        $context = $this->createFileContext('<?php echo "test";');
        $ast = [];
        
        $result = $this->engine->analyze($context, $ast);
        
        $this->assertCount(2, $result->getIssues());
        
        $issueTitles = array_map(fn($issue) => $issue->getTitle(), $result->getIssues());
        $this->assertContains('Analyzer 1 Issue', $issueTitles);
        $this->assertContains('Analyzer 2 Issue', $issueTitles);
    }

    public function testAnalyzeWithDisabledAnalyzer(): void
    {
        $issues = [TestHelper::createTestIssue(['title' => 'Should not appear'])];
        
        $analyzer = $this->createMockAnalyzer($issues);
        $analyzer->method('isEnabled')->willReturn(false);
        
        $this->engine->addAnalyzer($analyzer);
        
        $context = $this->createFileContext('<?php echo "test";');
        $ast = [];
        
        $result = $this->engine->analyze($context, $ast);
        
        $this->assertEmpty($result->getIssues());
    }

    public function testAnalyzeWithAnalyzerException(): void
    {
        $analyzer = $this->createMockAnalyzer();
        $analyzer->method('analyze')
                 ->willThrowException(new \RuntimeException('Analyzer failed'));
        
        $this->engine->addAnalyzer($analyzer);
        
        $context = $this->createFileContext('<?php echo "test";');
        $ast = [];
        
        // Engine should handle analyzer exceptions gracefully
        $result = $this->engine->analyze($context, $ast);
        
        $this->assertInstanceOf(AnalysisResult::class, $result);
        $this->assertEmpty($result->getIssues());
    }

    public function testParallelProcessingConfiguration(): void
    {
        $this->assertFalse($this->engine->isParallelProcessingEnabled());
        
        $result = $this->engine->setParallelProcessing(true);
        
        $this->assertInstanceOf(AnalysisEngine::class, $result);
        $this->assertTrue($this->engine->isParallelProcessingEnabled());
        
        $this->engine->setParallelProcessing(false);
        $this->assertFalse($this->engine->isParallelProcessingEnabled());
    }

    public function testCachingConfiguration(): void
    {
        $this->assertFalse($this->engine->isCachingEnabled());
        
        $result = $this->engine->setCachingEnabled(true);
        
        $this->assertInstanceOf(AnalysisEngine::class, $result);
        $this->assertTrue($this->engine->isCachingEnabled());
        
        $this->engine->setCachingEnabled(false);
        $this->assertFalse($this->engine->isCachingEnabled());
    }

    public function testGetStatistics(): void
    {
        // Add some analyzers and run analysis
        $analyzer1 = $this->createMockAnalyzer([TestHelper::createTestIssue()], 'analyzer_1');
        $analyzer2 = $this->createMockAnalyzer([TestHelper::createTestIssue()], 'analyzer_2');
        
        $this->engine->addAnalyzer($analyzer1)
                     ->addAnalyzer($analyzer2);
        
        $context = $this->createFileContext('<?php echo "test";');
        $ast = [];
        
        $this->engine->analyze($context, $ast);
        
        $stats = $this->engine->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('analyzers_count', $stats);
        $this->assertArrayHasKey('files_analyzed', $stats);
        $this->assertArrayHasKey('total_issues_found', $stats);
        $this->assertArrayHasKey('execution_time', $stats);
        
        $this->assertEquals(2, $stats['analyzers_count']);
        $this->assertEquals(1, $stats['files_analyzed']);
        $this->assertEquals(2, $stats['total_issues_found']);
    }

    public function testResetStatistics(): void
    {
        // Run some analysis first
        $analyzer = $this->createMockAnalyzer([TestHelper::createTestIssue()]);
        $this->engine->addAnalyzer($analyzer);
        
        $context = $this->createFileContext('<?php echo "test";');
        $this->engine->analyze($context, []);
        
        $statsBefore = $this->engine->getStatistics();
        $this->assertGreaterThan(0, $statsBefore['files_analyzed']);
        
        $result = $this->engine->resetStatistics();
        
        $this->assertInstanceOf(AnalysisEngine::class, $result);
        
        $statsAfter = $this->engine->getStatistics();
        $this->assertEquals(0, $statsAfter['files_analyzed']);
        $this->assertEquals(0, $statsAfter['total_issues_found']);
    }

    public function testAnalyzePerformance(): void
    {
        // Create multiple analyzers with issues
        for ($i = 0; $i < 5; $i++) {
            $issues = TestHelper::createTestIssues(10);
            $analyzer = $this->createMockAnalyzer($issues, "analyzer_{$i}");
            $this->engine->addAnalyzer($analyzer);
        }
        
        $context = $this->createFileContext($this->getTestPhpCode('basic'));
        $ast = [];
        
        $startTime = microtime(true);
        $result = $this->engine->analyze($context, $ast);
        $endTime = microtime(true);
        
        $executionTime = $endTime - $startTime;
        
        $this->assertInstanceOf(AnalysisResult::class, $result);
        $this->assertCount(50, $result->getIssues()); // 5 analyzers * 10 issues each
        $this->assertLessThan(1.0, $executionTime, 'Analysis should complete within 1 second');
    }

    public function testAnalyzeMemoryUsage(): void
    {
        $analyzer = $this->createMockAnalyzer(TestHelper::createTestIssues(100));
        $this->engine->addAnalyzer($analyzer);
        
        $context = $this->createFileContext($this->getTestPhpCode('basic'));
        $ast = [];
        
        $initialMemory = memory_get_usage();
        $result = $this->engine->analyze($context, $ast);
        $finalMemory = memory_get_usage();
        
        $memoryUsed = $finalMemory - $initialMemory;
        
        $this->assertInstanceOf(AnalysisResult::class, $result);
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 'Memory usage should be reasonable');
    }

    private function createMockAnalyzer(array $issues = [], string $id = 'mock_analyzer'): AnalyzerInterface
    {
        $analyzer = $this->createMock(AnalyzerInterface::class);
        
        $analyzer->method('analyze')
                 ->willReturn($issues);
        
        $analyzer->method('isEnabled')
                 ->willReturn(true);
        
        $analyzer->method('getAnalyzerId')
                 ->willReturn($id);
        
        $analyzer->method('setEnabled')
                 ->willReturnSelf();
        
        return $analyzer;
    }
}