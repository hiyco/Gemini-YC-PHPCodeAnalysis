<?php
namespace YC\CodeAnalysis\SecurityScanner\Risk;

use YC\CodeAnalysis\Core\Reports\ReportGenerator;
use Psr\Log\LoggerInterface;

/**
 * 安全风险评估系统
 * 
 * 综合分析开源库漏洞、数据库安全、代码质量等多个维度，
 * 生成全面的安全风险评估报告
 */
class SecurityRiskAssessment
{
    private LoggerInterface $logger;
    private ReportGenerator $reportGenerator;
    private VulnerabilityRiskAnalyzer $vulnerabilityAnalyzer;
    private DatabaseRiskAnalyzer $databaseAnalyzer;
    private SupplyChainRiskAnalyzer $supplyChainAnalyzer;
    private ComplianceAnalyzer $complianceAnalyzer;

    // 风险权重配置
    private array $riskWeights = [
        'vulnerability' => 0.35,      // 漏洞风险
        'database' => 0.25,           // 数据库安全
        'supply_chain' => 0.20,       // 供应链安全
        'compliance' => 0.20          // 合规性
    ];

    // 风险等级阈值
    private array $riskThresholds = [
        'critical' => 90,
        'high' => 70,
        'medium' => 40,
        'low' => 20
    ];

    public function __construct(
        LoggerInterface $logger,
        ReportGenerator $reportGenerator,
        VulnerabilityRiskAnalyzer $vulnerabilityAnalyzer,
        DatabaseRiskAnalyzer $databaseAnalyzer,
        SupplyChainRiskAnalyzer $supplyChainAnalyzer,
        ComplianceAnalyzer $complianceAnalyzer
    ) {
        $this->logger = $logger;
        $this->reportGenerator = $reportGenerator;
        $this->vulnerabilityAnalyzer = $vulnerabilityAnalyzer;
        $this->databaseAnalyzer = $databaseAnalyzer;
        $this->supplyChainAnalyzer = $supplyChainAnalyzer;
        $this->complianceAnalyzer = $complianceAnalyzer;
    }

    /**
     * 执行全面的安全风险评估
     */
    public function assessProject(string $projectPath, array $scanResults): array
    {
        $this->logger->info('开始安全风险评估', ['project_path' => $projectPath]);

        $startTime = microtime(true);

        // 分析各个维度的风险
        $vulnerabilityRisk = $this->assessVulnerabilityRisk($scanResults['vulnerability'] ?? []);
        $databaseRisk = $this->assessDatabaseRisk($scanResults['database'] ?? []);
        $supplyChainRisk = $this->assessSupplyChainRisk($scanResults['dependency'] ?? []);
        $complianceRisk = $this->assessComplianceRisk($scanResults);

        // 计算综合风险评分
        $overallRisk = $this->calculateOverallRisk([
            'vulnerability' => $vulnerabilityRisk,
            'database' => $databaseRisk,
            'supply_chain' => $supplyChainRisk,
            'compliance' => $complianceRisk
        ]);

        // 生成风险报告
        $riskReport = $this->generateRiskReport([
            'project_path' => $projectPath,
            'overall_risk' => $overallRisk,
            'vulnerability_risk' => $vulnerabilityRisk,
            'database_risk' => $databaseRisk,
            'supply_chain_risk' => $supplyChainRisk,
            'compliance_risk' => $complianceRisk,
            'assessment_time' => microtime(true) - $startTime
        ]);

        $this->logger->info('安全风险评估完成', [
            'overall_score' => $overallRisk['score'],
            'risk_level' => $overallRisk['level'],
            'duration' => $riskReport['assessment_time']
        ]);

        return $riskReport;
    }

    /**
     * 评估漏洞风险
     */
    private function assessVulnerabilityRisk(array $vulnerabilityData): array
    {
        return $this->vulnerabilityAnalyzer->analyze($vulnerabilityData);
    }

    /**
     * 评估数据库风险
     */
    private function assessDatabaseRisk(array $databaseData): array
    {
        return $this->databaseAnalyzer->analyze($databaseData);
    }

    /**
     * 评估供应链风险
     */
    private function assessSupplyChainRisk(array $dependencyData): array
    {
        return $this->supplyChainAnalyzer->analyze($dependencyData);
    }

