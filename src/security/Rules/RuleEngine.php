<?php

declare(strict_types=1);

namespace YcPca\Security\Rules;

use PhpParser\Node;
use YcPca\Security\SecurityContext;

/**
 * Rule-based vulnerability detection engine
 * Supports custom rules, pattern matching, and AST-based analysis
 */
class RuleEngine
{
    private array $rules = [];
    private array $customRules = [];
    private array $patterns = [];
    private array $statistics = [];

    public function __construct(array $customRules = [])
    {
        $this->loadBuiltInRules();
        $this->loadCustomRules($customRules);
        $this->compilePatterns();
    }

    /**
     * Load built-in security rules
     */
    private function loadBuiltInRules(): void
    {
        $this->rules = [
            // SQL Injection Rules
            'sql_concat' => [
                'pattern' => '/query|exec|prepare/',
                'ast_check' => function (Node $node, SecurityContext $context): ?array {
                    if ($node instanceof Node\Expr\FuncCall &&
                        in_array($this->getFunctionName($node), ['mysql_query', 'mysqli_query', 'pg_query'])) {
                        
                        // Check for string concatenation in query
                        if ($this->hasStringConcatenation($node->args[0] ?? null)) {
                            return [
                                'type' => 'sql_injection',
                                'subtype' => 'string_concatenation',
                                'severity' => 'HIGH',
                                'confidence' => 0.9,
                                'message' => 'SQL query uses string concatenation with user input',
                                'line' => $node->getLine(),
                            ];
                        }
                    }
                    return null;
                },
            ],
            
            // XSS Rules
            'unsafe_output' => [
                'pattern' => '/echo|print|printf/',
                'ast_check' => function (Node $node, SecurityContext $context): ?array {
                    if ($node instanceof Node\Stmt\Echo_) {
                        foreach ($node->exprs as $expr) {
                            if ($this->isUserInput($expr, $context) && !$this->hasEscaping($expr)) {
                                return [
                                    'type' => 'xss',
                                    'subtype' => 'unescaped_output',
                                    'severity' => 'HIGH',
                                    'confidence' => 0.85,
                                    'message' => 'User input echoed without escaping',
                                    'line' => $node->getLine(),
                                ];
                            }
                        }
                    }
                    return null;
                },
            ],
            
            // Code Execution Rules
            'eval_usage' => [
                'pattern' => '/eval/',
                'ast_check' => function (Node $node, SecurityContext $context): ?array {
                    if ($node instanceof Node\Expr\Eval_) {
                        return [
                            'type' => 'code_execution',
                            'subtype' => 'eval_usage',
                            'severity' => 'CRITICAL',
                            'confidence' => 1.0,
                            'message' => 'Use of eval() is extremely dangerous',
                            'line' => $node->getLine(),
                            'recommendation' => 'Remove eval() and use safer alternatives',
                        ];
                    }
                    return null;
                },
            ],
            
            // File Inclusion Rules
            'dynamic_include' => [
                'pattern' => '/include|require/',
                'ast_check' => function (Node $node, SecurityContext $context): ?array {
                    if ($node instanceof Node\Expr\Include_) {
                        if ($this->isDynamicValue($node->expr, $context)) {
                            return [
                                'type' => 'file_inclusion',
                                'subtype' => 'dynamic_include',
                                'severity' => 'HIGH',
                                'confidence' => 0.8,
                                'message' => 'Dynamic file inclusion detected',
                                'line' => $node->getLine(),
                            ];
                        }
                    }
                    return null;
                },
            ],
            
            // Sensitive Data Rules
            'hardcoded_password' => [
                'pattern' => '/password|passwd|pwd|secret|api_key|apikey/',
                'ast_check' => function (Node $node, SecurityContext $context): ?array {
                    if ($node instanceof Node\Expr\Assign) {
                        $varName = $this->getVariableName($node->var);
                        if ($varName && $this->isSensitiveVariable($varName)) {
                            if ($node->expr instanceof Node\Scalar\String_) {
                                $value = $node->expr->value;
                                if (strlen($value) > 5 && !$this->isPlaceholder($value)) {
                                    return [
                                        'type' => 'sensitive_data',
                                        'subtype' => 'hardcoded_credential',
                                        'severity' => 'CRITICAL',
                                        'confidence' => 0.95,
                                        'message' => "Hardcoded credential in variable '$varName'",
                                        'line' => $node->getLine(),
                                    ];
                                }
                            }
                        }
                    }
                    return null;
                },
            ],
            
            // CSRF Rules
            'missing_csrf_token' => [
                'pattern' => '/form|post|delete|update/',
                'ast_check' => function (Node $node, SecurityContext $context): ?array {
                    if ($this->isFormHandler($node, $context)) {
                        if (!$this->hasCsrfProtection($node, $context)) {
                            return [
                                'type' => 'csrf',
                                'subtype' => 'missing_token',
                                'severity' => 'MEDIUM',
                                'confidence' => 0.7,
                                'message' => 'Form handler lacks CSRF protection',
                                'line' => $node->getLine(),
                            ];
                        }
                    }
                    return null;
                },
            ],
            
            // Deserialization Rules
            'unsafe_unserialize' => [
                'pattern' => '/unserialize/',
                'ast_check' => function (Node $node, SecurityContext $context): ?array {
                    if ($node instanceof Node\Expr\FuncCall &&
                        $this->getFunctionName($node) === 'unserialize') {
                        
                        if ($this->isUserInput($node->args[0]->value ?? null, $context)) {
                            return [
                                'type' => 'deserialization',
                                'subtype' => 'unsafe_unserialize',
                                'severity' => 'CRITICAL',
                                'confidence' => 0.9,
                                'message' => 'Unserialize called with user input',
                                'line' => $node->getLine(),
                            ];
                        }
                    }
                    return null;
                },
            ],
            
            // Command Injection Rules
            'command_execution' => [
                'pattern' => '/exec|system|shell_exec|passthru|popen|proc_open/',
                'ast_check' => function (Node $node, SecurityContext $context): ?array {
                    if ($node instanceof Node\Expr\FuncCall) {
                        $funcName = $this->getFunctionName($node);
                        if (in_array($funcName, ['exec', 'system', 'shell_exec', 'passthru'])) {
                            if ($this->hasUserInput($node, $context)) {
                                return [
                                    'type' => 'command_injection',
                                    'subtype' => 'shell_command',
                                    'severity' => 'CRITICAL',
                                    'confidence' => 0.85,
                                    'message' => "Dangerous function '$funcName' with user input",
                                    'line' => $node->getLine(),
                                ];
                            }
                        }
                    }
                    return null;
                },
            ],
            
            // Path Traversal Rules
            'path_traversal' => [
                'pattern' => '/file_get_contents|fopen|include|require/',
                'ast_check' => function (Node $node, SecurityContext $context): ?array {
                    if ($node instanceof Node\Expr\FuncCall) {
                        $funcName = $this->getFunctionName($node);
                        if (in_array($funcName, ['file_get_contents', 'fopen', 'readfile'])) {
                            if ($this->hasPathTraversalRisk($node, $context)) {
                                return [
                                    'type' => 'path_traversal',
                                    'subtype' => 'file_access',
                                    'severity' => 'HIGH',
                                    'confidence' => 0.8,
                                    'message' => 'Potential path traversal vulnerability',
                                    'line' => $node->getLine(),
                                ];
                            }
                        }
                    }
                    return null;
                },
            ],
            
            // Weak Cryptography Rules
            'weak_hash' => [
                'pattern' => '/md5|sha1/',
                'ast_check' => function (Node $node, SecurityContext $context): ?array {
                    if ($node instanceof Node\Expr\FuncCall) {
                        $funcName = $this->getFunctionName($node);
                        if (in_array($funcName, ['md5', 'sha1'])) {
                            // Check if used for passwords
                            if ($this->isPasswordContext($node, $context)) {
                                return [
                                    'type' => 'weak_crypto',
                                    'subtype' => 'weak_hash',
                                    'severity' => 'HIGH',
                                    'confidence' => 0.9,
                                    'message' => "Weak hash function '$funcName' used for passwords",
                                    'line' => $node->getLine(),
                                    'recommendation' => 'Use password_hash() instead',
                                ];
                            }
                        }
                    }
                    return null;
                },
            ],
        ];
    }

