<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: AST parsing performance benchmark
 */

namespace YcPca\Benchmark\Benchmarks;

use YcPca\Benchmark\AbstractBenchmark;
use YcPca\Ast\PhpAstParser;
use YcPca\Analysis\AnalysisEngine;
use YcPca\Model\FileContext;

/**
 * Benchmark for measuring AST parsing performance
 */
class ParsingBenchmark extends AbstractBenchmark
{
    private array $testFiles = [];

    public function __construct(array $testFiles = [])
    {
        parent::__construct(
            'ast_parsing_performance',
            'Measures AST parsing performance across different file sizes',
            'parsing'
        );

        $this->testFiles = $testFiles;
        $this->expectedExecutionTime = 0.5; // 500ms
        $this->expectedMemoryUsage = 20 * 1024 * 1024; // 20MB
    }

    public function setUp(): void
    {
        // Generate test files if none provided
        if (empty($this->testFiles)) {
            $this->testFiles = $this->generateTestFiles();
        }
    }

    public function tearDown(): void
    {
        // Clean up generated test files if needed
        foreach ($this->testFiles as $file) {
            if (isset($file['is_temporary']) && $file['is_temporary'] && file_exists($file['path'])) {
                unlink($file['path']);
            }
        }
    }

    public function execute(PhpAstParser $astParser, AnalysisEngine $analysisEngine): mixed
    {
        $results = [];
        
        foreach ($this->testFiles as $testFile) {
            $context = new FileContext($testFile['path']);
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage();
            
            $ast = $astParser->parse($context);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage();
            
            $results[] = [
                'file' => $testFile['path'],
                'file_size' => $testFile['size'],
                'complexity' => $testFile['complexity'],
                'parse_time' => $endTime - $startTime,
                'memory_used' => $endMemory - $startMemory,
                'ast_nodes' => $ast ? $this->countAstNodes($ast) : 0,
                'success' => $ast !== null
            ];
        }
        
        return [
            'individual_results' => $results,
            'summary' => $this->calculateSummary($results)
        ];
    }

    /**
     * Generate test files with different sizes and complexity
     */
    private function generateTestFiles(): array
    {
        $files = [];
        $tempDir = sys_get_temp_dir() . '/pca_benchmark_' . uniqid();
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Small file (~1KB)
        $smallFile = $tempDir . '/small.php';
        file_put_contents($smallFile, $this->generatePhpCode('small'));
        $files[] = [
            'path' => $smallFile,
            'size' => filesize($smallFile),
            'complexity' => 'small',
            'is_temporary' => true
        ];

        // Medium file (~10KB)
        $mediumFile = $tempDir . '/medium.php';
        file_put_contents($mediumFile, $this->generatePhpCode('medium'));
        $files[] = [
            'path' => $mediumFile,
            'size' => filesize($mediumFile),
            'complexity' => 'medium',
            'is_temporary' => true
        ];

        // Large file (~50KB)
        $largeFile = $tempDir . '/large.php';
        file_put_contents($largeFile, $this->generatePhpCode('large'));
        $files[] = [
            'path' => $largeFile,
            'size' => filesize($largeFile),
            'complexity' => 'large',
            'is_temporary' => true
        ];

        return $files;
    }

    /**
     * Generate PHP code of different complexities
     */
    private function generatePhpCode(string $complexity): string
    {
        $code = "<?php\ndeclare(strict_types=1);\n\n";

        switch ($complexity) {
            case 'small':
                $code .= $this->generateSimpleClass('SmallClass', 5);
                break;

            case 'medium':
                for ($i = 0; $i < 3; $i++) {
                    $code .= $this->generateComplexClass("MediumClass{$i}", 20, true);
                    $code .= "\n";
                }
                break;

            case 'large':
                for ($i = 0; $i < 10; $i++) {
                    $code .= $this->generateComplexClass("LargeClass{$i}", 30, true);
                    $code .= "\n";
                }
                $code .= $this->generateInterface('LargeInterface', 15);
                $code .= $this->generateTrait('LargeTrait', 10);
                break;
        }

        return $code;
    }

    /**
     * Generate a simple PHP class
     */
    private function generateSimpleClass(string $className, int $methodCount): string
    {
        $code = "class {$className} {\n";
        
        for ($i = 0; $i < $methodCount; $i++) {
            $code .= "    public function method{$i}(\$param{$i}) {\n";
            $code .= "        return \$param{$i} * 2;\n";
            $code .= "    }\n\n";
        }
        
        $code .= "}\n\n";
        return $code;
    }

