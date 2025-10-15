<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: SQL Injection Detection Rule
 */

namespace YcPca\Analysis\Security\Rule;

use PhpParser\Node;
use YcPca\Analysis\Issue\Issue;
use YcPca\Model\FileContext;

/**
 * Rule to detect potential SQL injection vulnerabilities
 * 
 * Features:
 * - Direct SQL query detection
 * - String concatenation in queries
 * - Unsafe parameter usage
 * - Database function analysis
 */
class SqlInjectionRule extends AbstractSecurityRule
{
    protected array $supportedNodeTypes = [
        'Expr_FuncCall',
        'Expr_MethodCall',
        'Scalar_String'
    ];

    protected function initializeSecurityProperties(): void
    {
        $this->vulnerabilityType = 'sql_injection';
        $this->owaspCategory = 'A03_injection';
        $this->riskLevel = Issue::SEVERITY_HIGH;
        $this->cweIds = [89, 564]; // CWE-89: SQL Injection, CWE-564: SQL Injection: Hibernate
        $this->contextDependent = true;
        $this->falsePositiveProbability = 0.15;
        $this->performanceImpact = 'medium';
    }

    public function getRuleId(): string
    {
        return 'sql_injection';
    }

    public function getRuleName(): string
    {
        return 'SQL Injection Detection';
    }

    public function getDescription(): string
    {
        return 'Detects potential SQL injection vulnerabilities in database queries';
    }

    public function validate(Node $node, FileContext $context): array
    {
        $issues = [];
        
        switch ($node->getType()) {
            case 'Expr_FuncCall':
                $issues = array_merge($issues, $this->validateFunctionCall($node, $context));
                break;
                
            case 'Expr_MethodCall':
                $issues = array_merge($issues, $this->validateMethodCall($node, $context));
                break;
                
            case 'Scalar_String':
                $issues = array_merge($issues, $this->validateStringLiteral($node, $context));
                break;
        }
        
        return $issues;
    }

    public function getPriority(): int
    {
        return 90; // High priority for SQL injection
    }

