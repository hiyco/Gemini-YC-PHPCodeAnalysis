<?php

declare(strict_types=1);

namespace YcPca\Security\Detectors;

use PhpParser\Node;
use YcPca\Security\SecurityContext;
use YcPca\Security\Rules\RuleEngine;

/**
 * Advanced SQL Injection Detector
 * Detects various SQL injection patterns using AST analysis
 */
class SqlInjectionDetector implements DetectorInterface
{
    private RuleEngine $ruleEngine;
    private bool $executed = false;
    private array $dangerousFunctions = [
        'mysql_query',
        'mysqli_query',
        'mysqli_multi_query',
        'mysqli_real_query',
        'pg_query',
        'pg_send_query',
        'sqlite_query',
        'sqlite_exec',
        'mssql_query',
        'oci_execute',
        'odbc_exec',
        'PDO::exec',
        'PDO::query',
    ];
    
    private array $ormMethods = [
        // Laravel
        'DB::select', 'DB::insert', 'DB::update', 'DB::delete', 'DB::statement',
        'whereRaw', 'orWhereRaw', 'havingRaw', 'orHavingRaw', 'orderByRaw',
        'selectRaw', 'groupByRaw',
        
        // Doctrine
        'createQuery', 'createNativeQuery',
        
        // CodeIgniter
        'query', 'simple_query',
    ];

    public function __construct(RuleEngine $ruleEngine)
    {
        $this->ruleEngine = $ruleEngine;
    }

    /**
     * Detect SQL injection vulnerabilities
     */
    public function detect(array $ast, SecurityContext $context, array &$vulnerabilities): void
    {
        $this->executed = true;
        
        $traverser = new \PhpParser\NodeTraverser();
        $visitor = new class($this, $context, $vulnerabilities) extends \PhpParser\NodeVisitorAbstract {
            private SqlInjectionDetector $detector;
            private SecurityContext $context;
            private array &$vulnerabilities;
            
            public function __construct(
                SqlInjectionDetector $detector,
                SecurityContext $context,
                array &$vulnerabilities
            ) {
                $this->detector = $detector;
                $this->context = $context;
                $this->vulnerabilities = &$vulnerabilities;
            }
            
            public function enterNode(Node $node)
            {
                // Check function calls
                if ($node instanceof Node\Expr\FuncCall) {
                    $this->detector->checkFunctionCall($node, $this->context, $this->vulnerabilities);
                }
                
                // Check method calls
                if ($node instanceof Node\Expr\MethodCall) {
                    $this->detector->checkMethodCall($node, $this->context, $this->vulnerabilities);
                }
                
                // Check static method calls
                if ($node instanceof Node\Expr\StaticCall) {
                    $this->detector->checkStaticCall($node, $this->context, $this->vulnerabilities);
                }
                
                // Check prepared statements
                if ($node instanceof Node\Expr\MethodCall &&
                    $this->detector->isPrepareMethod($node)) {
                    $this->detector->checkPreparedStatement($node, $this->context, $this->vulnerabilities);
                }
            }
        };
        
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
    }

    /**
     * Check function calls for SQL injection
     */
    public function checkFunctionCall(Node\Expr\FuncCall $node, SecurityContext $context, array &$vulnerabilities): void
    {
        $funcName = $this->getFunctionName($node);
        if (!$funcName || !in_array($funcName, $this->dangerousFunctions)) {
            return;
        }
        
        // Get the SQL query argument
        $queryArg = $node->args[0] ?? null;
        if (!$queryArg) return;
        
        $queryNode = $queryArg->value;
        
        // Analyze the query for injection risks
        $risks = $this->analyzeQueryNode($queryNode, $context);
        
        foreach ($risks as $risk) {
            $vulnerabilities[] = array_merge($risk, [
                'type' => 'sql_injection',
                'function' => $funcName,
                'line' => $node->getLine(),
                'file' => $context->getFilePath(),
                'attack_vector' => 'network',
                'attack_complexity' => 'low',
                'privileges_required' => 'none',
                'user_interaction' => 'none',
                'confidentiality_impact' => 'high',
                'integrity_impact' => 'high',
                'availability_impact' => 'low',
            ]);
        }
    }

    /**
     * Check method calls for SQL injection
     */
    public function checkMethodCall(Node\Expr\MethodCall $node, SecurityContext $context, array &$vulnerabilities): void
    {
        $methodName = $this->getMethodName($node);
        if (!$methodName) return;
        
        // Check for ORM raw query methods
        foreach ($this->ormMethods as $ormMethod) {
            if (str_ends_with($ormMethod, $methodName)) {
                $this->checkOrmMethod($node, $context, $vulnerabilities, $methodName);
                return;
            }
        }
        
        // Check for query builders with raw input
        if (in_array($methodName, ['where', 'having', 'orderBy', 'groupBy'])) {
            $this->checkQueryBuilder($node, $context, $vulnerabilities);
        }
    }