    /**
     * 评估合规风险
     */
    private function assessComplianceRisk(array $scanResults): array
    {
        return $this->complianceAnalyzer->analyze($scanResults);
    }

    /**
     * 计算综合风险评分
     */
    private function calculateOverallRisk(array $riskDimensions): array
    {
        $weightedScore = 0;
        $riskFactors = [];
        $criticalIssues = [];
        $recommendations = [];

        foreach ($riskDimensions as $dimension => $risk) {
            $score = $risk['score'] ?? 0;
            $weight = $this->riskWeights[$dimension] ?? 0;
            
            $weightedScore += $score * $weight;
            
            // 收集风险因素
            if (!empty($risk['factors'])) {
                $riskFactors[$dimension] = $risk['factors'];
            }
            
            // 收集关键问题
            if (!empty($risk['critical_issues'])) {
                $criticalIssues[$dimension] = $risk['critical_issues'];
            }
            
            // 收集建议
            if (!empty($risk['recommendations'])) {
                $recommendations[$dimension] = $risk['recommendations'];
            }
        }

        $riskLevel = $this->determineRiskLevel($weightedScore);

        return [
            'score' => round($weightedScore, 1),
            'level' => $riskLevel,
            'dimensions' => $riskDimensions,
            'risk_factors' => $riskFactors,
            'critical_issues' => $criticalIssues,
            'recommendations' => $this->prioritizeRecommendations($recommendations, $riskLevel),
            'trends' => $this->analyzeRiskTrends($riskDimensions),
            'metrics' => $this->calculateRiskMetrics($riskDimensions)
        ];
    }

    /**
     * 确定风险等级
     */
    private function determineRiskLevel(float $score): string
    {
        if ($score >= $this->riskThresholds['critical']) {
            return 'critical';
        } elseif ($score >= $this->riskThresholds['high']) {
            return 'high';
        } elseif ($score >= $this->riskThresholds['medium']) {
            return 'medium';
        } elseif ($score >= $this->riskThresholds['low']) {
            return 'low';
        } else {
            return 'minimal';
        }
    }

    /**
     * 优先级排序建议
     */
    private function prioritizeRecommendations(array $recommendations, string $riskLevel): array
    {
        $prioritized = [];

        // 根据风险等级调整建议优先级
        foreach ($recommendations as $dimension => $dimRecommendations) {
            foreach ($dimRecommendations as $rec) {
                $priority = $this->calculateRecommendationPriority($rec, $dimension, $riskLevel);
                $rec['priority_score'] = $priority;
                $rec['dimension'] = $dimension;
                $prioritized[] = $rec;
            }
        }

        // 按优先级排序
        usort($prioritized, function ($a, $b) {
            return $b['priority_score'] <=> $a['priority_score'];
        });

        return $prioritized;
    }

    /**
     * 计算建议优先级
     */
    private function calculateRecommendationPriority(array $recommendation, string $dimension, string $riskLevel): int
    {
        $priority = 0;

        // 基于风险等级的基础分数
        $levelScores = [
            'critical' => 100,
            'high' => 80,
            'medium' => 60,
            'low' => 40,
            'minimal' => 20
        ];
        $priority += $levelScores[$riskLevel] ?? 0;

        // 基于维度权重
        $dimensionWeight = $this->riskWeights[$dimension] ?? 0;
        $priority += $dimensionWeight * 50;

        // 基于建议类型
        $type = $recommendation['type'] ?? '';
        $typeScores = [
            'security_fix' => 30,
            'vulnerability_patch' => 25,
            'configuration' => 20,
            'best_practice' => 15,
            'monitoring' => 10
        ];
        $priority += $typeScores[$type] ?? 5;

        // 基于预期影响
        $impact = $recommendation['impact'] ?? 'medium';
        $impactScores = [
            'high' => 20,
            'medium' => 10,
            'low' => 5
        ];
        $priority += $impactScores[$impact] ?? 0;

        return $priority;
    }