    public function getTags(): array
    {
        return array_merge(parent::getTags(), ['injection', 'database', 'sql']);
    }

    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'check_mysql_functions' => true,
            'check_pdo_methods' => true,
            'check_string_concatenation' => true,
            'check_variable_interpolation' => true,
            'allow_prepared_statements' => true,
            'dangerous_functions' => [
                'mysql_query', 'mysqli_query', 'mysql_db_query',
                'pg_query', 'pg_send_query', 'sqlite_query'
            ],
            'safe_functions' => [
                'mysqli_prepare', 'mysql_prepare', 'PDO::prepare'
            ]
        ]);
    }

    protected function getDefaultRemediationSuggestions(): array
    {
        return [
            'Use prepared statements with parameter binding',
            'Validate and sanitize all user input before database queries',
            'Use parameterized queries instead of string concatenation',
            'Implement input validation with whitelisting',
            'Use ORM frameworks that provide SQL injection protection',
            'Escape special characters in dynamic SQL queries',
            'Implement least privilege principle for database access'
        ];
    }

    protected function getDefaultBestPractices(): array
    {
        return [
            'Always use prepared statements for dynamic queries',
            'Never concatenate user input directly into SQL queries',
            'Validate input data types and ranges',
            'Use stored procedures where appropriate',
            'Implement proper error handling without exposing database structure',
            'Regularly update database software and libraries',
            'Use database-specific escaping functions as a last resort'
        ];
    }

    /**
     * Validate function calls for SQL injection risks
     */
    private function validateFunctionCall(Node\Expr\FuncCall $funcCall, FileContext $context): array
    {
        $issues = [];
        
        if (!($funcCall->name instanceof Node\Name)) {
            return $issues;
        }
        
        $funcName = $funcCall->name->toString();
        $dangerousFunctions = $this->getConfigValue('dangerous_functions', []);
        $safeFunctions = $this->getConfigValue('safe_functions', []);
        
        // Check for dangerous database functions
        if (in_array($funcName, $dangerousFunctions, true)) {
            $risk = $this->analyzeFunctionArguments($funcCall, $context);
            
            if ($risk['has_risk']) {
                $issue = $this->createSecurityIssue(
                    title: "Potential SQL Injection: {$funcName}()",
                    description: "Function '{$funcName}' is vulnerable to SQL injection attacks. " . $risk['description'],
                    node: $funcCall,
                    suggestions: $this->getSpecificSuggestions($funcName, $risk),
                    codeSnippet: $this->getCodeSnippet($funcCall, $context, 1),
                    metadata: [
                        'function_name' => $funcName,
                        'risk_factors' => $risk['factors'],
                        'confidence_level' => $risk['confidence']
                    ]
                );
                $issues[] = $issue;
            }
        }
        
        // Check for safe functions used incorrectly
        if (in_array($funcName, $safeFunctions, true)) {
            $misuse = $this->analyzeSafeFunctionMisuse($funcCall, $context);
            
            if ($misuse['has_misuse']) {
                $issue = $this->createSecurityIssue(
                    title: "Incorrect usage of safe function: {$funcName}()",
                    description: $misuse['description'],
                    node: $funcCall,
                    suggestions: $misuse['suggestions'],
                    codeSnippet: $this->getCodeSnippet($funcCall, $context, 1),
                    metadata: [
                        'function_name' => $funcName,
                        'misuse_type' => $misuse['type']
                    ]
                );
                $issue->setSeverity(Issue::SEVERITY_MEDIUM);
                $issues[] = $issue;
            }
        }
        
        return $issues;
    }

    /**
     * Validate method calls for SQL injection risks
     */
    private function validateMethodCall(Node\Expr\MethodCall $methodCall, FileContext $context): array
    {
        $issues = [];
        
        if (!($methodCall->name instanceof Node\Identifier)) {
            return $issues;
        }
        
        $methodName = $methodCall->name->toString();
        
        // Check PDO methods
        if ($this->isConfigEnabled('check_pdo_methods') && $this->isPdoMethod($methodName)) {
            $risk = $this->analyzePdoMethodCall($methodCall, $context);
            
            if ($risk['has_risk']) {
                $issue = $this->createSecurityIssue(
                    title: "Potential SQL Injection in PDO method: {$methodName}()",
                    description: $risk['description'],
                    node: $methodCall,
                    suggestions: $this->getPdoSpecificSuggestions($methodName, $risk),
                    codeSnippet: $this->getCodeSnippet($methodCall, $context, 1),
                    metadata: [
                        'method_name' => $methodName,
                        'pdo_context' => true,
                        'risk_factors' => $risk['factors']
                    ]
                );
                $issues[] = $issue;
            }
        }
        
        return $issues;
    }

    /**
     * Validate string literals for SQL injection patterns
     */
    private function validateStringLiteral(Node\Scalar\String $stringNode, FileContext $context): array
    {
        $issues = [];
        
        if (!$this->isConfigEnabled('check_string_concatenation')) {
            return $issues;
        }
        
        $sqlContent = strtolower($stringNode->value);
        
        // Check if string contains SQL keywords
        if ($this->containsSqlKeywords($sqlContent)) {
            // Check if string is being concatenated with variables
            $parent = $stringNode->getAttribute('parent');
            
            if ($this->isInConcatenationContext($parent)) {
                $issue = $this->createSecurityIssue(
                    title: 'Potential SQL Injection in string concatenation',
                    description: 'SQL query string is being concatenated with variables, which may lead to SQL injection.',
                    node: $stringNode,
                    suggestions: [
                        'Use prepared statements instead of string concatenation',
                        'Use parameter binding for dynamic values'
                    ],
                    codeSnippet: $this->getCodeSnippet($stringNode, $context, 2),
                    metadata: [
                        'sql_keywords_found' => $this->extractSqlKeywords($sqlContent),
                        'concatenation_context' => true
                    ]
                );
                $issues[] = $issue;
            }
        }
        
        return $issues;
    }

    /**
     * Analyze function arguments for SQL injection risks
     */
    private function analyzeFunctionArguments(Node\Expr\FuncCall $funcCall, FileContext $context): array
    {
        $risk = [
            'has_risk' => false,
            'description' => '',
            'factors' => [],
            'confidence' => 0.0
        ];
        
        if (empty($funcCall->args)) {
            return $risk;
        }
        
        $firstArg = $funcCall->args[0]->value;
        
        // Check for direct variable usage
        if ($firstArg instanceof Node\Expr\Variable) {
            $risk['has_risk'] = true;
            $risk['factors'][] = 'direct_variable_usage';
            $risk['description'] = 'Direct variable usage in SQL query without validation.';
            $risk['confidence'] += 0.6;
        }
        
        // Check for string concatenation
        if ($firstArg instanceof Node\Expr\BinaryOp\Concat) {
            $risk['has_risk'] = true;
            $risk['factors'][] = 'string_concatenation';
            $risk['description'] = 'String concatenation used to build SQL query.';
            $risk['confidence'] += 0.8;
        }
        
        // Check for variable interpolation
        if ($firstArg instanceof Node\Scalar\String && $this->hasVariableInterpolation($firstArg->value)) {
            $risk['has_risk'] = true;
            $risk['factors'][] = 'variable_interpolation';
            $risk['description'] = 'Variable interpolation found in SQL query string.';
            $risk['confidence'] += 0.7;
        }
        
        return $risk;
    }

    /**
     * Check if method is a PDO method
     */
    private function isPdoMethod(string $methodName): bool
    {
        $pdoMethods = ['query', 'exec', 'prepare'];
        return in_array($methodName, $pdoMethods, true);
    }

    /**
     * Analyze PDO method calls
     */
    private function analyzePdoMethodCall(Node\Expr\MethodCall $methodCall, FileContext $context): array
    {
        // Similar to analyzeFunctionArguments but PDO-specific
        return [
            'has_risk' => false,
            'description' => '',
            'factors' => []
        ];
    }

    /**
     * Check if string contains SQL keywords
     */
    private function containsSqlKeywords(string $content): bool
    {
        $sqlKeywords = ['select', 'insert', 'update', 'delete', 'drop', 'create', 'alter', 'union', 'where'];
        
        foreach ($sqlKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if node is in concatenation context
     */
    private function isInConcatenationContext(?Node $parent): bool
    {
        return $parent instanceof Node\Expr\BinaryOp\Concat;
    }

    /**
     * Check if string has variable interpolation
     */
    private function hasVariableInterpolation(string $content): bool
    {
        return preg_match('/\$\w+|\{\$\w+\}/', $content);
    }

    /**
     * Extract SQL keywords from content
     */
    private function extractSqlKeywords(string $content): array
    {
        $keywords = [];
        $sqlKeywords = ['select', 'insert', 'update', 'delete', 'drop', 'create', 'alter', 'union', 'where'];
        
        foreach ($sqlKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $keywords[] = $keyword;
            }
        }
        
        return $keywords;
    }

    /**
     * Get specific suggestions for function
     */
    private function getSpecificSuggestions(string $funcName, array $risk): array
    {
        $suggestions = [];
        
        if (strpos($funcName, 'mysql') !== false) {
            $suggestions[] = 'Replace ' . $funcName . ' with mysqli_prepare() and parameter binding';
        }
        
        if (in_array('string_concatenation', $risk['factors'], true)) {
            $suggestions[] = 'Use prepared statements instead of concatenating strings';
        }
        
        return array_merge($suggestions, $this->getRemediationSuggestions());
    }

    /**
     * Get PDO-specific suggestions
     */
    private function getPdoSpecificSuggestions(string $methodName, array $risk): array
    {
        $suggestions = [];
        
        if ($methodName === 'query') {
            $suggestions[] = 'Use PDO::prepare() instead of PDO::query() for dynamic queries';
        }
        
        return array_merge($suggestions, $this->getRemediationSuggestions());
    }

    /**
     * Analyze safe function misuse
     */
    private function analyzeSafeFunctionMisuse(Node\Expr\FuncCall $funcCall, FileContext $context): array
    {
        // Implementation for analyzing misuse of safe functions
        return [
            'has_misuse' => false,
            'description' => '',
            'type' => '',
            'suggestions' => []
        ];
    }
}