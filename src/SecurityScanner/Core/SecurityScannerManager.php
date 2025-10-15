<?php
namespace YC\CodeAnalysis\SecurityScanner\Core;

use YC\CodeAnalysis\SecurityScanner\Composer\ComposerDependencyScanner;
use YC\CodeAnalysis\SecurityScanner\Vulnerability\VulnerabilityDatabaseManager;
use YC\CodeAnalysis\SecurityScanner\Database\DatabaseSecurityScanner;
use YC\CodeAnalysis\SecurityScanner\License\LicenseComplianceScanner;
use YC\CodeAnalysis\SecurityScanner\Supply\SupplyChainScanner;
use YC\CodeAnalysis\SecurityScanner\Risk\SecurityRiskAssessment;
use YC\CodeAnalysis\Core\Database\DatabaseManager;
use Psr\Log\LoggerInterface;

/**
 * 安全扫描器管理器
 * 
 * 协调和管理所有安全扫描组件，提供统一的扫描接口
 */
class SecurityScannerManager
{
    private LoggerInterface $logger;
    private DatabaseManager $database;
    
    // 扫描器组件
    private ComposerDependencyScanner $dependencyScanner;
    private VulnerabilityDatabaseManager $vulnerabilityManager;
    private DatabaseSecurityScanner $databaseScanner;
    private LicenseComplianceScanner $licenseScanner;
    private SupplyChainScanner $supplyChainScanner;
    private SecurityRiskAssessment $riskAssessment;
    
    // 扫描配置
    private array $scanConfig = [
        'enable_dependency_scan' => true,
        'enable_vulnerability_scan' => true,
        'enable_database_scan' => true,
        'enable_license_scan' => true,
        'enable_supply_chain_scan' => true,
        'parallel_scanning' => true,
        'max_scan_time' => 1800, // 30分钟
        'output_format' => 'json'
    ];

    public function __construct(
        LoggerInterface $logger,
        DatabaseManager $database,
        ComposerDependencyScanner $dependencyScanner,
        VulnerabilityDatabaseManager $vulnerabilityManager,
        DatabaseSecurityScanner $databaseScanner,
        LicenseComplianceScanner $licenseScanner,
        SupplyChainScanner $supplyChainScanner,
        SecurityRiskAssessment $riskAssessment
    ) {
        $this->logger = $logger;
        $this->database = $database;
        $this->dependencyScanner = $dependencyScanner;
        $this->vulnerabilityManager = $vulnerabilityManager;
        $this->databaseScanner = $databaseScanner;
        $this->licenseScanner = $licenseScanner;
        $this->supplyChainScanner = $supplyChainScanner;
        $this->riskAssessment = $riskAssessment;
    }