    /**
     * 分析风险趋势
     */
    private function analyzeRiskTrends(array $riskDimensions): array
    {
        $trends = [];

        foreach ($riskDimensions as $dimension => $risk) {
            $score = $risk['score'] ?? 0;
            $historicalData = $risk['historical'] ?? [];
            
            if (count($historicalData) >= 2) {
                $recent = array_slice($historicalData, -5); // 最近5次评估
                $trend = $this->calculateTrend($recent);
                
                $trends[$dimension] = [
                    'current_score' => $score,
                    'trend' => $trend['direction'],
                    'change_rate' => $trend['rate'],
                    'stability' => $trend['stability']
                ];
            } else {
                $trends[$dimension] = [
                    'current_score' => $score,
                    'trend' => 'unknown',
                    'change_rate' => 0,
                    'stability' => 'unknown'
                ];
            }
        }

        return $trends;
    }

    /**
     * 计算趋势
     */
    private function calculateTrend(array $data): array
    {
        $count = count($data);
        if ($count < 2) {
            return ['direction' => 'unknown', 'rate' => 0, 'stability' => 'unknown'];
        }

        // 简单线性回归计算趋势
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;

        foreach ($data as $i => $value) {
            $x = $i + 1;
            $y = $value;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $slope = ($count * $sumXY - $sumX * $sumY) / ($count * $sumXX - $sumX * $sumX);
        
        // 计算稳定性（变异系数）
        $mean = array_sum($data) / $count;
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $data)) / $count;
        $stdDev = sqrt($variance);
        $stability = $mean > 0 ? ($stdDev / $mean) : 0;