    /**
     * Apply rules to AST nodes
     */
    public function applyRules(array $ast, SecurityContext $context, array &$vulnerabilities): void
    {
        $traverser = new \PhpParser\NodeTraverser();
        $visitor = new class($this, $context, $vulnerabilities) extends \PhpParser\NodeVisitorAbstract {
            private RuleEngine $engine;
            private SecurityContext $context;
            private array &$vulnerabilities;
            
            public function __construct(RuleEngine $engine, SecurityContext $context, array &$vulnerabilities)
            {
                $this->engine = $engine;
                $this->context = $context;
                $this->vulnerabilities = &$vulnerabilities;
            }
            
            public function enterNode(Node $node)
            {
                foreach ($this->engine->rules as $ruleName => $rule) {
                    if ($vulnerability = $rule['ast_check']($node, $this->context)) {
                        $vulnerability['rule'] = $ruleName;
                        $vulnerability['node_type'] = get_class($node);
                        $this->vulnerabilities[] = $vulnerability;
                    }
                }
                
                // Apply custom rules
                foreach ($this->engine->customRules as $ruleName => $rule) {
                    if ($vulnerability = $rule['check']($node, $this->context)) {
                        $vulnerability['rule'] = $ruleName;
                        $vulnerability['custom'] = true;
                        $this->vulnerabilities[] = $vulnerability;
                    }
                }
            }
        };
        
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
        
        // Update statistics
        $this->updateStatistics($vulnerabilities);
    }

