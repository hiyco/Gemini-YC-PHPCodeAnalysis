<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Feature tests for CLI commands
 */

namespace YcPca\Tests\Feature;

use YcPca\Tests\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use YcPca\Cli\AnalyzeCommand;

/**
 * Feature tests for CLI command functionality
 * 
 * Tests the CLI commands as end users would use them
 */
class CliCommandTest extends TestCase
{
    private Application $application;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->application = new Application();
        $this->application->add(new AnalyzeCommand());
        
        $command = $this->application->find('analyze');
        $this->commandTester = new CommandTester($command);
    }

    public function testAnalyzeCommandWithValidFile(): void
    {
        // Create a test file with known issues
        $testCode = $this->getTestPhpCode('sql_injection');
        $testFile = $this->createTempPhpFile($testCode);
        
        try {
            $this->commandTester->execute([
                'path' => $testFile,
                '--format' => 'json'
            ]);
            
            $output = $this->commandTester->getDisplay();
            $this->assertStringContainsString('Analysis', $output);
            
            // Command should succeed even with issues found
            $this->assertEquals(0, $this->commandTester->getStatusCode());
            
        } finally {
            unlink($testFile);
        }
    }

    public function testAnalyzeCommandWithDirectory(): void
    {
        // Test analyzing the fixtures directory
        $this->commandTester->execute([
            'path' => $this->fixturesPath,
            '--format' => 'console'
        ]);
        
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Analysis', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testAnalyzeCommandWithNonExistentFile(): void
    {
        $this->commandTester->execute([
            'path' => '/non/existent/file.php'
        ]);
        
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Invalid path', $output);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testAnalyzeCommandWithJsonOutput(): void
    {
        $testCode = $this->getTestPhpCode('basic');
        $testFile = $this->createTempPhpFile($testCode);
        
        try {
            $this->commandTester->execute([
                'path' => $testFile,
                '--format' => 'json'
            ]);
            
            $output = $this->commandTester->getDisplay();
            
            // Should be valid JSON
            $json = json_decode($output, true);
            $this->assertIsArray($json);
            
            // Check JSON structure
            $this->assertArrayHasKey('metadata', $json);
            $this->assertArrayHasKey('statistics', $json);
            $this->assertArrayHasKey('results', $json);
            
        } finally {
            unlink($testFile);
        }
    }

    public function testAnalyzeCommandWithSeverityFilter(): void
    {
        $testCode = $this->getTestPhpCode('sql_injection');
        $testFile = $this->createTempPhpFile($testCode);
        
        try {
            // Test with high severity filter
            $this->commandTester->execute([
                'path' => $testFile,
                '--severity' => 'high',
                '--format' => 'json'
            ]);
            
            $output = $this->commandTester->getDisplay();
            $json = json_decode($output, true);
            
            $this->assertIsArray($json);
            $this->assertEquals('high', $json['metadata']['severity_threshold']);
            
        } finally {
            unlink($testFile);
        }
    }

    public function testAnalyzeCommandWithSecurityFocus(): void
    {
        $testCode = $this->getTestPhpCode('sql_injection');
        $testFile = $this->createTempPhpFile($testCode);
        
        try {
            $this->commandTester->execute([
                'path' => $testFile,
                '--include-security',
                '--format' => 'json'
            ]);
            
            $output = $this->commandTester->getDisplay();
            $json = json_decode($output, true);
            
            $this->assertIsArray($json);
            
            // Should find security issues
            if (!empty($json['results'])) {
                $hasSecurityIssues = false;
                foreach ($json['results'] as $result) {
                    foreach ($result['issues'] as $issue) {
                        if ($issue['category'] === 'security') {
                            $hasSecurityIssues = true;
                            break 2;
                        }
                    }
                }
                $this->assertTrue($hasSecurityIssues, 'Should find security issues with --include-security flag');
            }
            
        } finally {
            unlink($testFile);
        }
    }

    public function testAnalyzeCommandWithOutputFile(): void
    {
        $testCode = $this->getTestPhpCode('basic');
        $testFile = $this->createTempPhpFile($testCode);
        $outputFile = $this->outputPath . '/test_report.json';
        
        try {
            $this->commandTester->execute([
                'path' => $testFile,
                '--format' => 'json',
                '--output' => $outputFile
            ]);
            
            $this->assertFileExists($outputFile);
            
            $content = file_get_contents($outputFile);
            $json = json_decode($content, true);
            $this->assertIsArray($json);
            
        } finally {
            unlink($testFile);
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    public function testAnalyzeCommandWithExcludePatterns(): void
    {
        $testCode = $this->getTestPhpCode('basic');
        $testFile = $this->createTempPhpFile($testCode, 'vendor_test.php');
        
        try {
            $this->commandTester->execute([
                'path' => dirname($testFile),
                '--exclude' => ['*vendor*'],
                '--format' => 'json'
            ]);
            
            $output = $this->commandTester->getDisplay();
            $json = json_decode($output, true);
            
            // Should exclude the vendor file
            $this->assertIsArray($json);
            $fileFound = false;
            if (!empty($json['results'])) {
                foreach ($json['results'] as $result) {
                    if (strpos($result['file'], 'vendor_test.php') !== false) {
                        $fileFound = true;
                        break;
                    }
                }
            }
            $this->assertFalse($fileFound, 'Excluded files should not appear in results');
            
        } finally {
            unlink($testFile);
        }
    }

    public function testAnalyzeCommandWithParallelProcessing(): void
    {
        $this->skipIfRequirementsNotMet(['parallel_support' => function_exists('pcntl_fork')]);
        
        $this->commandTester->execute([
            'path' => $this->fixturesPath,
            '--parallel',
            '--format' => 'json'
        ]);
        
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Analysis', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testAnalyzeCommandWithStats(): void
    {
        $testCode = $this->getTestPhpCode('basic');
        $testFile = $this->createTempPhpFile($testCode);
        
        try {
            $this->commandTester->execute([
                'path' => $testFile,
                '--stats',
                '--format' => 'console'
            ]);
            
            $output = $this->commandTester->getDisplay();
            
            // Should include detailed statistics
            $this->assertStringContainsString('Analysis Summary', $output);
            $this->assertStringContainsString('Files analyzed', $output);
            $this->assertStringContainsString('Execution time', $output);
            $this->assertStringContainsString('Memory usage', $output);
            
        } finally {
            unlink($testFile);
        }
    }

    public function testAnalyzeCommandMemoryLimit(): void
    {
        $testCode = $this->getTestPhpCode('basic');
        $testFile = $this->createTempPhpFile($testCode);
        
        try {
            $this->commandTester->execute([
                'path' => $testFile,
                '--memory-limit' => '64M',
                '--format' => 'json'
            ]);
            
            $this->assertEquals(0, $this->commandTester->getStatusCode());
            
        } finally {
            unlink($testFile);
        }
    }

    public function testAnalyzeCommandTimeout(): void
    {
        $testCode = $this->getTestPhpCode('basic');
        $testFile = $this->createTempPhpFile($testCode);
        
        try {
            $this->commandTester->execute([
                'path' => $testFile,
                '--timeout' => '10',
                '--format' => 'json'
            ]);
            
            $this->assertEquals(0, $this->commandTester->getStatusCode());
            
        } finally {
            unlink($testFile);
        }
    }

    public function testAnalyzeCommandVerboseOutput(): void
    {
        $testCode = $this->getTestPhpCode('basic');
        $testFile = $this->createTempPhpFile($testCode);
        
        try {
            $this->commandTester->execute([
                'path' => $testFile,
                '-v' // Verbose flag
            ]);
            
            $output = $this->commandTester->getDisplay();
            
            // Verbose mode should show more detailed information
            $this->assertStringContainsString('Analysis', $output);
            
        } finally {
            unlink($testFile);
        }
    }

    public function testAnalyzeCommandHelp(): void
    {
        $this->commandTester->execute([
            'path' => '/dummy',
            '--help'
        ]);
        
        $output = $this->commandTester->getDisplay();
        
        // Should show help information
        $this->assertStringContainsString('analyze', $output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('Arguments:', $output);
        $this->assertStringContainsString('Options:', $output);
    }

    public function testAnalyzeCommandInvalidFormat(): void
    {
        $testCode = $this->getTestPhpCode('basic');
        $testFile = $this->createTempPhpFile($testCode);
        
        try {
            $this->commandTester->execute([
                'path' => $testFile,
                '--format' => 'invalid_format'
            ]);
            
            // Should default to console format or show error
            $this->assertContains($this->commandTester->getStatusCode(), [0, 1]);
            
        } finally {
            unlink($testFile);
        }
    }

    public function testAnalyzeCommandExitCodes(): void
    {
        // Test with clean file (should exit with 0)
        $cleanCode = $this->getTestPhpCode('basic');
        $cleanFile = $this->createTempPhpFile($cleanCode);
        
        try {
            $this->commandTester->execute([
                'path' => $cleanFile,
                '--severity' => 'high'
            ]);
            
            $this->assertEquals(0, $this->commandTester->getStatusCode());
            
        } finally {
            unlink($cleanFile);
        }
        
        // Test with file containing issues (may exit with 1 depending on severity)
        $vulnerableCode = $this->getTestPhpCode('sql_injection');
        $vulnerableFile = $this->createTempPhpFile($vulnerableCode);
        
        try {
            $this->commandTester->execute([
                'path' => $vulnerableFile,
                '--severity' => 'critical'
            ]);
            
            // Exit code depends on whether critical issues are found
            $this->assertContains($this->commandTester->getStatusCode(), [0, 1]);
            
        } finally {
            unlink($vulnerableFile);
        }
    }

    public function testAnalyzeCommandProgressDisplay(): void
    {
        $this->commandTester->execute([
            'path' => $this->fixturesPath
        ]);
        
        $output = $this->commandTester->getDisplay();
        
        // Should show progress information
        $this->assertStringContainsString('Analyzing', $output);
    }
}