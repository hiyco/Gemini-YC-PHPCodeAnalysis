<?php

declare(strict_types=1);

namespace YcPca\Performance\Analyzers;

use PhpParser\Node;
use YcPca\Performance\PerformanceContext;

/**
 * Analyzes algorithm complexity including time and space complexity
 */
class AlgorithmComplexityAnalyzer
{
    private array $config;
    private array $complexityPatterns = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializePatterns();
    }

    /**
     * Analyze algorithm complexity in AST
     */
    public function analyze(array $ast, PerformanceContext $context, array &$issues, array &$metrics): void
    {
        // Analyze loop complexity
        $this->analyzeLoopComplexity($context, $issues);
        
        // Analyze recursive functions
        $this->analyzeRecursion($context, $issues);
        
        // Analyze data structure operations
        $this->analyzeDataStructureOperations($ast, $context, $issues);
        
        // Calculate cyclomatic complexity
        $cyclomaticComplexity = $this->calculateCyclomaticComplexity($ast);
        $metrics['cyclomatic_complexity'] = $cyclomaticComplexity;
        
        // Calculate cognitive complexity
        $cognitiveComplexity = $this->calculateCognitiveComplexity($ast);
        $metrics['cognitive_complexity'] = $cognitiveComplexity;
        
        // Check complexity thresholds
        $this->checkComplexityThresholds($cyclomaticComplexity, $cognitiveComplexity, $issues);
    }

    /**
     * Analyze loop complexity patterns
     */
    private function analyzeLoopComplexity(PerformanceContext $context, array &$issues): void
    {
        $loops = $context->getLoops();
        $nestedLoops = [];
        
        foreach ($loops as $loop) {
            $nestingLevel = $loop['nested_level'] ?? 0;
            
            if ($nestingLevel >= 2) {
                // Triple or more nested loops
                $issues[] = [
                    'type' => 'nested_loops',
                    'complexity' => $this->estimateLoopComplexity($nestingLevel),
                    'severity' => $nestingLevel >= 3 ? 'CRITICAL' : 'HIGH',
                    'message' => sprintf(
                        '%d-level nested loop detected. Complexity: %s',
                        $nestingLevel + 1,
                        $this->estimateLoopComplexity($nestingLevel)
                    ),
                    'line' => $loop['line'],
                    'impact_score' => $this->calculateLoopImpact($nestingLevel),
                    'frequency' => 'always',
                    'resource_type' => 'cpu',
                    'suggestions' => [
                        'Consider using hash maps or indexed data structures',
                        'Pre-process data to avoid nested iterations',
                        'Use database queries with proper joins',
                        'Implement caching for repeated computations',
                    ],
                ];
                
                $nestedLoops[] = $loop;
            } elseif ($nestingLevel === 1) {
                // Check for common inefficient patterns
                $this->checkInefficienticientPatterns($loop, $issues);
            }
        }
        
        // Check for multiple nested loop groups
        if (count($nestedLoops) > 2) {
            $issues[] = [
                'type' => 'excessive_nesting',
                'severity' => 'HIGH',
                'message' => sprintf(
                    'Multiple nested loop groups detected (%d groups)',
                    count($nestedLoops)
                ),
                'impact_score' => 7.0,
                'suggestions' => [
                    'Refactor code to reduce overall complexity',
                    'Consider breaking into smaller functions',
                    'Use more efficient algorithms or data structures',
                ],
            ];
        }
    }

    /**
     * Analyze recursive functions
     */
    private function analyzeRecursion(PerformanceContext $context, array &$issues): void
    {
        $recursiveFunctions = $context->getRecursiveFunctions();
        
        foreach ($recursiveFunctions as $function) {
            $hasTailCall = $function['tail_recursive'] ?? false;
            $hasBaseCase = $function['has_base_case'] ?? true;
            $estimatedDepth = $function['estimated_depth'] ?? null;
            
            $severity = 'MEDIUM';
            $suggestions = [];
            
            if (!$hasBaseCase) {
                $severity = 'CRITICAL';
                $suggestions[] = 'Missing base case - infinite recursion risk!';
            }
            
            if (!$hasTailCall) {
                $suggestions[] = 'Consider converting to tail recursion for optimization';
                $suggestions[] = 'Consider iterative approach instead of recursion';
            }
            
            if ($estimatedDepth && $estimatedDepth > 100) {
                $severity = 'HIGH';
                $suggestions[] = 'Deep recursion may cause stack overflow';
                $suggestions[] = 'Implement iterative solution or use trampolining';
            }
            
            $issues[] = [
                'type' => 'recursion',
                'subtype' => $hasTailCall ? 'tail_recursion' : 'standard_recursion',
                'severity' => $severity,
                'message' => sprintf(
                    'Recursive function "%s" detected',
                    $function['name'] ?? 'anonymous'
                ),
                'line' => $function['line'] ?? 0,
                'complexity' => 'O(n) space for call stack',
                'impact_score' => $hasTailCall ? 4.0 : 6.0,
                'suggestions' => $suggestions,
                'frequency' => 'always',
                'resource_type' => 'memory',
            ];
        }
    }

    /**
     * Analyze data structure operations
     */
    private function analyzeDataStructureOperations(array $ast, PerformanceContext $context, array &$issues): void
    {
        $visitor = new class($issues) extends \PhpParser\NodeVisitorAbstract {
            private array &$issues;
            
            public function __construct(array &$issues)
            {
                $this->issues = &$issues;
            }
            
            public function enterNode(Node $node)
            {
                // Check for array operations in loops
                if ($node instanceof Node\Expr\FuncCall) {
                    $this->checkArrayOperations($node);
                }
                
                // Check for inefficient string concatenation
                if ($node instanceof Node\Expr\AssignOp\Concat) {
                    $this->checkStringConcatenation($node);
                }
            }
            
            private function checkArrayOperations(Node\Expr\FuncCall $node): void
            {
                if ($node->name instanceof Node\Name) {
                    $funcName = $node->name->toString();
                    
                    // Check for inefficient array operations
                    if (in_array($funcName, ['in_array', 'array_search'])) {
                        // Check if this is inside a loop
                        $parent = $node->getAttribute('parent');
                        if ($this->isInsideLoop($parent)) {
                            $this->issues[] = [
                                'type' => 'inefficient_array_operation',
                                'severity' => 'MEDIUM',
                                'message' => "Using $funcName inside loop - O(n) operation",
                                'line' => $node->getLine(),
                                'complexity' => 'O(n²) when in loop',
                                'impact_score' => 5.0,
                                'suggestions' => [
                                    'Use array_flip() and isset() for O(1) lookups',
                                    'Consider using a hash map (associative array)',
                                    'Pre-process the array outside the loop',
                                ],
                                'frequency' => 'always',
                                'resource_type' => 'cpu',
                            ];
                        }
                    }
                    
                    // Check for array_merge in loops
                    if ($funcName === 'array_merge') {
                        $parent = $node->getAttribute('parent');
                        if ($this->isInsideLoop($parent)) {
                            $this->issues[] = [
                                'type' => 'array_merge_in_loop',
                                'severity' => 'HIGH',
                                'message' => 'array_merge in loop creates new arrays repeatedly',
                                'line' => $node->getLine(),
                                'complexity' => 'O(n²) memory and time',
                                'impact_score' => 7.0,
                                'suggestions' => [
                                    'Collect items and merge once after loop',
                                    'Use array_push() or [] operator instead',
                                    'Consider using SplFixedArray for better performance',
                                ],
                                'frequency' => 'always',
                                'resource_type' => 'memory',
                            ];
                        }
                    }
                }
            }
            
            private function checkStringConcatenation(Node $node): void
            {
                $parent = $node->getAttribute('parent');
                if ($this->isInsideLoop($parent)) {
                    $this->issues[] = [
                        'type' => 'string_concatenation_in_loop',
                        'severity' => 'MEDIUM',
                        'message' => 'String concatenation in loop is inefficient',
                        'line' => $node->getLine(),
                        'complexity' => 'O(n²) due to string immutability',
                        'impact_score' => 5.0,
                        'suggestions' => [
                            'Use array to collect strings, then implode()',
                            'Use sprintf() for formatted strings',
                            'Consider using output buffering for large content',
                        ],
                        'frequency' => 'always',
                        'resource_type' => 'memory',
                    ];
                }
            }
            
            private function isInsideLoop(?Node $node): bool
            {
                while ($node) {
                    if ($node instanceof Node\Stmt\For_ ||
                        $node instanceof Node\Stmt\Foreach_ ||
                        $node instanceof Node\Stmt\While_ ||
                        $node instanceof Node\Stmt\Do_) {
                        return true;
                    }
                    $node = $node->getAttribute('parent');
                }
                return false;
            }
        };
        
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
    }

    /**
     * Calculate cyclomatic complexity
     */
    private function calculateCyclomaticComplexity(array $ast): int
    {
        $complexity = 1; // Base complexity
        
        $visitor = new class($complexity) extends \PhpParser\NodeVisitorAbstract {
            private int &$complexity;
            
            public function __construct(int &$complexity)
            {
                $this->complexity = &$complexity;
            }
            
            public function enterNode(Node $node)
            {
                // Decision points increase complexity
                if ($node instanceof Node\Stmt\If_ ||
                    $node instanceof Node\Stmt\ElseIf_ ||
                    $node instanceof Node\Stmt\Case_ ||
                    $node instanceof Node\Stmt\For_ ||
                    $node instanceof Node\Stmt\Foreach_ ||
                    $node instanceof Node\Stmt\While_ ||
                    $node instanceof Node\Stmt\Do_ ||
                    $node instanceof Node\Expr\Ternary ||
                    $node instanceof Node\Expr\BinaryOp\LogicalAnd ||
                    $node instanceof Node\Expr\BinaryOp\LogicalOr ||
                    $node instanceof Node\Expr\BinaryOp\BooleanAnd ||
                    $node instanceof Node\Expr\BinaryOp\BooleanOr ||
                    $node instanceof Node\Expr\BinaryOp\Coalesce) {
                    $this->complexity++;
                }
                
                // Catch blocks
                if ($node instanceof Node\Stmt\Catch_) {
                    $this->complexity++;
                }
            }
        };
        
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
        
        return $complexity;
    }

    /**
     * Calculate cognitive complexity
     */
    private function calculateCognitiveComplexity(array $ast): int
    {
        $complexity = 0;
        $nestingLevel = 0;
        
        $visitor = new class($complexity, $nestingLevel) extends \PhpParser\NodeVisitorAbstract {
            private int &$complexity;
            private int &$nestingLevel;
            
            public function __construct(int &$complexity, int &$nestingLevel)
            {
                $this->complexity = &$complexity;
                $this->nestingLevel = &$nestingLevel;
            }
            
            public function enterNode(Node $node)
            {
                // Increment for flow-breaking structures
                if ($node instanceof Node\Stmt\If_ ||
                    $node instanceof Node\Stmt\Switch_ ||
                    $node instanceof Node\Stmt\For_ ||
                    $node instanceof Node\Stmt\Foreach_ ||
                    $node instanceof Node\Stmt\While_ ||
                    $node instanceof Node\Stmt\Do_ ||
                    $node instanceof Node\Stmt\Catch_) {
                    
                    $this->complexity += 1 + $this->nestingLevel;
                    $this->nestingLevel++;
                }
                
                // Logical operators
                if ($node instanceof Node\Expr\BinaryOp\LogicalAnd ||
                    $node instanceof Node\Expr\BinaryOp\LogicalOr) {
                    $this->complexity++;
                }
                
                // Recursion
                if ($node instanceof Node\Expr\FuncCall) {
                    // Simple recursion detection
                    $this->complexity++;
                }
            }
            
            public function leaveNode(Node $node)
            {
                // Decrement nesting level when leaving control structures
                if ($node instanceof Node\Stmt\If_ ||
                    $node instanceof Node\Stmt\Switch_ ||
                    $node instanceof Node\Stmt\For_ ||
                    $node instanceof Node\Stmt\Foreach_ ||
                    $node instanceof Node\Stmt\While_ ||
                    $node instanceof Node\Stmt\Do_ ||
                    $node instanceof Node\Stmt\Catch_) {
                    $this->nestingLevel--;
                }
            }
        };
        
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
        
        return $complexity;
    }

    /**
     * Check complexity thresholds
     */
    private function checkComplexityThresholds(int $cyclomatic, int $cognitive, array &$issues): void
    {
        $cyclomaticThreshold = $this->config['complexity_threshold'] ?? 10;
        $cognitiveThreshold = $this->config['cognitive_threshold'] ?? 15;
        
        if ($cyclomatic > $cyclomaticThreshold) {
            $issues[] = [
                'type' => 'high_cyclomatic_complexity',
                'severity' => $cyclomatic > $cyclomaticThreshold * 2 ? 'HIGH' : 'MEDIUM',
                'message' => sprintf(
                    'High cyclomatic complexity: %d (threshold: %d)',
                    $cyclomatic,
                    $cyclomaticThreshold
                ),
                'impact_score' => min(10, $cyclomatic / $cyclomaticThreshold * 4),
                'suggestions' => [
                    'Break down complex functions into smaller ones',
                    'Reduce conditional logic and branching',
                    'Use polymorphism to replace complex conditionals',
                    'Extract complex conditions into well-named functions',
                ],
                'frequency' => 'always',
                'resource_type' => 'cpu',
            ];
        }
        
        if ($cognitive > $cognitiveThreshold) {
            $issues[] = [
                'type' => 'high_cognitive_complexity',
                'severity' => $cognitive > $cognitiveThreshold * 2 ? 'HIGH' : 'MEDIUM',
                'message' => sprintf(
                    'High cognitive complexity: %d (threshold: %d)',
                    $cognitive,
                    $cognitiveThreshold
                ),
                'impact_score' => min(10, $cognitive / $cognitiveThreshold * 3),
                'suggestions' => [
                    'Reduce nesting levels',
                    'Simplify logical conditions',
                    'Extract nested logic into separate functions',
                    'Use early returns to reduce nesting',
                ],
                'frequency' => 'always',
                'resource_type' => 'cpu',
            ];
        }
    }

    /**
     * Initialize complexity patterns
     */
    private function initializePatterns(): void
    {
        $this->complexityPatterns = [
            'O(1)' => 'constant',
            'O(log n)' => 'logarithmic',
            'O(n)' => 'linear',
            'O(n log n)' => 'linearithmic',
            'O(n²)' => 'quadratic',
            'O(n³)' => 'cubic',
            'O(2^n)' => 'exponential',
            'O(n!)' => 'factorial',
        ];
    }

    /**
     * Estimate loop complexity based on nesting
     */
    private function estimateLoopComplexity(int $nestingLevel): string
    {
        switch ($nestingLevel) {
            case 0:
                return 'O(n)';
            case 1:
                return 'O(n²)';
            case 2:
                return 'O(n³)';
            default:
                return sprintf('O(n^%d)', $nestingLevel + 1);
        }
    }

    /**
     * Calculate loop impact score
     */
    private function calculateLoopImpact(int $nestingLevel): float
    {
        return min(10, 3 + ($nestingLevel * 2.5));
    }

    /**
     * Check for inefficient patterns in loops
     */
    private function checkInefficienticientPatterns(array $loop, array &$issues): void
    {
        // This would need more context from the AST
        // Simplified for demonstration
        if (!($loop['has_break'] ?? false)) {
            // Loop without break might process unnecessary items
            // Would need more analysis to determine if this is actually an issue
        }
    }
}