    /**
     * Load custom rules
     */
    private function loadCustomRules(array $customRules): void
    {
        foreach ($customRules as $name => $rule) {
            if ($this->validateCustomRule($rule)) {
                $this->customRules[$name] = $rule;
            }
        }
    }

    /**
     * Validate custom rule structure
     */
    private function validateCustomRule(array $rule): bool
    {
        return isset($rule['check']) && 
               is_callable($rule['check']) &&
               isset($rule['severity']) &&
               isset($rule['type']);
    }

    /**
     * Compile regex patterns for efficiency
     */
    private function compilePatterns(): void
    {
        foreach ($this->rules as $name => $rule) {
            if (isset($rule['pattern'])) {
                $this->patterns[$name] = $rule['pattern'];
            }
        }
    }

    /**
     * Helper: Get function name from node
     */
    private function getFunctionName(Node $node): ?string
    {
        if ($node instanceof Node\Expr\FuncCall) {
            if ($node->name instanceof Node\Name) {
                return $node->name->toString();
            }
        }
        return null;
    }

    /**
     * Helper: Check for string concatenation
     */
    private function hasStringConcatenation(?Node $node): bool
    {
        if (!$node) return false;
        
        if ($node instanceof Node\Arg) {
            $node = $node->value;
        }
        
        return $node instanceof Node\Expr\BinaryOp\Concat;
    }

    /**
     * Helper: Check if value is user input
     */
    private function isUserInput(?Node $node, SecurityContext $context): bool
    {
        if (!$node) return false;
        
        // Check for $_GET, $_POST, $_REQUEST, etc.
        if ($node instanceof Node\Expr\ArrayDimFetch) {
            if ($node->var instanceof Node\Expr\Variable) {
                $varName = $node->var->name;
                if (in_array($varName, ['_GET', '_POST', '_REQUEST', '_COOKIE', '_SERVER'])) {
                    return true;
                }
            }
        }
        
        // Check tainted variables from context
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            return $context->isTaintedVariable($node->name);
        }
        
