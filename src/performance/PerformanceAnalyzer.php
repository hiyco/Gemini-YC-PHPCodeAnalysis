<?php

declare(strict_types=1);

namespace YcPca\Performance;

use YcPca\Ast\AstParser;
use YcPca\Performance\Analyzers\AlgorithmComplexityAnalyzer;
use YcPca\Performance\Analyzers\DatabasePerformanceAnalyzer;
use YcPca\Performance\Analyzers\MemoryAnalyzer;
use YcPca\Performance\Analyzers\IoPerformanceAnalyzer;
use YcPca\Performance\Analyzers\PhpFeatureAnalyzer;
use YcPca\Performance\Reports\PerformanceReport;
use PhpParser\Node;

/**
 * Advanced Performance Analyzer with AST-based analysis
 * Detects performance bottlenecks, complexity issues, and optimization opportunities
 */
class PerformanceAnalyzer
{
    private AstParser $parser;
    private array $analyzers = [];
    private array $issues = [];
    private array $metrics = [];
    private array $config;
    private float $startTime;

    public function __construct(AstParser $parser, array $config = [])
    {
        $this->parser = $parser;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->initializeAnalyzers();
    }

    /**
     * Initialize all performance analyzers
     */
    private function initializeAnalyzers(): void
    {
        $this->analyzers = [
            'complexity' => new AlgorithmComplexityAnalyzer($this->config),
            'database' => new DatabasePerformanceAnalyzer($this->config),
            'memory' => new MemoryAnalyzer($this->config),
            'io' => new IoPerformanceAnalyzer($this->config),
            'php_features' => new PhpFeatureAnalyzer($this->config),
        ];
    }

