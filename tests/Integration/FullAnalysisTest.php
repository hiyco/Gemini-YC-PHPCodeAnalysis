<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Integration tests for full analysis workflow
 */

namespace YcPca\Tests\Integration;

use YcPca\Tests\TestCase;
use YcPca\Ast\PhpAstParser;
use YcPca\Analysis\AnalysisEngine;
use YcPca\Analysis\Analyzer\SyntaxAnalyzer;
use YcPca\Analysis\Analyzer\SecurityAnalyzer;
use YcPca\Analysis\Syntax\SyntaxRuleEngine;
use YcPca\Analysis\Security\SecurityRuleEngine;
use YcPca\Analysis\Security\Rule\SqlInjectionRule;
use YcPca\Analysis\Issue\Issue;
use YcPca\Model\FileContext;

/**
 * Integration tests for complete analysis workflow
 * 
 * Tests the entire pipeline from file parsing to issue detection
 */
class FullAnalysisTest extends TestCase
{
    private PhpAstParser $astParser;
    private AnalysisEngine $analysisEngine;
    private SyntaxAnalyzer $syntaxAnalyzer;
    private SecurityAnalyzer $securityAnalyzer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->astParser = new PhpAstParser();
        $this->analysisEngine = new AnalysisEngine();
        
        // Set up syntax analyzer
        $syntaxRuleEngine = new SyntaxRuleEngine();
        $this->syntaxAnalyzer = new SyntaxAnalyzer($syntaxRuleEngine);
        
        // Set up security analyzer
        $securityRuleEngine = new SecurityRuleEngine();
        $securityRuleEngine->addRule(new SqlInjectionRule());
        $this->securityAnalyzer = new SecurityAnalyzer($securityRuleEngine);
        
