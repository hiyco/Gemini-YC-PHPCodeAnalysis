<?php
namespace YC\CodeAnalysis\SecurityScanner\CLI;

use YC\CodeAnalysis\SecurityScanner\Core\SecurityScannerManager;
use YC\CodeAnalysis\Core\CLI\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;

/**
 * 安全扫描CLI命令
 * 
 * 提供命令行安全扫描功能
 */
class SecurityScanCommand extends BaseCommand
{
    protected static string $defaultName = 'security:scan';
    protected static string $defaultDescription = '执行项目安全扫描';

    private SecurityScannerManager $scannerManager;

    public function __construct(SecurityScannerManager $scannerManager)
    {
        parent::__construct();
        $this->scannerManager = $scannerManager;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('project-path', InputArgument::OPTIONAL, '项目路径', '.')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, '输出格式 (json|table|summary)', 'table')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, '输出文件路径')
            ->addOption('modules', 'm', InputOption::VALUE_OPTIONAL, '指定扫描模块，逗号分隔 (dependency,database,license,supply-chain)', null)
            ->addOption('parallel', 'p', InputOption::VALUE_NONE, '启用并行扫描')
            ->addOption('no-cache', null, InputOption::VALUE_NONE, '禁用缓存')
            ->addOption('severity', 's', InputOption::VALUE_OPTIONAL, '最低严重程度 (info|low|medium|high|critical)', 'info')
            ->addOption('exclude', null, InputOption::VALUE_OPTIONAL, '排除路径模式，逗号分隔')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, '配置文件路径')
            ->addOption('update-db', null, InputOption::VALUE_NONE, '更新漏洞数据库')
            ->addOption('quiet', 'q', InputOption::VALUE_NONE, '静默模式')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, '详细输出')
            ->setHelp('
安全扫描工具，支持以下功能：

<info>基本用法:</info>
  <comment>php analyzer security:scan /path/to/project</comment>

<info>指定扫描模块:</info>
  <comment>php analyzer security:scan --modules=dependency,database</comment>

<info>输出为JSON格式:</info>
  <comment>php analyzer security:scan --format=json --output=report.json</comment>

<info>并行扫描:</info>
  <comment>php analyzer security:scan --parallel</comment>

<info>更新漏洞数据库:</info>
  <comment>php analyzer security:scan --update-db</comment>

<info>扫描模块说明:</info>
  • dependency: 依赖项分析和漏洞检测
  • database: 数据库安全扫描
  • license: 开源许可证合规检查
  • supply-chain: 供应链安全分析
');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectPath = $input->getArgument('project-path');
        $format = $input->getOption('format');
        $outputFile = $input->getOption('output');
        $quiet = $input->getOption('quiet');
        $verbose = $input->getOption('verbose');

        // 验证项目路径
        if (!is_dir($projectPath)) {
            $output->writeln("<error>项目路径不存在: {$projectPath}</error>");
            return self::FAILURE;
        }

        // 处理更新数据库选项
        if ($input->getOption('update-db')) {
            return $this->updateVulnerabilityDatabase($output);
        }

        // 加载配置
        $config = $this->loadConfig($input);
        $this->scannerManager->setConfig($config);

        if (!$quiet) {
            $output->writeln('<info>开始安全扫描...</info>');
            $output->writeln("项目路径: <comment>{$projectPath}</comment>");
            
            if ($verbose) {
                $this->displayConfig($output, $config);
            }
        }

        try {
            // 创建进度条
            $progressBar = null;
            if (!$quiet && $format === 'table') {
                $progressBar = new ProgressBar($output, 100);
                $progressBar->setFormat('扫描进度: %bar% %percent:3s%% (%message%)');
                $progressBar->setMessage('准备中...');
                $progressBar->start();
            }

            // 执行扫描
            $report = $this->scannerManager->scanProject($projectPath, $config);

            if ($progressBar) {
                $progressBar->setMessage('扫描完成');
                $progressBar->finish();
                $output->writeln('');
            }

            // 输出结果
            $this->outputResults($output, $report, $format, $outputFile, $quiet);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>扫描失败: {$e->getMessage()}</error>");
            
            if ($verbose) {
                $output->writeln('<comment>详细错误信息:</comment>');
                $output->writeln($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * 更新漏洞数据库
     */
    private function updateVulnerabilityDatabase(OutputInterface $output): int
    {
        $output->writeln('<info>正在更新漏洞数据库...</info>');

        try {
            $results = $this->scannerManager->updateVulnerabilityDatabase();
            
            $output->writeln('<info>漏洞数据库更新完成</info>');
            
            foreach ($results as $source => $result) {
                if ($result['success']) {
                    $output->writeln("  <comment>{$source}</comment>: {$result['new_vulnerabilities']} 个新漏洞");
                } else {
                    $output->writeln("  <error>{$source}</error>: {$result['error']}");
                }
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>更新失败: {$e->getMessage()}</error>");
            return self::FAILURE;
        }
    }

    /**
     * 加载配置
     */
    private function loadConfig(InputInterface $input): array
    {
        $config = [];

        // 从配置文件加载
        $configFile = $input->getOption('config');
        if ($configFile && file_exists($configFile)) {
            $fileConfig = json_decode(file_get_contents($configFile), true);
            if ($fileConfig) {
                $config = array_merge($config, $fileConfig);
            }
        }

        // 命令行选项覆盖配置文件
        if ($input->getOption('modules')) {
            $modules = explode(',', $input->getOption('modules'));
            $config['enable_dependency_scan'] = in_array('dependency', $modules);
            $config['enable_database_scan'] = in_array('database', $modules);
            $config['enable_license_scan'] = in_array('license', $modules);
            $config['enable_supply_chain_scan'] = in_array('supply-chain', $modules);
        }

        if ($input->getOption('parallel')) {
            $config['parallel_scanning'] = true;
        }

        if ($input->getOption('no-cache')) {
            $config['enable_cache'] = false;
        }

        if ($input->getOption('severity')) {
            $config['min_severity'] = $input->getOption('severity');
        }

        if ($input->getOption('exclude')) {
            $config['exclude_patterns'] = explode(',', $input->getOption('exclude'));
        }

        return $config;
    }

    /**
     * 显示配置信息
     */
    private function displayConfig(OutputInterface $output, array $config): void
    {
        $output->writeln('<comment>扫描配置:</comment>');
        
        $enabledModules = [];
        if ($config['enable_dependency_scan'] ?? true) $enabledModules[] = 'dependency';
        if ($config['enable_database_scan'] ?? true) $enabledModules[] = 'database';
        if ($config['enable_license_scan'] ?? true) $enabledModules[] = 'license';
        if ($config['enable_supply_chain_scan'] ?? true) $enabledModules[] = 'supply-chain';
        
        $output->writeln('  模块: ' . implode(', ', $enabledModules));
        $output->writeln('  并行扫描: ' . (($config['parallel_scanning'] ?? false) ? '启用' : '禁用'));
        $output->writeln('  最低严重程度: ' . ($config['min_severity'] ?? 'info'));
        
        if (isset($config['exclude_patterns'])) {
            $output->writeln('  排除模式: ' . implode(', ', $config['exclude_patterns']));
        }
        
        $output->writeln('');
    }

    /**
     * 输出扫描结果
     */
    private function outputResults(
        OutputInterface $output, 
        array $report, 
        string $format, 
        ?string $outputFile, 
        bool $quiet
    ): void {
        switch ($format) {
            case 'json':
                $this->outputJson($output, $report, $outputFile);
                break;
            case 'summary':
                $this->outputSummary($output, $report, $quiet);
                break;
            case 'table':
            default:
                $this->outputTable($output, $report, $quiet);
                break;
        }
    }

    /**
     * JSON格式输出
     */
    private function outputJson(OutputInterface $output, array $report, ?string $outputFile): void
    {
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($outputFile) {
            file_put_contents($outputFile, $json);
            $output->writeln("<info>报告已保存到: {$outputFile}</info>");
        } else {
            $output->writeln($json);
        }
    }

    /**
     * 摘要格式输出
     */
    private function outputSummary(OutputInterface $output, array $report, bool $quiet): void
    {
        if (!$quiet) {
            $output->writeln('<info>=== 安全扫描摘要 ===</info>');
        }
        
        $summary = $report['summary'];
        $riskLevel = $summary['overall_risk_level'];
        $securityScore = $summary['security_score'];
        
        // 风险等级颜色
        $riskColors = [
            'critical' => 'error',
            'high' => 'comment',
            'medium' => 'question',
            'low' => 'info',
            'minimal' => 'info'
        ];
        $color = $riskColors[$riskLevel] ?? 'info';
        
        $output->writeln("<{$color}>整体风险等级: {$riskLevel}</{$color}>");
        $output->writeln("安全评分: <comment>{$securityScore}/100</comment>");
        $output->writeln("总问题数: <comment>{$summary['total_issues']}</comment>");
        
        if ($summary['critical_issues'] > 0) {
            $output->writeln("  <error>严重: {$summary['critical_issues']}</error>");
        }
        if ($summary['high_issues'] > 0) {
            $output->writeln("  <comment>高危: {$summary['high_issues']}</comment>");
        }
        if ($summary['medium_issues'] > 0) {
            $output->writeln("  <question>中等: {$summary['medium_issues']}</question>");
        }
        if ($summary['low_issues'] > 0) {
            $output->writeln("  <info>低危: {$summary['low_issues']}</info>");
        }
        
        $output->writeln("扫描模块: <comment>" . implode(', ', $summary['scan_modules']) . "</comment>");
        $output->writeln("扫描用时: <comment>{$report['scan_info']['duration']}秒</comment>");
    }

    /**
     * 表格格式输出
     */
    private function outputTable(OutputInterface $output, array $report, bool $quiet): void
    {
        if (!$quiet) {
            $this->outputSummary($output, $report, false);
            $output->writeln('');
        }

        // 显示各模块详细结果
        foreach ($report['scan_results'] as $module => $result) {
            if (!is_array($result) || (isset($result['success']) && $result['success'] === false)) {
                continue;
            }

            $output->writeln("<info>=== {$this->getModuleName($module)} ===</info>");
            $this->displayModuleResults($output, $module, $result);
            $output->writeln('');
        }

        // 显示建议
        if (!empty($report['recommendations']) && !$quiet) {
            $output->writeln('<info>=== 修复建议 ===</info>');
            $this->displayRecommendations($output, $report['recommendations']);
        }
    }

    /**
     * 获取模块显示名称
     */
    private function getModuleName(string $module): string
    {
        $names = [
            'dependency' => '依赖项分析',
            'database' => '数据库安全',
            'license' => '许可证合规',
            'supply_chain' => '供应链安全',
            'vulnerability' => '漏洞分析'
        ];

        return $names[$module] ?? ucfirst($module);
    }

    /**
     * 显示模块结果
     */
    private function displayModuleResults(OutputInterface $output, string $module, array $result): void
    {
        switch ($module) {
            case 'dependency':
                $this->displayDependencyResults($output, $result);
                break;
            case 'database':
                $this->displayDatabaseResults($output, $result);
                break;
            case 'vulnerability':
                $this->displayVulnerabilityResults($output, $result);
                break;
            default:
                $this->displayGenericResults($output, $result);
                break;
        }
    }

    /**
     * 显示依赖项结果
     */
    private function displayDependencyResults(OutputInterface $output, array $result): void
    {
        $metrics = $result['metrics'] ?? [];
        
        $table = new Table($output);
        $table->setHeaders(['指标', '值']);
        $table->addRows([
            ['总包数', $metrics['total_packages'] ?? 0],
            ['直接依赖', $metrics['direct_dependencies'] ?? 0],
            ['最大深度', $metrics['max_depth'] ?? 0],
            ['安全风险评分', $metrics['security_risk_score'] ?? 0],
            ['维护风险评分', $metrics['maintenance_risk_score'] ?? 0]
        ]);
        $table->render();
        
        // 显示兼容性问题
        if (!empty($result['compatibility_issues'])) {
            $output->writeln('<comment>兼容性问题:</comment>');
            foreach (array_slice($result['compatibility_issues'], 0, 5) as $issue) {
                $severity = $issue['severity'] ?? 'info';
                $package = $issue['package'] ?? 'unknown';
                $type = $issue['type'] ?? 'unknown';
                $output->writeln("  <{$severity}>{$package}: {$type}</{$severity}>");
            }
        }
    }

    /**
     * 显示数据库结果
     */
    private function displayDatabaseResults(OutputInterface $output, array $result): void
    {
        $summary = $result['summary'] ?? [];
        
        $table = new Table($output);
        $table->setHeaders(['类别', '问题数']);
        
        $statistics = $summary['statistics']['by_category'] ?? [];
        foreach ($statistics as $category => $count) {
            $table->addRow([$this->getCategoryName($category), $count]);
        }
        $table->render();
        
        $output->writeln("安全评分: <comment>{$summary['security_score'] ?? 0}/100</comment>");
    }

    /**
     * 显示漏洞结果
     */
    private function displayVulnerabilityResults(OutputInterface $output, array $result): void
    {
        $total = 0;
        $bySeverity = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        
        foreach ($result as $packageVulns) {
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
        
        $table = new Table($output);
        $table->setHeaders(['严重程度', '漏洞数']);
        
        foreach ($bySeverity as $severity => $count) {
            if ($count > 0) {
                $table->addRow([ucfirst($severity), $count]);
            }
        }
        $table->render();
        
        $output->writeln("受影响包数: <comment>" . count($result) . "</comment>");
        $output->writeln("总漏洞数: <comment>{$total}</comment>");
    }

    /**
     * 显示通用结果
     */
    private function displayGenericResults(OutputInterface $output, array $result): void
    {
        if (isset($result['summary'])) {
            foreach ($result['summary'] as $key => $value) {
                if (is_scalar($value)) {
                    $output->writeln("{$key}: <comment>{$value}</comment>");
                }
            }
        }
    }

    /**
     * 显示建议
     */
    private function displayRecommendations(OutputInterface $output, array $recommendations): void
    {
        $count = 0;
        foreach ($recommendations as $rec) {
            if (++$count > 10) break; // 限制显示前10个建议
            
            $priority = $rec['priority'] ?? 'medium';
            $action = $rec['action'] ?? $rec['description'] ?? '未指定';
            $dimension = $rec['dimension'] ?? '';
            
            $priorityColor = ['critical' => 'error', 'high' => 'comment', 'medium' => 'question', 'low' => 'info'][$priority] ?? 'info';
            
            $output->writeln("  <{$priorityColor}>[{$priority}]</{$priorityColor}> {$action}");
            if ($dimension) {
                $output->writeln("    <comment>领域: {$dimension}</comment>");
            }
        }
    }

    /**
     * 获取分类显示名称
     */
    private function getCategoryName(string $category): string
    {
        $names = [
            'sql_injection' => 'SQL注入',
            'performance' => '性能问题',
            'sensitive_data' => '敏感数据',
            'connection' => '连接管理',
            'deprecated' => '废弃函数'
        ];

        return $names[$category] ?? ucfirst($category);
    }
}