    /**
     * Analyze file performance
     */
    public function analyzeFile(string $filePath): PerformanceReport
    {
        $this->startTime = microtime(true);
        $this->issues = [];
        $this->metrics = [];
        
        try {
            $ast = $this->parser->parseFile($filePath);
            $context = $this->buildPerformanceContext($ast, $filePath);
            
            // Run all analyzers
            foreach ($this->analyzers as $name => $analyzer) {
                if ($this->isAnalyzerEnabled($name)) {
                    $analyzer->analyze($ast, $context, $this->issues, $this->metrics);
                }
            }
            
            // Calculate performance scores
            $this->calculatePerformanceScores();
            
            // Generate optimization suggestions
            $this->generateOptimizationSuggestions();
            
            return $this->generateReport($filePath);
            
        } catch (\Exception $e) {
            throw new PerformanceAnalysisException(
                "Failed to analyze file {$filePath}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Build performance context for analysis
     */
    private function buildPerformanceContext(array $ast, string $filePath): PerformanceContext
    {
        $context = new PerformanceContext($filePath);
        
        // Extract performance-relevant information
        $context->setLoops($this->extractLoops($ast));
        $context->setRecursiveFunctions($this->findRecursiveFunctions($ast));
        $context->setDatabaseOperations($this->extractDatabaseOperations($ast));
        $context->setFileOperations($this->extractFileOperations($ast));
        $context->setMemoryOperations($this->extractMemoryOperations($ast));
        $context->setReflectionUsage($this->findReflectionUsage($ast));
        $context->setSerializationPoints($this->findSerializationPoints($ast));
        $context->setFunctionCalls($this->analyzeFunctionCalls($ast));
        $context->setObjectCreations($this->countObjectCreations($ast));
        $context->setCacheUsage($this->analyzeCacheUsage($ast));
        
        return $context;
    }

    /**
     * Extract loops from AST for complexity analysis
     */
    private function extractLoops(array $ast): array
    {
        $loops = [];
        $visitor = new class extends \PhpParser\NodeVisitorAbstract {
            public array $loops = [];
            
            public function enterNode(Node $node) {
                if ($node instanceof Node\Stmt\For_ ||
                    $node instanceof Node\Stmt\Foreach_ ||
                    $node instanceof Node\Stmt\While_ ||
                    $node instanceof Node\Stmt\Do_) {
                    
                    $this->loops[] = [
                        'type' => $this->getLoopType($node),
                        'line' => $node->getLine(),
                        'nested_level' => $this->calculateNestingLevel($node),
                        'has_break' => $this->hasBreakStatement($node),
                        'estimated_iterations' => $this->estimateIterations($node),
                    ];
                }
            }
            
            private function getLoopType(Node $node): string
            {
                if ($node instanceof Node\Stmt\For_) return 'for';
                if ($node instanceof Node\Stmt\Foreach_) return 'foreach';
                if ($node instanceof Node\Stmt\While_) return 'while';
                if ($node instanceof Node\Stmt\Do_) return 'do-while';
                return 'unknown';
            }
            
            private function calculateNestingLevel(Node $node): int
            {
                $level = 0;
                $parent = $node->getAttribute('parent');
                while ($parent) {
                    if ($parent instanceof Node\Stmt\For_ ||
                        $parent instanceof Node\Stmt\Foreach_ ||
                        $parent instanceof Node\Stmt\While_ ||
                        $parent instanceof Node\Stmt\Do_) {
                        $level++;
                    }
                    $parent = $parent->getAttribute('parent');
                }
                return $level;
            }
            
            private function hasBreakStatement(Node $node): bool
            {
                $hasBreak = false;
                $breakVisitor = new class($hasBreak) extends \PhpParser\NodeVisitorAbstract {
                    private bool &$hasBreak;
                    
                    public function __construct(bool &$hasBreak) {
                        $this->hasBreak = &$hasBreak;
                    }
                    
                    public function enterNode(Node $node) {
                        if ($node instanceof Node\Stmt\Break_) {
                            $this->hasBreak = true;
                            return \PhpParser\NodeTraverser::STOP_TRAVERSAL;
                        }
                    }
                };
                
                $traverser = new \PhpParser\NodeTraverser();
                $traverser->addVisitor($breakVisitor);
                $traverser->traverse([$node]);
                
                return $hasBreak;
            }
            
            private function estimateIterations(Node $node): ?int
            {
                // Simple heuristic-based estimation
                if ($node instanceof Node\Stmt\For_) {
                    // Try to analyze loop bounds
                    if (!empty($node->init) && !empty($node->cond) && !empty($node->loop)) {
                        // Simplified: check for common patterns like i=0; i<N; i++
                        return null; // Complex analysis needed
                    }
                }
                return null;
            }
        };
        
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
        
        return $visitor->loops;
    }

    /**
     * Calculate performance scores for detected issues
     */
    private function calculatePerformanceScores(): void
    {
        foreach ($this->issues as &$issue) {
            $impact = $this->calculatePerformanceImpact($issue);
            $issue['impact_score'] = $impact;
            $issue['severity'] = $this->getPerformanceSeverity($impact);
            $issue['estimated_speedup'] = $this->estimateSpeedup($issue);
        }
        
        // Sort by impact
        usort($this->issues, function ($a, $b) {
            return $b['impact_score'] <=> $a['impact_score'];
        });
    }

    /**
     * Calculate performance impact score
     */
    private function calculatePerformanceImpact(array $issue): float
    {
        $baseScore = 0.0;
        
        // Complexity impact
        if (isset($issue['complexity'])) {
            $complexity = $issue['complexity'];
            if ($complexity === 'O(n^3)' || $complexity === 'O(2^n)') {
                $baseScore += 9.0;
            } elseif ($complexity === 'O(n^2)') {
                $baseScore += 7.0;
            } elseif ($complexity === 'O(n*log(n))') {
                $baseScore += 4.0;
            } elseif ($complexity === 'O(n)') {
                $baseScore += 2.0;
            }
        }
        
        // Frequency impact
        if (isset($issue['frequency'])) {
            $frequencyMultiplier = [
                'always' => 2.0,
                'often' => 1.5,
                'sometimes' => 1.0,
                'rarely' => 0.5,
            ];
            $baseScore *= $frequencyMultiplier[$issue['frequency']] ?? 1.0;
        }
        
        // Resource impact
        if (isset($issue['resource_type'])) {
            $resourceImpact = [
                'cpu' => 1.5,
                'memory' => 1.3,
                'io' => 1.4,
                'network' => 1.6,
            ];
            $baseScore *= $resourceImpact[$issue['resource_type']] ?? 1.0;
        }
        
        return min(10.0, $baseScore);
    }

    /**
     * Get performance severity level
     */
    private function getPerformanceSeverity(float $impact): string
    {
        if ($impact >= 8.0) return 'CRITICAL';
        if ($impact >= 6.0) return 'HIGH';
        if ($impact >= 4.0) return 'MEDIUM';
        if ($impact >= 2.0) return 'LOW';
        return 'INFO';
    }

    /**
     * Estimate potential speedup from fixing issue
     */
    private function estimateSpeedup(array $issue): string
    {
        $impact = $issue['impact_score'];
        
        if ($impact >= 8.0) {
            return '10x-100x potential speedup';
        } elseif ($impact >= 6.0) {
            return '5x-10x potential speedup';
        } elseif ($impact >= 4.0) {
            return '2x-5x potential speedup';
        } elseif ($impact >= 2.0) {
            return '20%-100% potential speedup';
        }
        return 'Minor optimization';
    }

    /**
     * Generate optimization suggestions
     */
    private function generateOptimizationSuggestions(): void
    {
        foreach ($this->issues as &$issue) {
            $issue['suggestions'] = $this->getSuggestionsForIssue($issue);
            $issue['code_example'] = $this->generateOptimizedCode($issue);
        }
    }

    /**
     * Get optimization suggestions for specific issue
     */
    private function getSuggestionsForIssue(array $issue): array
    {
        $suggestions = [];
        
        switch ($issue['type']) {
            case 'nested_loops':
                $suggestions[] = 'Consider using hash maps or indexed lookups to reduce complexity';
                $suggestions[] = 'Evaluate if data can be pre-processed or cached';
                $suggestions[] = 'Consider using database joins instead of nested queries';
                break;
                
            case 'n_plus_one_query':
                $suggestions[] = 'Use eager loading with JOIN or WITH clause';
                $suggestions[] = 'Implement query result caching';
                $suggestions[] = 'Consider using a DataLoader pattern';
                break;
                
            case 'memory_leak':
                $suggestions[] = 'Ensure proper cleanup of resources and references';
                $suggestions[] = 'Use weak references where appropriate';
                $suggestions[] = 'Implement object pooling for frequently created objects';
                break;
                
            case 'inefficient_file_io':
                $suggestions[] = 'Use buffered I/O operations';
                $suggestions[] = 'Consider async I/O for non-blocking operations';
                $suggestions[] = 'Implement file caching where appropriate';
                break;
                
            case 'reflection_overhead':
                $suggestions[] = 'Cache reflection results for reuse';
                $suggestions[] = 'Consider code generation instead of runtime reflection';
                $suggestions[] = 'Use property access instead of reflection where possible';
                break;
                
            default:
                $suggestions[] = 'Review algorithm efficiency';
                $suggestions[] = 'Consider caching computed results';
                $suggestions[] = 'Profile code to identify actual bottlenecks';
        }
        
        return $suggestions;
    }

    /**
     * Generate optimized code example
     */
    private function generateOptimizedCode(array $issue): ?string
    {
        // Generate specific optimized code based on issue type
        switch ($issue['type']) {
            case 'nested_loops':
                return $this->generateOptimizedLoopCode($issue);
            case 'n_plus_one_query':
                return $this->generateOptimizedQueryCode($issue);
            case 'memory_leak':
                return $this->generateMemoryEfficientCode($issue);
            default:
                return null;
        }
    }

    /**
     * Generate optimized loop code
     */
    private function generateOptimizedLoopCode(array $issue): string
    {
        return <<<'PHP'
// Before: O(n²) complexity
foreach ($items as $item) {
    foreach ($otherItems as $other) {
        if ($item['id'] === $other['item_id']) {
            // Process...
        }
    }
}

// After: O(n) complexity using hash map
$itemMap = array_column($items, null, 'id');
foreach ($otherItems as $other) {
    if (isset($itemMap[$other['item_id']])) {
        $item = $itemMap[$other['item_id']];
        // Process...
    }
}
PHP;
    }

    /**
     * Generate optimized query code
     */
    private function generateOptimizedQueryCode(array $issue): string
    {
        return <<<'PHP'
// Before: N+1 queries
foreach ($users as $user) {
    $posts = $db->query("SELECT * FROM posts WHERE user_id = ?", [$user->id]);
    // Process...
}

// After: Single query with JOIN
$userPosts = $db->query("
    SELECT u.*, p.*
    FROM users u
    LEFT JOIN posts p ON p.user_id = u.id
    WHERE u.id IN (?)
", $userIds);

// Or with eager loading (ORM)
$users = User::with('posts')->whereIn('id', $userIds)->get();
PHP;
    }

    /**
     * Generate memory efficient code
     */
    private function generateMemoryEfficientCode(array $issue): string
    {
        return <<<'PHP'
// Before: Memory intensive
$data = file_get_contents('large_file.csv');
$lines = explode("\n", $data);
foreach ($lines as $line) {
    // Process...
}

// After: Memory efficient streaming
$handle = fopen('large_file.csv', 'r');
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // Process line...
        // Free memory after processing
        unset($processedData);
    }
    fclose($handle);
}

// Or using generators
function readLargeFile($file) {
    $handle = fopen($file, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            yield $line;
        }
        fclose($handle);
    }
}

foreach (readLargeFile('large_file.csv') as $line) {
    // Process line...
}
PHP;
    }

    /**
     * Generate comprehensive performance report
     */
    private function generateReport(string $filePath): PerformanceReport
    {
        $executionTime = microtime(true) - $this->startTime;
        
        $reportMetrics = array_merge($this->metrics, [
            'total_issues' => count($this->issues),
            'critical' => $this->countBySeverity('CRITICAL'),
            'high' => $this->countBySeverity('HIGH'),
            'medium' => $this->countBySeverity('MEDIUM'),
            'low' => $this->countBySeverity('LOW'),
            'info' => $this->countBySeverity('INFO'),
            'execution_time' => $executionTime,
            'file_path' => $filePath,
            'timestamp' => time(),
        ]);
        
        return new PerformanceReport($this->issues, $reportMetrics);
    }

    /**
     * Count issues by severity
     */
    private function countBySeverity(string $severity): int
    {
        return count(array_filter($this->issues, function ($issue) use ($severity) {
            return $issue['severity'] === $severity;
        }));
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'enabled_analyzers' => [
                'complexity' => true,
                'database' => true,
                'memory' => true,
                'io' => true,
                'php_features' => true,
            ],
            'complexity_threshold' => 10,
            'max_nesting_level' => 3,
            'memory_limit' => 128 * 1024 * 1024, // 128MB
            'slow_query_threshold' => 1.0, // seconds
            'cache_analysis' => true,
            'profile_mode' => false,
        ];
    }

    /**
     * Check if analyzer is enabled
     */
    private function isAnalyzerEnabled(string $name): bool
    {
        return $this->config['enabled_analyzers'][$name] ?? false;
    }
}

class PerformanceAnalysisException extends \Exception {}