        return false;
    }

    /**
     * Helper: Check if output has escaping
     */
    private function hasEscaping(Node $node): bool
    {
        // Check for htmlspecialchars, htmlentities, etc.
        if ($node instanceof Node\Expr\FuncCall) {
            $funcName = $this->getFunctionName($node);
            return in_array($funcName, [
                'htmlspecialchars',
                'htmlentities',
                'strip_tags',
                'filter_var',
                'esc_html',
                'esc_attr',
            ]);
        }
        return false;
    }

    /**
     * Helper: Check if value is dynamic
     */
    private function isDynamicValue(Node $node, SecurityContext $context): bool
    {
        // Not a simple string literal
        if (!($node instanceof Node\Scalar\String_)) {
            return true;
        }
        
        // Check if contains variables
        if ($node instanceof Node\Scalar\Encapsed) {
            return true;
        }
        
        return false;
    }

    /**
     * Helper: Get variable name
     */
    private function getVariableName(Node $node): ?string
    {
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            return $node->name;
        }
        return null;
    }

    /**
     * Helper: Check if variable name is sensitive
     */
    private function isSensitiveVariable(string $name): bool
    {
        $patterns = [
            '/password/i',
            '/passwd/i',
            '/pwd/i',
            '/secret/i',
            '/api_?key/i',
            '/token/i',
            '/credential/i',
            '/private_?key/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Helper: Check if value is placeholder
     */
    private function isPlaceholder(string $value): bool
    {
        $placeholders = [
            'password',
            'secret',
            'changeme',
            'example',
            'placeholder',
            '********',
            'xxx',
        ];
        
        return in_array(strtolower($value), $placeholders) ||
               preg_match('/^[<\[{].+[>\]}]$/', $value);
    }

    /**
     * Helper: Check if node is form handler
     */
    private function isFormHandler(Node $node, SecurityContext $context): bool
    {
        // Check for POST request handling
        if ($node instanceof Node\Expr\ArrayDimFetch &&
            $node->var instanceof Node\Expr\Variable &&
            $node->var->name === '_POST') {
            return true;
        }
        
        // Check for form processing functions
        if ($node instanceof Node\Stmt\Function_ || $node instanceof Node\Stmt\ClassMethod) {
            $name = $node->name->toString();
            if (preg_match('/handle|process|submit|save|update|delete/i', $name)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Helper: Check for CSRF protection
     */
    private function hasCsrfProtection(Node $node, SecurityContext $context): bool
    {
        // This would need to check for CSRF token validation
        // Simplified for demonstration
        return false;
    }

    /**
     * Helper: Check for user input in node
     */
    private function hasUserInput(Node $node, SecurityContext $context): bool
    {
        foreach ($node->args ?? [] as $arg) {
            if ($this->isUserInput($arg->value, $context)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Helper: Check for path traversal risk
     */
    private function hasPathTraversalRisk(Node $node, SecurityContext $context): bool
    {
        if (!isset($node->args[0])) return false;
        
        $arg = $node->args[0]->value;
        
        // Check if input contains user data
        if ($this->isUserInput($arg, $context)) {
            // Check if there's validation
            // Simplified - would need more complex analysis
            return true;
        }
        
        return false;
    }

    /**
     * Helper: Check if in password context
     */
    private function isPasswordContext(Node $node, SecurityContext $context): bool
    {
        // Check surrounding code for password-related operations
        $parent = $node->getAttribute('parent');
        if ($parent instanceof Node\Expr\Assign) {
            $varName = $this->getVariableName($parent->var);
            return $varName && $this->isSensitiveVariable($varName);
        }
        return false;
    }

    /**
     * Update rule statistics
     */
    private function updateStatistics(array $vulnerabilities): void
    {
        foreach ($vulnerabilities as $vuln) {
            $rule = $vuln['rule'] ?? 'unknown';
            $this->statistics[$rule] = ($this->statistics[$rule] ?? 0) + 1;
        }
    }

    /**
     * Get rule statistics
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * Add custom rule at runtime
     */
    public function addRule(string $name, array $rule): void
    {
        if ($this->validateCustomRule($rule)) {
            $this->customRules[$name] = $rule;
        }
    }

    /**
     * Remove rule
     */
    public function removeRule(string $name): void
    {
        unset($this->rules[$name]);
        unset($this->customRules[$name]);
    }

    /**
     * Get all active rules
     */
    public function getRules(): array
    {
        return array_merge($this->rules, $this->customRules);
    }
}