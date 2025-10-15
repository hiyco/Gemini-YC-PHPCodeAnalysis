<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Unused Variable Detection Rule
 */

namespace YcPca\Analysis\Syntax\Rule;

use PhpParser\Node;
use YcPca\Analysis\Issue\Issue;
use YcPca\Model\FileContext;

/**
 * Rule to detect unused variables
 * 
 * Features:
 * - Function/method scope analysis
 * - Exception for specific patterns
 * - Configurable ignore patterns
 */
class UnusedVariableRule extends AbstractSyntaxRule
{
    protected array $supportedNodeTypes = [
        'Stmt_Function',
        'Stmt_ClassMethod',
        'Expr_Closure'
    ];

    private array $variableUsage = [];

    public function getRuleId(): string
    {
        return 'unused_variable';
    }

    public function getRuleName(): string
    {
        return 'Unused Variable Detection';
    }

    public function getDescription(): string
    {
        return 'Detects variables that are assigned but never used';
    }

    public function getCategory(): string
    {
        return Issue::CATEGORY_QUALITY;
    }

    public function getSeverity(): string
    {
        return Issue::SEVERITY_MEDIUM;
    }

    public function validate(Node $node, FileContext $context): array
    {
        $issues = [];
        
        // Reset variable usage for this scope
        $this->variableUsage = [];
        
        // Analyze variable usage within this function/method
        $this->analyzeScope($node);
        
        // Find unused variables
        foreach ($this->variableUsage as $varName => $info) {
            if ($info['assigned'] && !$info['used'] && !$this->shouldIgnoreVariable($varName)) {
                $issue = $this->createVariableIssue($varName, $info, $node, $context);
                $issues[] = $issue;
            }
        }
        
        return $issues;
    }

    public function getPriority(): int
    {
        return 60; // Medium priority for quality issues
    }

    public function getTags(): array
    {
        return ['syntax', 'quality', 'cleanup', 'unused'];
    }

    public function validateConfig(array $config): array
    {
        $errors = [];
        
        if (isset($config['ignore_patterns']) && !is_array($config['ignore_patterns'])) {
            $errors[] = 'ignore_patterns must be an array';
        }
        
        if (isset($config['ignore_parameters']) && !is_bool($config['ignore_parameters'])) {
            $errors[] = 'ignore_parameters must be a boolean';
        }
        
        return $errors;
    }

    public function reset(): self
    {
        $this->variableUsage = [];
        return $this;
    }

    protected function getDefaultConfig(): array
    {
        return [
            'ignore_patterns' => [
                '_.*',  // Variables starting with underscore
                '.*Exception$', // Exception variables
                'this', // $this reference
            ],
            'ignore_parameters' => false, // Whether to ignore unused function parameters
            'ignore_global_variables' => true,
            'ignore_superglobals' => true
        ];
    }

    /**
     * Analyze variable usage within a scope
     */
    private function analyzeScope(Node $node): void
    {
        $this->traverseNode($node);
    }

