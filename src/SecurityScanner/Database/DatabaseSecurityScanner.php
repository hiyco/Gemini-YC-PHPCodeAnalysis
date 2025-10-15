<?php
namespace YC\CodeAnalysis\SecurityScanner\Database;

use YC\CodeAnalysis\Core\Contracts\ScannerInterface;
use YC\CodeAnalysis\Core\AST\ASTAnalyzer;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use Psr\Log\LoggerInterface;

/**
 * 数据库安全扫描器
 * 
 * 检测SQL注入、查询性能问题、连接管理、敏感数据保护等数据库安全问题
 */
class DatabaseSecurityScanner implements ScannerInterface, NodeVisitor
{
    private ASTAnalyzer $astAnalyzer;
    private LoggerInterface $logger;
    private SQLInjectionDetector $sqlInjectionDetector;
    private QueryPerformanceAnalyzer $queryAnalyzer;
    private ConnectionAnalyzer $connectionAnalyzer;
    private SensitiveDataDetector $sensitiveDataDetector;
    
    // 扫描结果存储
    private array $issues = [];
    private array $currentFileIssues = [];
    private string $currentFile = '';
    
    // 数据库相关的类和方法
    private array $databaseClasses = [
        'PDO', 'mysqli', 'Doctrine\\DBAL\\Connection', 
        'Illuminate\\Database\\Connection', 'Illuminate\\Support\\Facades\\DB'
    ];
    
    private array $queryMethods = [
        'query', 'exec', 'prepare', 'execute', 'select', 'insert', 
        'update', 'delete', 'raw', 'statement'
    ];

    public function __construct(
        ASTAnalyzer $astAnalyzer,
        LoggerInterface $logger,
        SQLInjectionDetector $sqlInjectionDetector,
        QueryPerformanceAnalyzer $queryAnalyzer,
        ConnectionAnalyzer $connectionAnalyzer,
        SensitiveDataDetector $sensitiveDataDetector
    ) {
        $this->astAnalyzer = $astAnalyzer;
        $this->logger = $logger;
        $this->sqlInjectionDetector = $sqlInjectionDetector;
        $this->queryAnalyzer = $queryAnalyzer;
        $this->connectionAnalyzer = $connectionAnalyzer;
        $this->sensitiveDataDetector = $sensitiveDataDetector;
    }

    /**
     * 扫描项目数据库安全问题
     */
    public function scan(string $projectPath): array
    {
        $this->issues = [];
        $phpFiles = $this->findPHPFiles($projectPath);
        
        $this->logger->info('开始数据库安全扫描', ['files_count' => count($phpFiles)]);
        
        foreach ($phpFiles as $file) {
            $this->scanFile($file);
        }
        
        // 生成扫描报告
        $report = $this->generateReport();
        
        $this->logger->info('数据库安全扫描完成', [
            'total_issues' => count($this->issues),
            'files_scanned' => count($phpFiles)
        ]);
        
        return $report;
    }