    /**
     * Generate a complex PHP class with various constructs
     */
    private function generateComplexClass(string $className, int $methodCount, bool $withTraits = false): string
    {
        $code = "class {$className}";
        
        if ($withTraits) {
            $code .= " implements ArrayAccess";
        }
        
        $code .= " {\n";
        $code .= "    private array \$data = [];\n";
        $code .= "    protected static int \$instanceCount = 0;\n\n";

        $code .= "    public function __construct(array \$data = []) {\n";
        $code .= "        \$this->data = \$data;\n";
        $code .= "        self::\$instanceCount++;\n";
        $code .= "    }\n\n";

        for ($i = 0; $i < $methodCount; $i++) {
            $methodType = $i % 3;
            
            switch ($methodType) {
                case 0: // Simple method
                    $code .= "    public function simpleMethod{$i}(\$param): mixed {\n";
                    $code .= "        return \$param ? \$this->data[\$param] ?? null : \$this->data;\n";
                    $code .= "    }\n\n";
                    break;

                case 1: // Complex method with control structures
                    $code .= "    protected function complexMethod{$i}(array \$items): array {\n";
                    $code .= "        \$result = [];\n";
                    $code .= "        foreach (\$items as \$key => \$item) {\n";
                    $code .= "            if (is_array(\$item)) {\n";
                    $code .= "                \$result[\$key] = array_map(fn(\$x) => \$x * 2, \$item);\n";
                    $code .= "            } else {\n";
                    $code .= "                \$result[\$key] = \$item;\n";
                    $code .= "            }\n";
                    $code .= "        }\n";
                    $code .= "        return \$result;\n";
                    $code .= "    }\n\n";
                    break;

                case 2: // Method with try-catch
                    $code .= "    public function methodWithException{$i}(\$value): ?string {\n";
                    $code .= "        try {\n";
                    $code .= "            if (!\$value) {\n";
                    $code .= "                throw new InvalidArgumentException('Invalid value');\n";
                    $code .= "            }\n";
                    $code .= "            return json_encode(\$value);\n";
                    $code .= "        } catch (Exception \$e) {\n";
                    $code .= "            error_log(\$e->getMessage());\n";
                    $code .= "            return null;\n";
                    $code .= "        }\n";
                    $code .= "    }\n\n";
                    break;
            }
        }

        if ($withTraits) {
            $code .= "    public function offsetExists(mixed \$offset): bool {\n";
            $code .= "        return isset(\$this->data[\$offset]);\n";
            $code .= "    }\n\n";
            
            $code .= "    public function offsetGet(mixed \$offset): mixed {\n";
            $code .= "        return \$this->data[\$offset] ?? null;\n";
            $code .= "    }\n\n";
            
            $code .= "    public function offsetSet(mixed \$offset, mixed \$value): void {\n";
            $code .= "        \$this->data[\$offset] = \$value;\n";
            $code .= "    }\n\n";
            
            $code .= "    public function offsetUnset(mixed \$offset): void {\n";
            $code .= "        unset(\$this->data[\$offset]);\n";
            $code .= "    }\n\n";
        }

        $code .= "}\n";
        return $code;
    }

    /**
     * Generate an interface
     */
    private function generateInterface(string $interfaceName, int $methodCount): string
    {
        $code = "interface {$interfaceName} {\n";
        
        for ($i = 0; $i < $methodCount; $i++) {
            $code .= "    public function interfaceMethod{$i}(mixed \$param): mixed;\n";
        }
        
        $code .= "}\n\n";
        return $code;
    }

    /**
     * Generate a trait
     */
    private function generateTrait(string $traitName, int $methodCount): string
    {
        $code = "trait {$traitName} {\n";
        
        for ($i = 0; $i < $methodCount; $i++) {
            $code .= "    public function traitMethod{$i}(): string {\n";
            $code .= "        return 'trait method {$i}';\n";
            $code .= "    }\n\n";
        }
        
        $code .= "}\n\n";
        return $code;
    }

    /**
     * Count AST nodes recursively
     */
    private function countAstNodes($ast): int
    {
        if (!is_array($ast)) {
            return 0;
        }

        $count = count($ast);
        foreach ($ast as $node) {
            if (is_array($node)) {
                $count += $this->countAstNodes($node);
            } elseif (is_object($node)) {
                // For nikic/php-parser nodes, count sub-nodes
                if (method_exists($node, 'getSubNodeNames')) {
                    foreach ($node->getSubNodeNames() as $subNodeName) {
                        $subNode = $node->$subNodeName;
                        if (is_array($subNode)) {
                            $count += $this->countAstNodes($subNode);
                        } elseif ($subNode !== null) {
                            $count++;
                        }
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Calculate summary statistics from results
     */
    private function calculateSummary(array $results): array
    {
        if (empty($results)) {
            return [];
        }

        $totalTime = 0;
        $totalMemory = 0;
        $totalNodes = 0;
        $totalFileSize = 0;
        $successCount = 0;

        $times = [];
        $memories = [];

        foreach ($results as $result) {
            $totalTime += $result['parse_time'];
            $totalMemory += $result['memory_used'];
            $totalNodes += $result['ast_nodes'];
            $totalFileSize += $result['file_size'];
            
            if ($result['success']) {
                $successCount++;
            }

            $times[] = $result['parse_time'];
            $memories[] = $result['memory_used'];
        }

        $fileCount = count($results);

        return [
            'files_processed' => $fileCount,
            'success_rate' => $fileCount > 0 ? ($successCount / $fileCount * 100) : 0,
            'total_parse_time' => $totalTime,
            'average_parse_time' => $fileCount > 0 ? ($totalTime / $fileCount) : 0,
            'total_memory_used' => $totalMemory,
            'average_memory_used' => $fileCount > 0 ? ($totalMemory / $fileCount) : 0,
            'total_ast_nodes' => $totalNodes,
            'average_ast_nodes' => $fileCount > 0 ? ($totalNodes / $fileCount) : 0,
            'total_file_size' => $totalFileSize,
            'average_file_size' => $fileCount > 0 ? ($totalFileSize / $fileCount) : 0,
            'parse_speed_chars_per_second' => $totalTime > 0 ? ($totalFileSize / $totalTime) : 0,
            'fastest_parse_time' => !empty($times) ? min($times) : 0,
            'slowest_parse_time' => !empty($times) ? max($times) : 0,
            'lowest_memory_usage' => !empty($memories) ? min($memories) : 0,
            'highest_memory_usage' => !empty($memories) ? max($memories) : 0,
        ];
    }
}