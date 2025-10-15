<?php
namespace YC\CodeAnalysis\SecurityScanner\Database;

use PhpParser\Node;

/**
 * SQL注入检测器
 * 
 * 专门用于检测各种SQL注入漏洞
 */
class SQLInjectionDetector
{
    // 危险的SQL关键词
    private array $dangerousKeywords = [
        'union', 'select', 'insert', 'update', 'delete', 'drop', 'create',
        'alter', 'exec', 'execute', 'sp_', 'xp_', 'information_schema'
    ];

    // 常见的注入模式
    private array $injectionPatterns = [
        '/(\s|^)(union\s+select)/i',
        '/(\s|^)(select\s+.*\s+from)/i',
        '/(\s|^)(drop\s+table)/i',
        '/(\s|^)(delete\s+from)/i',
        '/(\s|^)(insert\s+into)/i',
        '/(\s|^)(update\s+.*\s+set)/i',
        '/(\'|\")(\s*)(or|and)(\s*)(\'|\")/i',
        '/(\'|\")(\s*)(\d+)(\s*)(=)(\s*)(\d+)(\s*)(\'|\")/i',
        '/(\s|^)(exec\()/i',
        '/(\s|^)(execute\()/i'
    ];

    // 不安全的PHP函数
    private array $unsafeFunctions = [
        'mysql_query', 'mysql_real_escape_string', 'addslashes'
    ];

    /**
     * 检测方法调用中的SQL注入
     */
    public function detectInMethodCall(Node\Expr\MethodCall $node, array $args): array
    {
        $issues = [];
        $methodName = $this->getMethodName($node);

        if (!$methodName) {
            return $issues;
        }

        // 检查每个参数
        foreach ($args as $index => $arg) {
            if ($arg->value instanceof Node\Scalar\String_) {
                // 静态字符串SQL
                $sqlString = $arg->value->value;
                $staticIssues = $this->detectInStaticSQL($sqlString, $methodName);
                $issues = array_merge($issues, $staticIssues);
            } elseif ($this->isConcatenatedString($arg->value)) {
                // 字符串拼接
                $concatenationIssues = $this->detectInConcatenation($arg->value, $methodName);
                $issues = array_merge($issues, $concatenationIssues);
            } elseif ($this->isVariableWithoutValidation($arg->value)) {
                // 未验证的变量
                $variableIssues = $this->detectInVariable($arg->value, $methodName);
                $issues = array_merge($issues, $variableIssues);
            }
        }

        // 检查预处理语句使用
        if ($methodName === 'prepare') {
            $prepareIssues = $this->checkPreparedStatement($args);
            $issues = array_merge($issues, $prepareIssues);
        }

        return $issues;
    }

    /**
     * 检测原始SQL中的注入
     */
    public function detectRawSQL(array $args): array
    {
        $issues = [];

        foreach ($args as $arg) {
            if ($arg->value instanceof Node\Scalar\String_) {
                $sqlString = $arg->value->value;
                $rawIssues = $this->analyzeRawSQL($sqlString);
                $issues = array_merge($issues, $rawIssues);
            } elseif ($this->isConcatenatedString($arg->value)) {
                $issues[] = [
                    'type' => 'sql_injection_concatenation',
                    'severity' => 'critical',
                    'message' => '原始SQL查询使用了字符串拼接，存在SQL注入风险',
                    'suggestion' => '使用参数化查询或绑定参数'
                ];
            }
        }

        return $issues;
    }

    /**
     * 检测字符串中的SQL注入
     */
    public function detectInString(Node\Arg $arg): array
    {
        $issues = [];

        if ($arg->value instanceof Node\Scalar\String_) {
            $sqlString = $arg->value->value;
            $issues = $this->analyzeRawSQL($sqlString);
        } elseif ($this->isConcatenatedString($arg->value)) {
            $issues[] = [
                'type' => 'sql_injection_concatenation',
                'severity' => 'critical',
                'message' => 'SQL查询使用字符串拼接构建，存在注入风险',
                'suggestion' => '使用参数化查询替代字符串拼接'
            ];
        }

        return $issues;
    }