    /**
     * 执行完整的安全扫描
     */
    public function scanProject(string $projectPath, array $options = []): array
    {
        $config = array_merge($this->scanConfig, $options);
        $this->logger->info('开始安全扫描', ['project' => $projectPath, 'config' => $config]);

        $scanId = $this->generateScanId();
        $startTime = microtime(true);
        
        try {
            // 记录扫描开始
            $this->recordScanStart($scanId, $projectPath, $config);
            
            // 执行各模块扫描
            $scanResults = $this->executeScanModules($projectPath, $config);
            
            // 执行风险评估
            $riskAssessment = $this->executeRiskAssessment($projectPath, $scanResults);
            
            // 生成最终报告
            $finalReport = $this->generateFinalReport($scanId, $projectPath, $scanResults, $riskAssessment, $startTime);
            
            // 记录扫描完成
            $this->recordScanCompletion($scanId, $finalReport);
            
            $this->logger->info('安全扫描完成', [
                'scan_id' => $scanId,
                'duration' => $finalReport['scan_info']['duration'],
                'total_issues' => $finalReport['summary']['total_issues']
            ]);
            
            return $finalReport;
            
        } catch (\Exception $e) {
            $this->logger->error('安全扫描失败', [
                'scan_id' => $scanId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->recordScanFailure($scanId, $e);
            throw $e;
        }
    }

    /**
     * 执行扫描模块
     */
    private function executeScanModules(string $projectPath, array $config): array
    {
        $results = [];
        $scanTasks = $this->prepareScanTasks($projectPath, $config);
        
        if ($config['parallel_scanning'] && extension_loaded('pcntl')) {
            $results = $this->executeParallelScanning($scanTasks);
        } else {
            $results = $this->executeSequentialScanning($scanTasks);
        }
        
        return $results;
    }

    /**
     * 准备扫描任务
     */
    private function prepareScanTasks(string $projectPath, array $config): array
    {
        $tasks = [];
        
        if ($config['enable_dependency_scan']) {
            $tasks['dependency'] = [
                'scanner' => $this->dependencyScanner,
                'method' => 'scan',
                'args' => [$projectPath]
            ];
        }
        
        if ($config['enable_database_scan']) {
            $tasks['database'] = [
                'scanner' => $this->databaseScanner,
                'method' => 'scan',
                'args' => [$projectPath]
            ];
        }
        
        if ($config['enable_license_scan']) {
            $tasks['license'] = [
                'scanner' => $this->licenseScanner,
                'method' => 'scan',
                'args' => [$projectPath]
            ];
        }
        
        if ($config['enable_supply_chain_scan']) {
            $tasks['supply_chain'] = [
                'scanner' => $this->supplyChainScanner,
                'method' => 'scan',
                'args' => [$projectPath]
            ];
        }
        
        return $tasks;
    }

    /**
     * 并行执行扫描
     */
    private function executeParallelScanning(array $tasks): array
    {
        $results = [];
        $processes = [];
        
        foreach ($tasks as $taskName => $task) {
            $pid = pcntl_fork();
            
            if ($pid === -1) {
                // Fork失败，回退到串行执行
                $this->logger->warning("Fork失败，回退到串行扫描");
                return $this->executeSequentialScanning($tasks);
            } elseif ($pid === 0) {
                // 子进程
                try {
                    $result = call_user_func_array(
                        [$task['scanner'], $task['method']], 
                        $task['args']
                    );
                    
                    // 将结果写入临时文件
                    $tempFile = sys_get_temp_dir() . "/scan_result_{$taskName}_" . getmypid();
                    file_put_contents($tempFile, serialize($result));
                    exit(0);
                } catch (\Exception $e) {
                    $this->logger->error("子进程扫描失败: {$taskName}", ['error' => $e->getMessage()]);
                    exit(1);
                }
            } else {
                // 父进程
                $processes[$taskName] = $pid;
            }
        }
        
        // 等待所有子进程完成
        foreach ($processes as $taskName => $pid) {
            $status = 0;
            pcntl_waitpid($pid, $status);
            
            if (pcntl_wexitstatus($status) === 0) {
                $tempFile = sys_get_temp_dir() . "/scan_result_{$taskName}_{$pid}";
                if (file_exists($tempFile)) {
                    $results[$taskName] = unserialize(file_get_contents($tempFile));
                    unlink($tempFile);
                } else {
                    $this->logger->error("扫描结果文件不存在: {$taskName}");
                    $results[$taskName] = null;
                }
            } else {
                $this->logger->error("子进程异常退出: {$taskName}");
                $results[$taskName] = null;
            }
        }
        
        return $results;
    }

    /**
     * 串行执行扫描
     */
    private function executeSequentialScanning(array $tasks): array
    {
        $results = [];
        
        foreach ($tasks as $taskName => $task) {
            $this->logger->info("开始{$taskName}扫描");
            $startTime = microtime(true);
            
            try {
                $results[$taskName] = call_user_func_array(
                    [$task['scanner'], $task['method']], 
                    $task['args']
                );
                
                $duration = microtime(true) - $startTime;
                $this->logger->info("{$taskName}扫描完成", ['duration' => round($duration, 2)]);
                
            } catch (\Exception $e) {
                $this->logger->error("{$taskName}扫描失败", ['error' => $e->getMessage()]);
                $results[$taskName] = [
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }
        
        return $results;
    }

    /**
     * 执行风险评估
     */
    private function executeRiskAssessment(string $projectPath, array $scanResults): array
    {
        $this->logger->info('开始风险评估');
        
        // 检查依赖项漏洞
        if (isset($scanResults['dependency']['packages'])) {
            $scanResults['vulnerability'] = $this->checkDependencyVulnerabilities($scanResults['dependency']['packages']);
        }
        
        return $this->riskAssessment->assessProject($projectPath, $scanResults);
    }

    /**
     * 检查依赖项漏洞
     */
    private function checkDependencyVulnerabilities(array $packages): array
    {
        $vulnerabilities = [];
        
        foreach ($packages as $package) {
            $packageName = $package['name'];
            $version = $package['installed_version'] ?? $package['version'] ?? null;
            
            if ($packageName && $version) {
                $packageVulns = $this->vulnerabilityManager->findVulnerabilities($packageName, $version);
                if (!empty($packageVulns)) {
                    $vulnerabilities[$packageName] = $packageVulns;
                }
            }
        }
        
        return $vulnerabilities;
    }

    /**
     * 生成最终报告
     */
    private function generateFinalReport(
        string $scanId, 
        string $projectPath, 
        array $scanResults, 
        array $riskAssessment, 
        float $startTime
    ): array {
        $duration = microtime(true) - $startTime;
        
        // 统计总问题数
        $totalIssues = $this->countTotalIssues($scanResults);
        
        // 生成摘要
        $summary = $this->generateScanSummary($scanResults, $riskAssessment);
        
        return [
            'scan_info' => [
                'scan_id' => $scanId,
                'project_path' => $projectPath,
                'scan_time' => date('Y-m-d H:i:s'),
                'duration' => round($duration, 2),
                'scanner_version' => '1.0.0'
            ],
            'summary' => array_merge($summary, [
                'total_issues' => $totalIssues,
                'scan_modules' => array_keys($scanResults)
            ]),
            'scan_results' => $scanResults,
            'risk_assessment' => $riskAssessment,
            'recommendations' => $this->generateRecommendations($scanResults, $riskAssessment),
            'compliance_status' => $this->generateComplianceStatus($scanResults),
            'metrics' => $this->generateMetrics($scanResults, $riskAssessment)
        ];
    }

    /**
     * 统计总问题数
     */
    private function countTotalIssues(array $scanResults): int
    {
        $total = 0;
        
        foreach ($scanResults as $moduleResult) {
            if (is_array($moduleResult)) {
                if (isset($moduleResult['issues'])) {
                    if (is_array($moduleResult['issues'])) {
                        foreach ($moduleResult['issues'] as $fileIssues) {
                            $total += is_array($fileIssues) ? count($fileIssues) : 0;
                        }
                    } else {
                        $total += (int)$moduleResult['issues'];
                    }
                } elseif (isset($moduleResult['summary']['total_issues'])) {
                    $total += (int)$moduleResult['summary']['total_issues'];
                }
            }
        }
        
        return $total;
    }

    /**
     * 生成扫描摘要
     */
    private function generateScanSummary(array $scanResults, array $riskAssessment): array
    {
        $summary = [
            'overall_risk_level' => $riskAssessment['risk_assessment']['level'] ?? 'unknown',
            'security_score' => $riskAssessment['risk_assessment']['score'] ?? 0,
            'modules_executed' => count($scanResults),
            'modules_successful' => 0,
            'critical_issues' => 0,
            'high_issues' => 0,
            'medium_issues' => 0,
            'low_issues' => 0
        ];
        
        // 统计执行成功的模块
        foreach ($scanResults as $result) {
            if (is_array($result) && (!isset($result['success']) || $result['success'] !== false)) {
                $summary['modules_successful']++;
            }
        }
        
        // 统计各级别问题数量（从风险评估中获取）
        if (isset($riskAssessment['risk_assessment']['dimensions'])) {
            foreach ($riskAssessment['risk_assessment']['dimensions'] as $dimension) {
                $issues = $dimension['issues'] ?? [];
                foreach ($issues as $issue) {
                    $severity = $issue['severity'] ?? 'info';
                    switch ($severity) {
                        case 'critical':
                            $summary['critical_issues']++;
                            break;
                        case 'high':
                            $summary['high_issues']++;
                            break;
                        case 'medium':
                            $summary['medium_issues']++;
                            break;
                        case 'low':
                            $summary['low_issues']++;
                            break;
                    }
                }
            }
        }
        
        return $summary;
    }

    /**
     * 生成建议
     */
    private function generateRecommendations(array $scanResults, array $riskAssessment): array
    {
        $recommendations = [];
        
        // 从风险评估中获取建议
        if (isset($riskAssessment['risk_assessment']['recommendations'])) {
            $recommendations = array_merge($recommendations, $riskAssessment['risk_assessment']['recommendations']);
        }
        
        // 添加模块特定建议
        foreach ($scanResults as $module => $result) {
            if (isset($result['recommendations'])) {
                $recommendations = array_merge($recommendations, $result['recommendations']);
            }
        }
        
        return $recommendations;
    }

    /**
     * 生成合规状态
     */
    private function generateComplianceStatus(array $scanResults): array
    {
        $compliance = [
            'overall_status' => 'compliant',
            'standards' => []
        ];
        
        if (isset($scanResults['license']['compliance'])) {
            $compliance['standards']['license'] = $scanResults['license']['compliance'];
        }
        
        if (isset($scanResults['supply_chain']['compliance'])) {
            $compliance['standards']['supply_chain'] = $scanResults['supply_chain']['compliance'];
        }
        
        // 确定整体合规状态
        foreach ($compliance['standards'] as $standard) {
            if (isset($standard['status']) && $standard['status'] !== 'compliant') {
                $compliance['overall_status'] = 'non_compliant';
                break;
            }
        }
        
        return $compliance;
    }

    /**
     * 生成指标
     */
    private function generateMetrics(array $scanResults, array $riskAssessment): array
    {
        $metrics = [
            'scan_coverage' => $this->calculateScanCoverage($scanResults),
            'vulnerability_metrics' => $this->extractVulnerabilityMetrics($scanResults),
            'dependency_metrics' => $this->extractDependencyMetrics($scanResults),
            'database_security_metrics' => $this->extractDatabaseMetrics($scanResults)
        ];
        
        // 添加风险评估指标
        if (isset($riskAssessment['risk_assessment']['metrics'])) {
            $metrics['risk_metrics'] = $riskAssessment['risk_assessment']['metrics'];
        }
        
        return $metrics;
    }

    /**
     * 计算扫描覆盖率
     */
    private function calculateScanCoverage(array $scanResults): array
    {
        $totalModules = count($this->scanConfig);
        $executedModules = count($scanResults);
        $successfulModules = 0;
        
        foreach ($scanResults as $result) {
            if (is_array($result) && (!isset($result['success']) || $result['success'] !== false)) {
                $successfulModules++;
            }
        }
        
        return [
            'total_modules' => $totalModules,
            'executed_modules' => $executedModules,
            'successful_modules' => $successfulModules,
            'coverage_percentage' => $totalModules > 0 ? round(($executedModules / $totalModules) * 100, 1) : 0,
            'success_rate' => $executedModules > 0 ? round(($successfulModules / $executedModules) * 100, 1) : 0
        ];
    }

    /**
     * 提取漏洞指标
     */
    private function extractVulnerabilityMetrics(array $scanResults): array
    {
        if (!isset($scanResults['vulnerability'])) {
            return ['total' => 0, 'by_severity' => []];
        }
        
        $vulns = $scanResults['vulnerability'];
        $total = 0;
        $bySeverity = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        
        foreach ($vulns as $packageVulns) {
            if (is_array($packageVulns)) {
                $total += count($packageVulns);
                foreach ($packageVulns as $vuln) {
                    $severity = $vuln['severity'] ?? 'info';
                    if (isset($bySeverity[$severity])) {
                        $bySeverity[$severity]++;
                    }
                }
            }
        }
        
        return [
            'total' => $total,
            'by_severity' => $bySeverity,
            'packages_affected' => count($vulns)
        ];
    }

    /**
     * 提取依赖项指标
     */
    private function extractDependencyMetrics(array $scanResults): array
    {
        if (!isset($scanResults['dependency']['metrics'])) {
            return [];
        }
        
        return $scanResults['dependency']['metrics'];
    }

    /**
     * 提取数据库指标
     */
    private function extractDatabaseMetrics(array $scanResults): array
    {
        if (!isset($scanResults['database']['security_metrics'])) {
            return [];
        }
        
        return $scanResults['database']['security_metrics'];
    }

    /**
     * 生成扫描ID
     */
    private function generateScanId(): string
    {
        return 'scan_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * 记录扫描开始
     */
    private function recordScanStart(string $scanId, string $projectPath, array $config): void
    {
        $sql = "INSERT INTO security_scans (scan_id, project_path, config, status, created_at) VALUES (?, ?, ?, 'running', NOW())";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$scanId, $projectPath, json_encode($config)]);
    }

    /**
     * 记录扫描完成
     */
    private function recordScanCompletion(string $scanId, array $report): void
    {
        $sql = "UPDATE security_scans SET status = 'completed', report = ?, completed_at = NOW() WHERE scan_id = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([json_encode($report), $scanId]);
    }

    /**
     * 记录扫描失败
     */
    private function recordScanFailure(string $scanId, \Exception $e): void
    {
        $sql = "UPDATE security_scans SET status = 'failed', error_message = ?, completed_at = NOW() WHERE scan_id = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$e->getMessage(), $scanId]);
    }

    /**
     * 设置扫描配置
     */
    public function setConfig(array $config): void
    {
        $this->scanConfig = array_merge($this->scanConfig, $config);
    }

    /**
     * 获取扫描配置
     */
    public function getConfig(): array
    {
        return $this->scanConfig;
    }

    /**
     * 更新漏洞数据库
     */
    public function updateVulnerabilityDatabase(): array
    {
        return $this->vulnerabilityManager->updateAllDatabases();
    }

    /**
     * 获取扫描历史
     */
    public function getScanHistory(int $limit = 10): array
    {
        $sql = "SELECT scan_id, project_path, status, created_at, completed_at FROM security_scans ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 获取特定扫描报告
     */
    public function getScanReport(string $scanId): ?array
    {
        $sql = "SELECT report FROM security_scans WHERE scan_id = ?";
        $stmt = $this->database->prepare($sql);
        $stmt->execute([$scanId]);
        $result = $stmt->fetchColumn();
        
        return $result ? json_decode($result, true) : null;
    }
}