    /**
     * Check static method calls
     */
    public function checkStaticCall(Node\Expr\StaticCall $node, SecurityContext $context, array &$vulnerabilities): void
    {
        if ($node->class instanceof Node\Name) {
            $className = $node->class->toString();
            $methodName = $node->name instanceof Node\Identifier ? $node->name->toString() : null;
            
            if ($className === 'DB' && $methodName) {
                if (in_array($methodName, ['select', 'insert', 'update', 'delete', 'statement', 'raw'])) {
                    $this->checkDatabaseFacade($node, $context, $vulnerabilities);
                }
            }
        }
    }

    /**
     * Analyze query node for injection risks
     */
    private function analyzeQueryNode(Node $node, SecurityContext $context): array
    {
        $risks = [];
        
        // String concatenation
        if ($node instanceof Node\Expr\BinaryOp\Concat) {
            $leftRisks = $this->analyzeQueryNode($node->left, $context);
            $rightRisks = $this->analyzeQueryNode($node->right, $context);
            
            if ($this->containsUserInput($node->right, $context)) {
                $risks[] = [
                    'subtype' => 'string_concatenation',
                    'severity' => 'CRITICAL',
                    'confidence' => 0.95,
                    'message' => 'SQL query uses direct concatenation with user input',
                    'fix_suggestion' => 'Use parameterized queries or prepared statements',
                    'exploitable' => true,
                    'known_exploits' => true,
                    'user_reachable' => true,
                ];
            }
            
            return array_merge($risks, $leftRisks, $rightRisks);
        }
        
        // String interpolation
        if ($node instanceof Node\Scalar\Encapsed) {
            foreach ($node->parts as $part) {
                if ($part instanceof Node\Expr\Variable) {
                    if ($context->isTaintedVariable($part->name)) {
                        $risks[] = [
                            'subtype' => 'string_interpolation',
                            'severity' => 'CRITICAL',
                            'confidence' => 0.9,
                            'message' => 'SQL query uses string interpolation with tainted variable',
                            'fix_suggestion' => 'Use parameterized queries instead of string interpolation',
                            'exploitable' => true,
                            'user_reachable' => true,
                        ];
                    }
                }
            }
        }
        
        // sprintf/printf usage
        if ($node instanceof Node\Expr\FuncCall) {
            $funcName = $this->getFunctionName($node);
            if (in_array($funcName, ['sprintf', 'printf', 'vsprintf'])) {
                foreach ($node->args as $i => $arg) {
                    if ($i === 0) continue; // Skip format string
                    
                    if ($this->containsUserInput($arg->value, $context)) {
                        $risks[] = [
                            'subtype' => 'format_string',
                            'severity' => 'HIGH',
                            'confidence' => 0.85,
                            'message' => "SQL query built with $funcName contains user input",
                            'fix_suggestion' => 'Use proper parameter binding, not string formatting',
                            'exploitable' => true,
                            'user_reachable' => true,
                        ];
                    }
                }
            }
        }
        
        // Variable usage
        if ($node instanceof Node\Expr\Variable) {
            if ($context->isTaintedVariable($node->name)) {
                $risks[] = [
                    'subtype' => 'tainted_variable',
                    'severity' => 'HIGH',
                    'confidence' => 0.8,
                    'message' => 'SQL query uses potentially tainted variable directly',
                    'fix_suggestion' => 'Validate and sanitize input or use prepared statements',
                    'user_reachable' => true,
                ];
            }
        }
        
        return $risks;
    }

    /**
     * Check if node contains user input
     */
    private function containsUserInput(Node $node, SecurityContext $context): bool
    {
        // Check for superglobals
        if ($node instanceof Node\Expr\ArrayDimFetch) {
            if ($node->var instanceof Node\Expr\Variable) {
                $varName = $node->var->name;
                if (in_array($varName, ['_GET', '_POST', '_REQUEST', '_COOKIE', '_SERVER'])) {
                    return true;
                }
            }
        }
        
        // Check for tainted variables
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            if ($context->isTaintedVariable($node->name)) {
                return true;
            }
        }
        
        // Check for function calls that return user input
        if ($node instanceof Node\Expr\FuncCall) {
            $funcName = $this->getFunctionName($node);
            if (in_array($funcName, ['filter_input', 'filter_input_array', 'getenv'])) {
                return true;
            }
        }
        