    /**
     * 分析静态SQL字符串
     */
    private function detectInStaticSQL(string $sql, string $method): array
    {
        $issues = [];

        // 检查是否包含变量插值
        if (strpos($sql, '$') !== false || strpos($sql, '{') !== false) {
            $issues[] = [
                'type' => 'sql_injection_interpolation',
                'severity' => 'critical',
                'message' => 'SQL查询中包含变量插值，存在注入风险',
                'suggestion' => '使用参数绑定替代变量插值',
                'method' => $method,
                'sql_snippet' => $this->truncateSQL($sql)
            ];
        }

        // 检查危险模式
        foreach ($this->injectionPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                $issues[] = [
                    'type' => 'sql_injection_pattern',
                    'severity' => 'high',
                    'message' => 'SQL查询包含可疑的注入模式',
                    'suggestion' => '检查SQL语句的安全性，使用参数化查询',
                    'method' => $method,
                    'pattern' => $pattern,
                    'sql_snippet' => $this->truncateSQL($sql)
                ];
            }
        }

        return $issues;
    }

    /**
     * 检测字符串拼接中的注入
     */
    private function detectInConcatenation(Node $node, string $method): array
    {
        $issues = [];
        $riskLevel = $this->analyzeConcatenationRisk($node);

        if ($riskLevel > 0) {
            $severity = $riskLevel >= 3 ? 'critical' : ($riskLevel >= 2 ? 'high' : 'medium');
            
            $issues[] = [
                'type' => 'sql_injection_concatenation',
                'severity' => $severity,
                'message' => 'SQL查询使用字符串拼接构建，存在注入风险',
                'suggestion' => '使用参数化查询或绑定参数替代字符串拼接',
                'method' => $method,
                'risk_factors' => $this->getConcatenationRiskFactors($node)
            ];
        }

        return $issues;
    }

    /**
     * 分析拼接风险等级
     */
    private function analyzeConcatenationRisk(Node $node): int
    {
        $risk = 0;

        // 递归分析拼接表达式
        if ($node instanceof Node\Expr\BinaryOp\Concat) {
            $risk += $this->analyzeConcatenationRisk($node->left);
            $risk += $this->analyzeConcatenationRisk($node->right);
        } elseif ($node instanceof Node\Expr\Variable) {
            // 变量拼接增加风险
            $risk += 2;
        } elseif ($node instanceof Node\Expr\ArrayDimFetch) {
            // 数组访问（如$_GET, $_POST）高风险
            $arrayVar = $this->getVariableName($node->var);
            if (in_array($arrayVar, ['_GET', '_POST', '_REQUEST', '_COOKIE'])) {
                $risk += 3;
            } else {
                $risk += 2;
            }
        } elseif ($node instanceof Node\Expr\FuncCall) {
            // 函数调用
            $funcName = $this->getFunctionName($node);
            if (in_array($funcName, $this->unsafeFunctions)) {
                $risk += 2;
            } else {
                $risk += 1;
            }
        }

        return $risk;
    }

    /**
     * 获取拼接风险因素
     */
    private function getConcatenationRiskFactors(Node $node): array
    {
        $factors = [];

        if ($node instanceof Node\Expr\BinaryOp\Concat) {
            $factors = array_merge(
                $factors,
                $this->getConcatenationRiskFactors($node->left),
                $this->getConcatenationRiskFactors($node->right)
            );
        } elseif ($node instanceof Node\Expr\ArrayDimFetch) {
            $arrayVar = $this->getVariableName($node->var);
            if (in_array($arrayVar, ['_GET', '_POST', '_REQUEST', '_COOKIE'])) {
                $factors[] = "使用了超全局变量 \${$arrayVar}";
            }
        } elseif ($node instanceof Node\Expr\Variable) {
            $varName = $this->getVariableName($node);
            $factors[] = "包含变量 \${$varName}";
        }

        return array_unique($factors);
    }

    /**
     * 检测未验证的变量
     */
    private function detectInVariable(Node $node, string $method): array
    {
        $issues = [];

        if ($node instanceof Node\Expr\ArrayDimFetch) {
            $arrayVar = $this->getVariableName($node->var);
            if (in_array($arrayVar, ['_GET', '_POST', '_REQUEST', '_COOKIE'])) {
                $issues[] = [
                    'type' => 'sql_injection_user_input',
                    'severity' => 'critical',
                    'message' => "直接使用用户输入 \${$arrayVar} 构建SQL查询",
                    'suggestion' => '对用户输入进行验证和过滤，使用参数化查询',
                    'method' => $method,
                    'input_source' => $arrayVar
                ];
            }
        } elseif ($node instanceof Node\Expr\Variable) {
            $varName = $this->getVariableName($node);
            $issues[] = [
                'type' => 'sql_injection_variable',
                'severity' => 'medium',
                'message' => "变量 \${$varName} 直接用于SQL查询可能存在风险",
                'suggestion' => '确保变量经过适当验证，考虑使用参数化查询',
                'method' => $method,
                'variable' => $varName
            ];
        }

        return $issues;
    }

    /**
     * 检查预处理语句
     */
    private function checkPreparedStatement(array $args): array
    {
        $issues = [];

        if (empty($args)) {
            return $issues;
        }

        $sqlArg = $args[0];
        if ($sqlArg->value instanceof Node\Scalar\String_) {
            $sql = $sqlArg->value->value;
            
            // 检查预处理语句是否正确使用占位符
            $placeholderCount = substr_count($sql, '?') + substr_count($sql, ':');
            
            if ($placeholderCount === 0) {
                $issues[] = [
                    'type' => 'prepared_statement_no_placeholders',
                    'severity' => 'medium',
                    'message' => '预处理语句没有使用占位符',
                    'suggestion' => '使用 ? 或命名参数作为占位符',
                    'sql_snippet' => $this->truncateSQL($sql)
                ];
            }

            // 检查是否仍然包含字符串拼接
            if (strpos($sql, '$') !== false) {
                $issues[] = [
                    'type' => 'prepared_statement_interpolation',
                    'severity' => 'high',
                    'message' => '预处理语句中仍然包含变量插值',
                    'suggestion' => '移除变量插值，使用参数绑定',
                    'sql_snippet' => $this->truncateSQL($sql)
                ];
            }
        }

        return $issues;
    }

    /**
     * 分析原始SQL
     */
    private function analyzeRawSQL(string $sql): array
    {
        $issues = [];

        // 检查危险关键词
        $foundKeywords = [];
        foreach ($this->dangerousKeywords as $keyword) {
            if (stripos($sql, $keyword) !== false) {
                $foundKeywords[] = $keyword;
            }
        }

        if (!empty($foundKeywords)) {
            $issues[] = [
                'type' => 'sql_dangerous_keywords',
                'severity' => 'medium',
                'message' => '原始SQL包含潜在危险的关键词',
                'suggestion' => '检查SQL语句的必要性和安全性',
                'keywords' => $foundKeywords,
                'sql_snippet' => $this->truncateSQL($sql)
            ];
        }

        // 检查注入模式
        foreach ($this->injectionPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                $issues[] = [
                    'type' => 'sql_injection_pattern',
                    'severity' => 'high',
                    'message' => '原始SQL包含可疑的注入模式',
                    'suggestion' => '使用参数化查询替代原始SQL',
                    'sql_snippet' => $this->truncateSQL($sql)
                ];
                break; // 避免重复报告
            }
        }

        return $issues;
    }

    /**
     * 检查是否是字符串拼接
     */
    private function isConcatenatedString(Node $node): bool
    {
        return $node instanceof Node\Expr\BinaryOp\Concat;
    }

    /**
     * 检查是否是未验证的变量
     */
    private function isVariableWithoutValidation(Node $node): bool
    {
        return $node instanceof Node\Expr\Variable || 
               $node instanceof Node\Expr\ArrayDimFetch;
    }

    /**
     * 获取方法名
     */
    private function getMethodName($node): ?string
    {
        if ($node->name instanceof Node\Identifier) {
            return $node->name->name;
        }
        return null;
    }

    /**
     * 获取函数名
     */
    private function getFunctionName(Node\Expr\FuncCall $node): ?string
    {
        if ($node->name instanceof Node\Name) {
            return $node->name->toString();
        }
        return null;
    }

    /**
     * 获取变量名
     */
    private function getVariableName($varNode): ?string
    {
        if ($varNode instanceof Node\Expr\Variable && is_string($varNode->name)) {
            return $varNode->name;
        }
        return null;
    }

    /**
     * 截断SQL用于显示
     */
    private function truncateSQL(string $sql, int $length = 100): string
    {
        $sql = trim(preg_replace('/\s+/', ' ', $sql));
        if (strlen($sql) > $length) {
            return substr($sql, 0, $length) . '...';
        }
        return $sql;
    }
}