        return [
            'direction' => $slope > 0.5 ? 'increasing' : ($slope < -0.5 ? 'decreasing' : 'stable'),
            'rate' => abs($slope),
            'stability' => $stability < 0.1 ? 'stable' : ($stability < 0.3 ? 'moderate' : 'volatile')
        ];
    }

    /**
     * 计算风险指标
     */
    private function calculateRiskMetrics(array $riskDimensions): array
    {
        $totalScore = 0;
        $highRiskDimensions = 0;
        $improvingDimensions = 0;
        $deterioratingDimensions = 0;

        foreach ($riskDimensions as $dimension => $risk) {
            $score = $risk['score'] ?? 0;
            $totalScore += $score;

            if ($score >= 70) {
                $highRiskDimensions++;
            }

            $trend = $risk['trend'] ?? 'stable';
            if ($trend === 'improving') {
                $improvingDimensions++;
            } elseif ($trend === 'deteriorating') {
                $deterioratingDimensions++;
            }
        }

        $averageScore = count($riskDimensions) > 0 ? $totalScore / count($riskDimensions) : 0;

        return [
            'average_dimension_score' => round($averageScore, 1),
            'high_risk_dimensions' => $highRiskDimensions,
            'dimension_count' => count($riskDimensions),
            'improving_dimensions' => $improvingDimensions,
            'deteriorating_dimensions' => $deterioratingDimensions,
            'risk_distribution' => $this->calculateRiskDistribution($riskDimensions),
            'maturity_score' => $this->calculateSecurityMaturityScore($riskDimensions)
        ];
    }

    /**
     * 计算风险分布
     */
    private function calculateRiskDistribution(array $riskDimensions): array
    {
        $distribution = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'minimal' => 0
        ];

        foreach ($riskDimensions as $risk) {
            $score = $risk['score'] ?? 0;
            $level = $this->determineRiskLevel($score);
            $distribution[$level]++;
        }

        return $distribution;
    }

    /**
     * 计算安全成熟度评分
     */
    private function calculateSecurityMaturityScore(array $riskDimensions): array
    {
        $maturityFactors = [
            'automation' => 0,      // 自动化程度
            'monitoring' => 0,      // 监控覆盖
            'response' => 0,        // 响应能力
            'prevention' => 0       // 预防措施
        ];

        // 基于各维度计算成熟度
        foreach ($riskDimensions as $dimension => $risk) {
            $score = $risk['score'] ?? 0;
            $maturityContribution = max(0, 100 - $score) / 100;

            switch ($dimension) {
                case 'vulnerability':
                    $maturityFactors['monitoring'] += $maturityContribution * 0.4;
                    $maturityFactors['response'] += $maturityContribution * 0.3;
                    break;
                case 'database':
                    $maturityFactors['prevention'] += $maturityContribution * 0.4;
                    $maturityFactors['automation'] += $maturityContribution * 0.2;
                    break;
                case 'supply_chain':
                    $maturityFactors['monitoring'] += $maturityContribution * 0.3;
                    $maturityFactors['automation'] += $maturityContribution * 0.3;
                    break;
                case 'compliance':
                    $maturityFactors['prevention'] += $maturityContribution * 0.3;
                    $maturityFactors['response'] += $maturityContribution * 0.2;
                    break;
            }
        }

        // 归一化到0-100范围
        $overallMaturity = array_sum($maturityFactors) * 25; // 4个因素，每个最大1，所以*25得到百分比

        return [
            'overall_score' => round($overallMaturity, 1),
            'factors' => array_map(function ($score) {
                return round($score * 100, 1);
            }, $maturityFactors),
            'level' => $this->determineMaturityLevel($overallMaturity)
        ];
    }

    /**
     * 确定成熟度等级
     */
    private function determineMaturityLevel(float $score): string
    {
        if ($score >= 90) return 'optimized';
        if ($score >= 75) return 'managed';
        if ($score >= 60) return 'defined';
        if ($score >= 40) return 'repeatable';
        return 'initial';
    }

    /**
     * 生成风险报告
     */
    private function generateRiskReport(array $data): array
    {
        $report = [
            'assessment_info' => [
                'project_path' => $data['project_path'],
                'assessment_time' => date('Y-m-d H:i:s'),
                'duration' => round($data['assessment_time'], 2),
                'version' => '1.0'
            ],
            'executive_summary' => $this->generateExecutiveSummary($data['overall_risk']),
            'risk_assessment' => $data['overall_risk'],
            'detailed_analysis' => [
                'vulnerability_risk' => $data['vulnerability_risk'],
                'database_risk' => $data['database_risk'],
                'supply_chain_risk' => $data['supply_chain_risk'],
                'compliance_risk' => $data['compliance_risk']
            ],
            'action_plan' => $this->generateActionPlan($data['overall_risk']),
            'monitoring_recommendations' => $this->generateMonitoringRecommendations($data['overall_risk'])
        ];

        return $report;
    }

    /**
     * 生成执行摘要
     */
    private function generateExecutiveSummary(array $overallRisk): array
    {
        $score = $overallRisk['score'];
        $level = $overallRisk['level'];
        $criticalIssues = count($overallRisk['critical_issues'] ?? []);

        return [
            'overall_security_posture' => $this->getSecurityPostureDescription($level, $score),
            'key_findings' => $this->getKeyFindings($overallRisk),
            'immediate_actions' => $this->getImmediateActions($overallRisk),
            'business_impact' => $this->assessBusinessImpact($level, $criticalIssues),
            'investment_priority' => $this->getInvestmentPriority($overallRisk)
        ];
    }

    /**
     * 获取安全态势描述
     */
    private function getSecurityPostureDescription(string $level, float $score): string
    {
        switch ($level) {
            case 'critical':
                return "项目存在严重的安全风险（评分：{$score}），需要立即采取行动解决关键安全问题。";
            case 'high':
                return "项目安全风险较高（评分：{$score}），建议优先处理高风险问题。";
            case 'medium':
                return "项目安全状况一般（评分：{$score}），存在一些需要关注的安全问题。";
            case 'low':
                return "项目安全状况良好（评分：{$score}），只有少量低风险问题。";
            default:
                return "项目安全状况优秀（评分：{$score}），维持当前安全最佳实践。";
        }
    }

    /**
     * 获取关键发现
     */
    private function getKeyFindings(array $overallRisk): array
    {
        $findings = [];
        
        foreach ($overallRisk['dimensions'] as $dimension => $risk) {
            $score = $risk['score'] ?? 0;
            if ($score >= 70) {
                $findings[] = [
                    'dimension' => $dimension,
                    'finding' => "{$dimension}维度风险较高（{$score}分）",
                    'impact' => $risk['impact'] ?? '中等'
                ];
            }
        }

        return $findings;
    }

    /**
     * 获取立即行动项
     */
    private function getImmediateActions(array $overallRisk): array
    {
        $actions = [];
        $recommendations = $overallRisk['recommendations'] ?? [];
        
        // 取前5个最高优先级的建议
        $topRecommendations = array_slice($recommendations, 0, 5);
        
        foreach ($topRecommendations as $rec) {
            if (($rec['priority_score'] ?? 0) >= 80) {
                $actions[] = $rec['action'] ?? $rec['description'] ?? '未指定行动';
            }
        }

        return $actions;
    }

    /**
     * 评估业务影响
     */
    private function assessBusinessImpact(string $level, int $criticalIssues): array
    {
        $impactLevels = [
            'critical' => ['level' => '极高', 'description' => '可能导致业务中断、数据泄露或重大经济损失'],
            'high' => ['level' => '高', 'description' => '可能影响业务连续性和客户信任'],
            'medium' => ['level' => '中等', 'description' => '可能影响系统性能和用户体验'],
            'low' => ['level' => '低', 'description' => '对业务影响有限'],
            'minimal' => ['level' => '极低', 'description' => '基本不影响正常业务运营']
        ];

        $impact = $impactLevels[$level] ?? $impactLevels['minimal'];
        
        if ($criticalIssues > 0) {
            $impact['critical_issues_impact'] = "检测到{$criticalIssues}个关键安全问题，需要紧急处理";
        }

        return $impact;
    }

    /**
     * 获取投资优先级建议
     */
    private function getInvestmentPriority(array $overallRisk): array
    {
        $dimensions = $overallRisk['dimensions'] ?? [];
        $priorities = [];

        foreach ($dimensions as $dimension => $risk) {
            $score = $risk['score'] ?? 0;
            $weight = $this->riskWeights[$dimension] ?? 0;
            $priority = $score * $weight;

            $priorities[] = [
                'dimension' => $dimension,
                'score' => $score,
                'priority' => round($priority, 1),
                'recommendation' => $this->getDimensionInvestmentRecommendation($dimension, $score)
            ];
        }

        // 按优先级排序
        usort($priorities, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        return $priorities;
    }

    /**
     * 获取维度投资建议
     */
    private function getDimensionInvestmentRecommendation(string $dimension, float $score): string
    {
        $recommendations = [
            'vulnerability' => $score >= 70 ? '投资漏洞管理工具和流程' : '维持现有漏洞监控',
            'database' => $score >= 70 ? '加强数据库安全配置和监控' : '定期数据库安全检查',
            'supply_chain' => $score >= 70 ? '实施供应链安全管理' : '保持依赖项更新',
            'compliance' => $score >= 70 ? '投入合规性改进项目' : '维持现有合规标准'
        ];

        return $recommendations[$dimension] ?? '根据具体情况制定改进计划';
    }

    /**
     * 生成行动计划
     */
    private function generateActionPlan(array $overallRisk): array
    {
        $recommendations = $overallRisk['recommendations'] ?? [];
        
        // 按时间框架分组
        $actionPlan = [
            'immediate' => [],      // 立即执行（1周内）
            'short_term' => [],     // 短期（1个月内）
            'medium_term' => [],    // 中期（3个月内）
            'long_term' => []       // 长期（6个月以上）
        ];

        foreach ($recommendations as $rec) {
            $priority = $rec['priority_score'] ?? 0;
            $timeframe = $this->determineTimeframe($priority);
            
            $actionPlan[$timeframe][] = [
                'action' => $rec['action'] ?? $rec['description'] ?? '未指定',
                'dimension' => $rec['dimension'] ?? '通用',
                'priority_score' => $priority,
                'estimated_effort' => $rec['effort'] ?? '未评估',
                'expected_impact' => $rec['impact'] ?? '中等'
            ];
        }

        return $actionPlan;
    }

    /**
     * 确定时间框架
     */
    private function determineTimeframe(int $priority): string
    {
        if ($priority >= 90) return 'immediate';
        if ($priority >= 70) return 'short_term';
        if ($priority >= 50) return 'medium_term';
        return 'long_term';
    }

    /**
     * 生成监控建议
     */
    private function generateMonitoringRecommendations(array $overallRisk): array
    {
        return [
            'automated_monitoring' => [
                'vulnerability_scanning' => '每日漏洞扫描',
                'dependency_monitoring' => '依赖项更新监控',
                'security_metrics' => '安全指标仪表板'
            ],
            'periodic_reviews' => [
                'monthly' => '安全评估回顾',
                'quarterly' => '风险评估更新',
                'annually' => '全面安全审计'
            ],
            'alerting' => [
                'critical_vulnerabilities' => '关键漏洞即时告警',
                'security_incidents' => '安全事件通知',
                'compliance_violations' => '合规违规提醒'
            ]
        ];
    }
}