        // Recursively check complex expressions
        if ($node instanceof Node\Expr\BinaryOp) {
            return $this->containsUserInput($node->left, $context) ||
                   $this->containsUserInput($node->right, $context);
        }
        
        return false;
    }

    /**
     * Check ORM methods for SQL injection
     */
    private function checkOrmMethod(
        Node\Expr\MethodCall $node,
        SecurityContext $context,
        array &$vulnerabilities,
        string $methodName
    ): void {
        // Check for raw query methods
        if (str_contains($methodName, 'Raw') || $methodName === 'query') {
            if (!empty($node->args)) {
                $queryArg = $node->args[0]->value;
                $risks = $this->analyzeQueryNode($queryArg, $context);
                
                foreach ($risks as $risk) {
                    $vulnerabilities[] = array_merge($risk, [
                        'type' => 'sql_injection',
                        'method' => $methodName,
                        'context' => 'ORM',
                        'line' => $node->getLine(),
                        'file' => $context->getFilePath(),
                    ]);
                }
            }
        }
    }

    /**
     * Check query builder methods
     */
    private function checkQueryBuilder(
        Node\Expr\MethodCall $node,
        SecurityContext $context,
        array &$vulnerabilities
    ): void {
        if (count($node->args) >= 3) {
            // Check if using raw expressions in where clauses
            $operator = $node->args[1]->value ?? null;
            if ($operator instanceof Node\Scalar\String_ && $operator->value === 'raw') {
                $vulnerabilities[] = [
                    'type' => 'sql_injection',
                    'subtype' => 'query_builder_raw',
                    'severity' => 'MEDIUM',
                    'confidence' => 0.7,
                    'message' => 'Query builder using raw expressions',
                    'line' => $node->getLine(),
                    'file' => $context->getFilePath(),
                    'fix_suggestion' => 'Avoid raw expressions in query builders',
                ];
            }
        }
    }

    /**
     * Check database facade methods
     */
    private function checkDatabaseFacade(
        Node\Expr\StaticCall $node,
        SecurityContext $context,
        array &$vulnerabilities
    ): void {
        if (!empty($node->args)) {
            $queryArg = $node->args[0]->value;
            $risks = $this->analyzeQueryNode($queryArg, $context);
            
            // Check for parameter binding
            $hasBinding = count($node->args) > 1;
            
            foreach ($risks as $risk) {
                if (!$hasBinding) {
                    $risk['confidence'] = min(1.0, $risk['confidence'] + 0.1);
                    $risk['message'] .= ' (no parameter binding detected)';
                }
                
                $vulnerabilities[] = array_merge($risk, [
                    'type' => 'sql_injection',
                    'context' => 'Database Facade',
                    'line' => $node->getLine(),
                    'file' => $context->getFilePath(),
                ]);
            }
        }
    }

    /**
     * Check prepared statements for proper usage
     */
    public function checkPreparedStatement(
        Node\Expr\MethodCall $node,
        SecurityContext $context,
        array &$vulnerabilities
    ): void {
        // Check if prepare() is called with concatenated strings
        if (!empty($node->args)) {
            $queryArg = $node->args[0]->value;
            
            if ($queryArg instanceof Node\Expr\BinaryOp\Concat) {
                $vulnerabilities[] = [
                    'type' => 'sql_injection',
                    'subtype' => 'improper_prepared_statement',
                    'severity' => 'HIGH',
                    'confidence' => 0.9,
                    'message' => 'Prepared statement query built with concatenation',
                    'line' => $node->getLine(),
                    'file' => $context->getFilePath(),
                    'fix_suggestion' => 'Use placeholders (?) in prepared statements, not concatenation',
                    'exploitable' => true,
                ];
            }
        }
    }

    /**
     * Check if method is a prepare method
     */
    public function isPrepareMethod(Node\Expr\MethodCall $node): bool
    {
        $methodName = $this->getMethodName($node);
        return in_array($methodName, ['prepare', 'prepare_query', 'prepareStatement']);
    }

    /**
     * Get function name from node
     */
    private function getFunctionName(Node\Expr\FuncCall $node): ?string
    {
        if ($node->name instanceof Node\Name) {
            return $node->name->toString();
        }
        return null;
    }

    /**
     * Get method name from node
     */
    private function getMethodName(Node\Expr\MethodCall $node): ?string
    {
        if ($node->name instanceof Node\Identifier) {
            return $node->name->toString();
        }
        return null;
    }

    /**
     * Check if detector has executed
     */
    public function hasExecuted(): bool
    {
        return $this->executed;
    }
}