        // Add analyzers to engine
        $this->analysisEngine->addAnalyzer($this->syntaxAnalyzer)
                             ->addAnalyzer($this->securityAnalyzer);
    }

    public function testAnalyzeVulnerableFile(): void
    {
        // Use the vulnerable test fixture
        $context = $this->getFixtureContext('sample_vulnerable.php');
        $ast = $this->astParser->parse($context);
        
        $this->assertNotNull($ast, 'AST should be parsed successfully');
        
        $result = $this->analysisEngine->analyze($context, $ast);
        
        $this->assertNotNull($result);
        $issues = $result->getIssues();
        $this->assertNotEmpty($issues, 'Vulnerable file should have security issues');
        
        // Check for SQL injection issues
        $sqlInjectionIssues = array_filter($issues, function($issue) {
            return strpos($issue->getTitle(), 'SQL Injection') !== false ||
                   $issue->getRuleId() === 'sql_injection';
        });
        
        $this->assertNotEmpty($sqlInjectionIssues, 'Should detect SQL injection vulnerabilities');
        
        // Check for high severity issues
        $highSeverityIssues = array_filter($issues, function($issue) {
            return in_array($issue->getSeverity(), [Issue::SEVERITY_HIGH, Issue::SEVERITY_CRITICAL]);
        });
        
        $this->assertNotEmpty($highSeverityIssues, 'Should have high severity issues');
    }

    public function testAnalyzeCleanFile(): void
    {
        // Use the clean test fixture
        $context = $this->getFixtureContext('sample_clean.php');
        $ast = $this->astParser->parse($context);
        
        $this->assertNotNull($ast, 'AST should be parsed successfully');
        
        $result = $this->analysisEngine->analyze($context, $ast);
        
        $this->assertNotNull($result);
        $issues = $result->getIssues();
        
        // Clean file should have minimal or no security issues
        $securityIssues = array_filter($issues, function($issue) {
            return $issue->getCategory() === Issue::CATEGORY_SECURITY;
        });
        
        $this->assertEmpty($securityIssues, 'Clean file should not have security issues');
    }

    public function testAnalyzeQualityIssuesFile(): void
    {
        // Use the quality issues test fixture
        $context = $this->getFixtureContext('sample_quality_issues.php');
        $ast = $this->astParser->parse($context);
        
        $this->assertNotNull($ast, 'AST should be parsed successfully');
        
        $result = $this->analysisEngine->analyze($context, $ast);
        
        $this->assertNotNull($result);
        $issues = $result->getIssues();
        $this->assertNotEmpty($issues, 'Quality issues file should have issues');
        
        // Check for quality issues
        $qualityIssues = array_filter($issues, function($issue) {
            return $issue->getCategory() === Issue::CATEGORY_QUALITY;
        });
        
        $this->assertNotEmpty($qualityIssues, 'Should detect code quality issues');
    }

    public function testAnalysisPerformanceWithMultipleFiles(): void
    {
        $files = [
            'sample_vulnerable.php',
            'sample_clean.php',
            'sample_quality_issues.php'
        ];
        
        $startTime = microtime(true);
        $totalIssues = 0;
        
        foreach ($files as $filename) {
            $context = $this->getFixtureContext($filename);
            $ast = $this->astParser->parse($context);
            
            if ($ast !== null) {
                $result = $this->analysisEngine->analyze($context, $ast);
                $totalIssues += count($result->getIssues());
            }
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        $this->assertGreaterThan(0, $totalIssues, 'Should find issues across multiple files');
        $this->assertLessThan(5.0, $executionTime, 'Analysis should complete within reasonable time');
    }

    public function testAnalysisMemoryUsage(): void
    {
        $context = $this->getFixtureContext('sample_vulnerable.php');
        
        $initialMemory = memory_get_usage();
        
        $ast = $this->astParser->parse($context);
        $result = $this->analysisEngine->analyze($context, $ast);
        
        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;
        
        $this->assertNotNull($result);
        $this->assertLessThan(20 * 1024 * 1024, $memoryUsed, 'Memory usage should be reasonable');
    }

    public function testAnalysisWithCachingEnabled(): void
    {
        $this->analysisEngine->setCachingEnabled(true);
        
        $context = $this->getFixtureContext('sample_vulnerable.php');
        $ast = $this->astParser->parse($context);
        
        // First analysis
        $startTime1 = microtime(true);
        $result1 = $this->analysisEngine->analyze($context, $ast);
        $time1 = microtime(true) - $startTime1;
        
        // Second analysis (should use cache)
        $startTime2 = microtime(true);
        $result2 = $this->analysisEngine->analyze($context, $ast);
        $time2 = microtime(true) - $startTime2;
        
        $this->assertEquals(count($result1->getIssues()), count($result2->getIssues()));
        $this->assertLessThanOrEqual($time1, $time2, 'Second analysis should be faster or same due to caching');
    }

    public function testAnalysisStatistics(): void
    {
        $context = $this->getFixtureContext('sample_vulnerable.php');
        $ast = $this->astParser->parse($context);
        
        // Reset statistics
        $this->analysisEngine->resetStatistics();
        
        $result = $this->analysisEngine->analyze($context, $ast);
        $stats = $this->analysisEngine->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertEquals(1, $stats['files_analyzed']);
        $this->assertEquals(count($result->getIssues()), $stats['total_issues_found']);
        $this->assertGreaterThan(0, $stats['execution_time']);
    }

    public function testAnalyzeWithOnlySecurityAnalyzer(): void
    {
        // Create engine with only security analyzer
        $engine = new AnalysisEngine();
        $engine->addAnalyzer($this->securityAnalyzer);
        
        $context = $this->getFixtureContext('sample_vulnerable.php');
        $ast = $this->astParser->parse($context);
        
        $result = $engine->analyze($context, $ast);
        $issues = $result->getIssues();
        
        // All issues should be security-related
        foreach ($issues as $issue) {
            $this->assertEquals(Issue::CATEGORY_SECURITY, $issue->getCategory());
        }
    }

    public function testAnalyzeWithDisabledAnalyzers(): void
    {
        // Disable all analyzers
        $this->syntaxAnalyzer->setEnabled(false);
        $this->securityAnalyzer->setEnabled(false);
        
        $context = $this->getFixtureContext('sample_vulnerable.php');
        $ast = $this->astParser->parse($context);
        
        $result = $this->analysisEngine->analyze($context, $ast);
        $issues = $result->getIssues();
        
        $this->assertEmpty($issues, 'Disabled analyzers should not find issues');
    }

    public function testAnalyzeInvalidPhpFile(): void
    {
        $invalidCode = '<?php
        class InvalidClass {
            public function method( {
                // Missing closing parenthesis
            }
        ';
        
        $context = $this->createFileContext($invalidCode);
        $ast = $this->astParser->parse($context);
        
        // Parser should return null for invalid PHP
        $this->assertNull($ast);
        
        // Analysis engine should handle null AST gracefully
        $result = $this->analysisEngine->analyze($context, null);
        
        $this->assertNotNull($result);
        $this->assertEmpty($result->getIssues());
    }

    public function testAnalyzeEmptyFile(): void
    {
        $emptyCode = '<?php';
        
        $context = $this->createFileContext($emptyCode);
        $ast = $this->astParser->parse($context);
        
        $this->assertNotNull($ast);
        
        $result = $this->analysisEngine->analyze($context, $ast);
        
        $this->assertNotNull($result);
        $this->assertEmpty($result->getIssues());
    }

    public function testAnalyzeResultMetadata(): void
    {
        $context = $this->getFixtureContext('sample_vulnerable.php');
        $ast = $this->astParser->parse($context);
        
        $result = $this->analysisEngine->analyze($context, $ast);
        
        $this->assertNotNull($result);
        $this->assertInstanceOf(FileContext::class, $result->getFileContext());
        $this->assertEquals($context->getFilePath(), $result->getFileContext()->getFilePath());
        
        $metadata = $result->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('execution_time', $metadata);
        $this->assertArrayHasKey('analyzers_used', $metadata);
    }

    public function testAnalyzeIssueDetails(): void
    {
        $context = $this->getFixtureContext('sample_vulnerable.php');
        $ast = $this->astParser->parse($context);
        
        $result = $this->analysisEngine->analyze($context, $ast);
        $issues = $result->getIssues();
        
        $this->assertNotEmpty($issues);
        
        foreach ($issues as $issue) {
            // Verify issue structure
            $this->assertNotEmpty($issue->getId());
            $this->assertNotEmpty($issue->getTitle());
            $this->assertNotEmpty($issue->getDescription());
            $this->assertNotEmpty($issue->getRuleId());
            $this->assertNotEmpty($issue->getRuleName());
            $this->assertGreaterThan(0, $issue->getLine());
            $this->assertContains($issue->getSeverity(), Issue::getValidSeverities());
            $this->assertContains($issue->getCategory(), Issue::getValidCategories());
            $this->assertIsArray($issue->getTags());
            $this->assertIsArray($issue->getSuggestions());
            $this->assertIsArray($issue->getMetadata());
        }
    }

    public function testParallelProcessingMode(): void
    {
        $this->analysisEngine->setParallelProcessing(true);
        
        $context = $this->getFixtureContext('sample_vulnerable.php');
        $ast = $this->astParser->parse($context);
        
        $result = $this->analysisEngine->analyze($context, $ast);
        
        $this->assertNotNull($result);
        $this->assertNotEmpty($result->getIssues());
        
        // Parallel mode should produce same results as sequential
        $this->analysisEngine->setParallelProcessing(false);
        $sequentialResult = $this->analysisEngine->analyze($context, $ast);
        
        $this->assertEquals(
            count($result->getIssues()), 
            count($sequentialResult->getIssues()),
            'Parallel and sequential modes should find same number of issues'
        );
    }
}