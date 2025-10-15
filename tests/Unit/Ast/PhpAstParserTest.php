<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Unit tests for PHP AST Parser
 */

namespace YcPca\Tests\Unit\Ast;

use PHPUnit\Framework\TestCase;
use YcPca\Ast\PhpAstParser;
use YcPca\Model\FileContext;

/**
 * Test PHP AST Parser functionality
 * 
 * @covers \YcPca\Ast\PhpAstParser
 */
class PhpAstParserTest extends TestCase
{
    private PhpAstParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PhpAstParser();
    }

    public function testParseValidPhpCode(): void
    {
        $code = '<?php
class TestClass {
    public function testMethod(): string {
        return "test";
    }
}';
        
        $tempFile = tempnam(sys_get_temp_dir(), 'pca_test_');
        file_put_contents($tempFile, $code);
        
        $context = new FileContext($tempFile);
        $ast = $this->parser->parse($context);
        
        $this->assertNotNull($ast);
        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
        
        unlink($tempFile);
    }

    public function testParseEmptyFile(): void
    {
        $code = '<?php';
        
        $tempFile = tempnam(sys_get_temp_dir(), 'pca_test_');
        file_put_contents($tempFile, $code);
        
        $context = new FileContext($tempFile);
        $ast = $this->parser->parse($context);
        
        $this->assertNotNull($ast);
        $this->assertIsArray($ast);
        
        unlink($tempFile);
    }

    public function testParseInvalidPhpCode(): void
    {
        $code = '<?php
class TestClass {
    public function testMethod( {
        // Missing closing parenthesis
        return "test";
    }
}';
        
        $tempFile = tempnam(sys_get_temp_dir(), 'pca_test_');
        file_put_contents($tempFile, $code);
        
        $context = new FileContext($tempFile);
        
        // Parser should handle syntax errors gracefully
        $ast = $this->parser->parse($context);
        $this->assertNull($ast);
        
        unlink($tempFile);
    }

    public function testParseNonExistentFile(): void
    {
        $context = new FileContext('/non/existent/file.php');
        $ast = $this->parser->parse($context);
        
        $this->assertNull($ast);
    }

    public function testCacheStatistics(): void
    {
        $code = '<?php echo "test";';
        
        $tempFile = tempnam(sys_get_temp_dir(), 'pca_test_');
        file_put_contents($tempFile, $code);
        
        $context = new FileContext($tempFile);
        
        // Parse the same file twice to test caching
        $ast1 = $this->parser->parse($context);
        $ast2 = $this->parser->parse($context);
        
        $stats = $this->parser->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('cache_hits', $stats);
        $this->assertArrayHasKey('cache_misses', $stats);
        $this->assertArrayHasKey('files_parsed', $stats);
        
        // Second parse should be a cache hit
        $this->assertGreaterThan(0, $stats['cache_hits']);
        
        unlink($tempFile);
    }

    public function testParsePerformance(): void
    {
        $code = '<?php
' . str_repeat('class TestClass' . rand() . ' { public function test() { return "test"; } }' . PHP_EOL, 100);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'pca_test_');
        file_put_contents($tempFile, $code);
        
        $context = new FileContext($tempFile);
        
        $startTime = microtime(true);
        $ast = $this->parser->parse($context);
        $endTime = microtime(true);
        
        $executionTime = $endTime - $startTime;
        
        $this->assertNotNull($ast);
        $this->assertLessThan(1.0, $executionTime, 'Parsing should complete within 1 second');
        
        unlink($tempFile);
    }

    public function testResetCache(): void
    {
        $code = '<?php echo "test";';
        
        $tempFile = tempnam(sys_get_temp_dir(), 'pca_test_');
        file_put_contents($tempFile, $code);
        
        $context = new FileContext($tempFile);
        
        // Parse file to populate cache
        $this->parser->parse($context);
        
        $statsBefore = $this->parser->getStatistics();
        $this->assertGreaterThan(0, $statsBefore['files_parsed']);
        
        // Reset cache
        $this->parser->resetCache();
        
        $statsAfter = $this->parser->getStatistics();
        $this->assertEquals(0, $statsAfter['files_parsed']);
        $this->assertEquals(0, $statsAfter['cache_hits']);
        $this->assertEquals(0, $statsAfter['cache_misses']);
        
        unlink($tempFile);
    }

    public function testWithVisitors(): void
    {
        $code = '<?php
class TestClass {
    public function testMethod(): void {
        echo "Hello World";
    }
}';
        
        $tempFile = tempnam(sys_get_temp_dir(), 'pca_test_');
        file_put_contents($tempFile, $code);
        
        $context = new FileContext($tempFile);
        
        // Create a simple visitor that counts nodes
        $visitor = new class {
            public int $nodeCount = 0;
            
            public function enterNode($node): void {
                $this->nodeCount++;
            }
        };
        
        $ast = $this->parser->parseWithVisitors($context, [$visitor]);
        
        $this->assertNotNull($ast);
        $this->assertGreaterThan(0, $visitor->nodeCount);
        
        unlink($tempFile);
    }

    public function testGetSupportedExtensions(): void
    {
        $extensions = $this->parser->getSupportedExtensions();
        
        $this->assertIsArray($extensions);
        $this->assertContains('php', $extensions);
    }

    public function testIsPhpFile(): void
    {
        $this->assertTrue($this->parser->isPhpFile('test.php'));
        $this->assertTrue($this->parser->isPhpFile('test.phtml'));
        $this->assertFalse($this->parser->isPhpFile('test.txt'));
        $this->assertFalse($this->parser->isPhpFile('test.js'));
    }

    public function testLargeFileHandling(): void
    {
        // Generate a large PHP file
        $code = '<?php' . PHP_EOL;
        for ($i = 0; $i < 10000; $i++) {
            $code .= "// Line {$i}" . PHP_EOL;
            $code .= "\$var{$i} = 'value{$i}';" . PHP_EOL;
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'pca_test_large_');
        file_put_contents($tempFile, $code);
        
        $context = new FileContext($tempFile);
        
        $startMemory = memory_get_usage();
        $ast = $this->parser->parse($context);
        $endMemory = memory_get_usage();
        
        $memoryUsed = $endMemory - $startMemory;
        
        $this->assertNotNull($ast);
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage should be reasonable for large files');
        
        unlink($tempFile);
    }
}