    /**
     * 扫描单个文件
     */
    private function scanFile(string $filePath): void
    {
        $this->currentFile = $filePath;
        $this->currentFileIssues = [];
        
        try {
            $code = file_get_contents($filePath);
            if ($code === false) {
                return;
            }
            
            // 解析AST并访问节点
            $this->astAnalyzer->analyzeWithVisitor($code, $this);
            
            // 将当前文件的问题添加到总问题列表
            if (!empty($this->currentFileIssues)) {
                $this->issues[$filePath] = $this->currentFileIssues;
            }
            
        } catch (\Exception $e) {
            $this->logger->error("扫描文件失败: {$filePath}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * AST节点访问开始
     */
    public function beforeTraverse(array $nodes): ?array
    {
        return null;
    }

    /**
     * 访问AST节点
     */
    public function enterNode(Node $node): ?Node
    {
        // 检测方法调用
        if ($node instanceof Node\Expr\MethodCall) {
            $this->analyzeMethodCall($node);
        }
        
        // 检测静态方法调用
        if ($node instanceof Node\Expr\StaticCall) {
            $this->analyzeStaticCall($node);
        }
        
        // 检测函数调用
        if ($node instanceof Node\Expr\FuncCall) {
            $this->analyzeFunctionCall($node);
        }
        
        // 检测类实例化
        if ($node instanceof Node\Expr\New_) {
            $this->analyzeNewInstance($node);
        }
        
        // 检测变量赋值
        if ($node instanceof Node\Expr\Assign) {
            $this->analyzeAssignment($node);
        }
        
        return null;
    }

    /**
     * 访问AST节点结束
     */
    public function leaveNode(Node $node): ?Node
    {
        return null;
    }

    /**
     * AST遍历结束
     */
    public function afterTraverse(array $nodes): ?array
    {
        return null;
    }

    /**
     * 分析方法调用
     */
    private function analyzeMethodCall(Node\Expr\MethodCall $node): void
    {
        $methodName = $this->getMethodName($node);
        if (!$methodName || !in_array($methodName, $this->queryMethods)) {
            return;
        }

        $line = $node->getStartLine();
        $args = $node->args;

        // SQL注入检测
        $sqlInjectionIssues = $this->sqlInjectionDetector->detectInMethodCall($node, $args);
        foreach ($sqlInjectionIssues as $issue) {
            $this->addIssue('sql_injection', $issue, $line);
        }

        // 查询性能分析
        $performanceIssues = $this->queryAnalyzer->analyzeMethodCall($node, $args);
        foreach ($performanceIssues as $issue) {
            $this->addIssue('performance', $issue, $line);
        }

        // 敏感数据检测
        $sensitiveDataIssues = $this->sensitiveDataDetector->detectInQuery($node, $args);
        foreach ($sensitiveDataIssues as $issue) {
            $this->addIssue('sensitive_data', $issue, $line);
        }
    }

    /**
     * 分析静态方法调用
     */
    private function analyzeStaticCall(Node\Expr\StaticCall $node): void
    {
        $className = $this->getClassName($node->class);
        $methodName = $this->getMethodName($node);
        
        if (!$this->isDatabaseRelated($className, $methodName)) {
            return;
        }

        $line = $node->getStartLine();
        $args = $node->args;

        // Laravel DB facade特殊处理
        if ($className === 'DB' || $className === 'Illuminate\\Support\\Facades\\DB') {
            $this->analyzeLaravelDB($node, $args, $line);
        }
    }

    /**
     * 分析Laravel DB静态调用
     */
    private function analyzeLaravelDB(Node\Expr\StaticCall $node, array $args, int $line): void
    {
        $methodName = $this->getMethodName($node);
        
        // 检测原始查询
        if (in_array($methodName, ['raw', 'select', 'statement'])) {
            $rawSqlIssues = $this->sqlInjectionDetector->detectRawSQL($args);
            foreach ($rawSqlIssues as $issue) {
                $this->addIssue('sql_injection', $issue, $line);
            }
        }
        
        // 检测N+1查询问题
        if ($methodName === 'select' && $this->isInLoop($node)) {
            $this->addIssue('performance', [
                'type' => 'n_plus_one_query',
                'message' => '检测到可能的N+1查询问题',
                'suggestion' => '考虑使用预加载(eager loading)或批量查询'
            ], $line);
        }
    }

    /**
     * 分析函数调用
     */
    private function analyzeFunctionCall(Node\Expr\FuncCall $node): void
    {
        $funcName = $this->getFunctionName($node);
        $line = $node->getStartLine();
        $args = $node->args;

        // 检测mysqli函数
        if (strpos($funcName, 'mysqli_') === 0) {
            $this->analyzeMysqliFunction($funcName, $args, $line);
        }
        
        // 检测PDO相关函数
        if (in_array($funcName, ['mysql_query', 'mysql_real_escape_string'])) {
            $this->addIssue('deprecated', [
                'type' => 'deprecated_mysql_function',
                'function' => $funcName,
                'message' => "使用了已废弃的MySQL函数: {$funcName}",
                'suggestion' => '使用PDO或MySQLi替代'
            ], $line);
        }
    }

    /**
     * 分析MySQLi函数调用
     */
    private function analyzeMysqliFunction(string $funcName, array $args, int $line): void
    {
        if ($funcName === 'mysqli_query' && count($args) >= 2) {
            $queryArg = $args[1];
            $sqlInjectionIssues = $this->sqlInjectionDetector->detectInString($queryArg);
            foreach ($sqlInjectionIssues as $issue) {
                $this->addIssue('sql_injection', $issue, $line);
            }
        }
    }

    /**
     * 分析类实例化
     */
    private function analyzeNewInstance(Node\Expr\New_ $node): void
    {
        $className = $this->getClassName($node->class);
        $line = $node->getStartLine();
        
        if ($className === 'PDO') {
            $this->analyzePDOInstance($node, $line);
        }
    }

    /**
     * 分析PDO实例化
     */
    private function analyzePDOInstance(Node\Expr\New_ $node, int $line): void
    {
        $args = $node->args;
        
        // 检查连接配置
        $connectionIssues = $this->connectionAnalyzer->analyzePDOConnection($args);
        foreach ($connectionIssues as $issue) {
            $this->addIssue('connection', $issue, $line);
        }
    }

    /**
     * 分析变量赋值
     */
    private function analyzeAssignment(Node\Expr\Assign $node): void
    {
        // 检测硬编码的数据库凭据
        if ($node->expr instanceof Node\Scalar\String_) {
            $value = $node->expr->value;
            $varName = $this->getVariableName($node->var);
            
            if ($this->isCredentialVariable($varName)) {
                $this->addIssue('security', [
                    'type' => 'hardcoded_credentials',
                    'variable' => $varName,
                    'message' => '检测到硬编码的数据库凭据',
                    'suggestion' => '使用环境变量或配置文件存储敏感信息'
                ], $node->getStartLine());
            }
        }
    }

    /**
     * 添加问题到当前文件
     */
    private function addIssue(string $category, array $issue, int $line): void
    {
        $issue['line'] = $line;
        $issue['file'] = $this->currentFile;
        $issue['category'] = $category;
        
        $this->currentFileIssues[] = $issue;
    }

    /**
     * 生成扫描报告
     */
    private function generateReport(): array
    {
        $totalIssues = 0;
        $issuesByCategory = [];
        $issuesBySeverity = [];
        $fileStatistics = [];

        foreach ($this->issues as $file => $fileIssues) {
            $totalIssues += count($fileIssues);
            $fileStatistics[$file] = [
                'total_issues' => count($fileIssues),
                'categories' => []
            ];

            foreach ($fileIssues as $issue) {
                $category = $issue['category'];
                $severity = $this->calculateIssueSeverity($issue);
                
                // 按类别统计
                if (!isset($issuesByCategory[$category])) {
                    $issuesByCategory[$category] = 0;
                }
                $issuesByCategory[$category]++;
                
                // 按严重程度统计
                if (!isset($issuesBySeverity[$severity])) {
                    $issuesBySeverity[$severity] = 0;
                }
                $issuesBySeverity[$severity]++;
                
                // 文件统计
                if (!isset($fileStatistics[$file]['categories'][$category])) {
                    $fileStatistics[$file]['categories'][$category] = 0;
                }
                $fileStatistics[$file]['categories'][$category]++;
            }
        }

        // 生成安全评分
        $securityScore = $this->calculateSecurityScore($issuesBySeverity, $totalIssues);

        // 生成修复建议
        $fixRecommendations = $this->generateFixRecommendations($issuesByCategory);

        return [
            'summary' => [
                'total_issues' => $totalIssues,
                'files_with_issues' => count($this->issues),
                'security_score' => $securityScore,
                'scan_time' => date('Y-m-d H:i:s')
            ],
            'statistics' => [
                'by_category' => $issuesByCategory,
                'by_severity' => $issuesBySeverity,
                'by_file' => $fileStatistics
            ],
            'issues' => $this->issues,
            'recommendations' => $fixRecommendations,
            'security_metrics' => $this->calculateSecurityMetrics()
        ];
    }

    /**
     * 计算问题严重程度
     */
    private function calculateIssueSeverity(array $issue): string
    {
        $category = $issue['category'];
        $type = $issue['type'] ?? '';

        // SQL注入问题通常是高危险
        if ($category === 'sql_injection') {
            return 'critical';
        }

        // 硬编码凭据是高风险
        if ($type === 'hardcoded_credentials') {
            return 'high';
        }

        // 敏感数据暴露
        if ($category === 'sensitive_data') {
            return 'high';
        }

        // 性能问题通常是中等
        if ($category === 'performance') {
            return 'medium';
        }

        // 连接问题
        if ($category === 'connection') {
            return 'medium';
        }

        // 废弃函数
        if ($category === 'deprecated') {
            return 'low';
        }

        return 'info';
    }

    /**
     * 计算安全评分
     */
    private function calculateSecurityScore(array $issuesBySeverity, int $totalIssues): float
    {
        if ($totalIssues === 0) {
            return 100.0;
        }

        $severityWeights = [
            'critical' => 10,
            'high' => 7,
            'medium' => 4,
            'low' => 2,
            'info' => 1
        ];

        $weightedScore = 0;
        foreach ($issuesBySeverity as $severity => $count) {
            $weight = $severityWeights[$severity] ?? 1;
            $weightedScore += $count * $weight;
        }

        // 基础分数100，根据加权问题数量扣分
        $score = max(0, 100 - ($weightedScore * 2));
        
        return round($score, 1);
    }

    /**
     * 生成修复建议
     */
    private function generateFixRecommendations(array $issuesByCategory): array
    {
        $recommendations = [];

        if (isset($issuesByCategory['sql_injection'])) {
            $recommendations[] = [
                'category' => 'sql_injection',
                'priority' => 'critical',
                'title' => 'SQL注入漏洞修复',
                'description' => '使用参数化查询或预处理语句防止SQL注入攻击',
                'actions' => [
                    '使用PDO的prepare()方法和绑定参数',
                    '避免直接拼接用户输入到SQL语句中',
                    '使用ORM框架提供的安全查询方法',
                    '对用户输入进行严格验证和过滤'
                ]
            ];
        }

        if (isset($issuesByCategory['performance'])) {
            $recommendations[] = [
                'category' => 'performance',
                'priority' => 'medium',
                'title' => '查询性能优化',
                'description' => '优化数据库查询性能，减少不必要的数据库访问',
                'actions' => [
                    '使用索引优化查询性能',
                    '避免N+1查询问题，使用预加载',
                    '合理使用查询缓存',
                    '优化复杂查询的逻辑结构'
                ]
            ];
        }

        if (isset($issuesByCategory['sensitive_data'])) {
            $recommendations[] = [
                'category' => 'sensitive_data',
                'priority' => 'high',
                'title' => '敏感数据保护',
                'description' => '保护敏感数据不被泄露或误用',
                'actions' => [
                    '加密存储敏感数据',
                    '限制敏感数据的查询权限',
                    '使用数据脱敏技术',
                    '记录敏感数据访问日志'
                ]
            ];
        }

        if (isset($issuesByCategory['connection'])) {
            $recommendations[] = [
                'category' => 'connection',
                'priority' => 'medium',
                'title' => '数据库连接优化',
                'description' => '优化数据库连接管理，提高系统稳定性',
                'actions' => [
                    '使用连接池管理数据库连接',
                    '设置合理的连接超时时间',
                    '及时关闭不使用的连接',
                    '配置连接重试机制'
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * 计算安全指标
     */
    private function calculateSecurityMetrics(): array
    {
        $totalFiles = count($this->issues);
        $totalIssues = 0;
        $criticalIssues = 0;
        $highIssues = 0;

        foreach ($this->issues as $fileIssues) {
            $totalIssues += count($fileIssues);
            
            foreach ($fileIssues as $issue) {
                $severity = $this->calculateIssueSeverity($issue);
                if ($severity === 'critical') {
                    $criticalIssues++;
                } elseif ($severity === 'high') {
                    $highIssues++;
                }
            }
        }

        return [
            'risk_level' => $this->calculateRiskLevel($criticalIssues, $highIssues, $totalIssues),
            'vulnerability_density' => $totalFiles > 0 ? round($totalIssues / $totalFiles, 2) : 0,
            'critical_vulnerability_ratio' => $totalIssues > 0 ? round(($criticalIssues / $totalIssues) * 100, 1) : 0,
            'security_debt_hours' => $this->estimateFixTime($criticalIssues, $highIssues, $totalIssues - $criticalIssues - $highIssues)
        ];
    }

    /**
     * 计算风险等级
     */
    private function calculateRiskLevel(int $critical, int $high, int $total): string
    {
        if ($critical > 0) {
            return 'critical';
        }
        
        if ($high > 3) {
            return 'high';
        }
        
        if ($total > 10) {
            return 'medium';
        }
        
        if ($total > 0) {
            return 'low';
        }
        
        return 'minimal';
    }

    /**
     * 估算修复时间
     */
    private function estimateFixTime(int $critical, int $high, int $others): int
    {
        // 估算修复时间（小时）
        return ($critical * 4) + ($high * 2) + ($others * 0.5);
    }

    /**
     * 辅助方法 - 获取方法名
     */
    private function getMethodName($node): ?string
    {
        if ($node->name instanceof Node\Identifier) {
            return $node->name->name;
        }
        return null;
    }

    /**
     * 辅助方法 - 获取类名
     */
    private function getClassName($classNode): ?string
    {
        if ($classNode instanceof Node\Name) {
            return $classNode->toString();
        }
        return null;
    }

    /**
     * 辅助方法 - 获取函数名
     */
    private function getFunctionName(Node\Expr\FuncCall $node): ?string
    {
        if ($node->name instanceof Node\Name) {
            return $node->name->toString();
        }
        return null;
    }

    /**
     * 辅助方法 - 获取变量名
     */
    private function getVariableName($varNode): ?string
    {
        if ($varNode instanceof Node\Expr\Variable && is_string($varNode->name)) {
            return $varNode->name;
        }
        return null;
    }

    /**
     * 检查是否是数据库相关的类和方法
     */
    private function isDatabaseRelated(?string $className, ?string $methodName): bool
    {
        if (!$className || !$methodName) {
            return false;
        }

        foreach ($this->databaseClasses as $dbClass) {
            if (strpos($className, $dbClass) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查是否是凭据相关变量
     */
    private function isCredentialVariable(?string $varName): bool
    {
        if (!$varName) {
            return false;
        }

        $credentialKeywords = ['password', 'passwd', 'pwd', 'secret', 'key', 'token', 'auth'];
        $varLower = strtolower($varName);

        foreach ($credentialKeywords as $keyword) {
            if (strpos($varLower, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查节点是否在循环中
     */
    private function isInLoop(Node $node): bool
    {
        // 简化实现 - 实际需要遍历父节点检查
        // 这里返回false，具体实现需要AST上下文分析
        return false;
    }

    /**
     * 查找PHP文件
     */
    private function findPHPFiles(string $directory): array
    {
        $phpFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $phpFiles[] = $file->getPathname();
            }
        }

        return $phpFiles;
    }
}