    /**
     * Traverse node and track variable usage
     */
    private function traverseNode(Node $node): void
    {
        // Handle variable assignments
        if ($node instanceof Node\Expr\Assign) {
            $this->handleAssignment($node);
        }
        
        // Handle variable usage
        if ($node instanceof Node\Expr\Variable) {
            $this->handleVariableUsage($node);
        }
        
        // Handle function parameters
        if ($node instanceof Node\Param) {
            $this->handleParameter($node);
        }
        
        // Recursively traverse child nodes
        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->$subNodeName;
            
            if ($subNode instanceof Node) {
                $this->traverseNode($subNode);
            } elseif (is_array($subNode)) {
                foreach ($subNode as $arrayNode) {
                    if ($arrayNode instanceof Node) {
                        $this->traverseNode($arrayNode);
                    }
                }
            }
        }
    }

    /**
     * Handle variable assignment
     */
    private function handleAssignment(Node\Expr\Assign $assign): void
    {
        if ($assign->var instanceof Node\Expr\Variable && is_string($assign->var->name)) {
            $varName = $assign->var->name;
            
            $this->variableUsage[$varName] = [
                'assigned' => true,
                'used' => false,
                'line' => $assign->getStartLine(),
                'type' => 'assignment'
            ];
        }
    }

    /**
     * Handle variable usage (reading)
     */
    private function handleVariableUsage(Node\Expr\Variable $variable): void
    {
        if (is_string($variable->name)) {
            $varName = $variable->name;
            
            // Check if this is part of an assignment target
            if ($this->isAssignmentTarget($variable)) {
                return; // Skip, this is handled by handleAssignment
            }
            
            // Mark as used
            if (isset($this->variableUsage[$varName])) {
                $this->variableUsage[$varName]['used'] = true;
            } else {
                // Variable used without assignment (could be parameter or global)
                $this->variableUsage[$varName] = [
                    'assigned' => false,
                    'used' => true,
                    'line' => $variable->getStartLine(),
                    'type' => 'usage'
                ];
            }
        }
    }

    /**
     * Handle function parameter
     */
    private function handleParameter(Node\Param $param): void
    {
        if ($param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
            $varName = $param->var->name;
            
            $this->variableUsage[$varName] = [
                'assigned' => true, // Parameters are considered assigned
                'used' => false,
                'line' => $param->getStartLine(),
                'type' => 'parameter'
            ];
        }
    }

    /**
     * Check if variable should be ignored
     */
    private function shouldIgnoreVariable(string $varName): bool
    {
        $ignorePatterns = $this->getConfigValue('ignore_patterns', []);
        $ignoreParameters = $this->getConfigValue('ignore_parameters', false);
        $ignoreGlobals = $this->getConfigValue('ignore_global_variables', true);
        $ignoreSuperglobals = $this->getConfigValue('ignore_superglobals', true);
        
        // Check if it's a parameter and we're ignoring parameters
        if ($ignoreParameters && isset($this->variableUsage[$varName]) && 
            $this->variableUsage[$varName]['type'] === 'parameter') {
            return true;
        }
        
        // Check superglobals
        if ($ignoreSuperglobals && in_array($varName, [
            'GLOBALS', '_GET', '_POST', '_SESSION', '_COOKIE', '_FILES', '_ENV', '_SERVER', '_REQUEST'
        ])) {
            return true;
        }
        
        // Check ignore patterns
        foreach ($ignorePatterns as $pattern) {
            if (preg_match('/^' . str_replace('*', '.*', $pattern) . '$/', $varName)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if variable is the target of an assignment
     */
    private function isAssignmentTarget(Node\Expr\Variable $variable): bool
    {
        // Get parent node to check context
        $parent = $variable->getAttribute('parent');
        
        if ($parent instanceof Node\Expr\Assign && $parent->var === $variable) {
            return true;
        }
        
        return false;
    }

    /**
     * Create issue for unused variable
     */
    private function createVariableIssue(string $varName, array $info, Node $node, FileContext $context): Issue
    {
        $type = $info['type'];
        $line = $info['line'];
        
        $title = "Unused {$type}: \${$varName}";
        $description = "Variable '\${$varName}' is {$this->getVariableTypeDescription($type)} but never used.";
        
        $suggestions = $this->getVariableSuggestions($varName, $type);
        
        // Create a temporary node for the specific line
        $tempNode = new Node\Expr\Variable($varName, [
            'startLine' => $line,
            'endLine' => $line
        ]);
        
        return $this->createIssue(
            title: $title,
            description: $description,
            node: $tempNode,
            suggestions: $suggestions,
            codeSnippet: $this->getCodeSnippet($tempNode, $context, 1),
            metadata: [
                'variable_name' => $varName,
                'variable_type' => $type,
                'assignment_line' => $line
            ]
        );
    }

    /**
     * Get description for variable type
     */
    private function getVariableTypeDescription(string $type): string
    {
        return match ($type) {
            'parameter' => 'declared as a parameter',
            'assignment' => 'assigned a value',
            'usage' => 'referenced',
            default => 'defined'
        };
    }

    /**
     * Get suggestions for unused variable
     */
    private function getVariableSuggestions(string $varName, string $type): array
    {
        $suggestions = [];
        
        switch ($type) {
            case 'parameter':
                $suggestions = [
                    "Remove the unused parameter '\${$varName}' if it's not needed",
                    "Use the parameter '\${$varName}' in the function body",
                    "Prefix with underscore (_{$varName}) to indicate intentionally unused",
                    "Add a comment explaining why the parameter is unused"
                ];
                break;
                
            case 'assignment':
                $suggestions = [
                    "Remove the unused variable assignment for '\${$varName}'",
                    "Use the variable '\${$varName}' somewhere in the code",
                    "Consider if the assignment has side effects that are needed"
                ];
                break;
                
            default:
                $suggestions = [
                    "Remove the unused variable '\${$varName}'",
                    "Use the variable '\${$varName}' in your code"
                ];
        }
        
        return $suggestions;
    }
}