<?php

namespace YC\PromptSystem;

/**
 * 动态提示词生成器
 */
class PromptGenerator
{
    private TemplateEngine $templateEngine;
    private ContextExtractor $contextExtractor;
    private PromptOptimizer $optimizer;
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_tokens' => 4096,
            'language' => 'zh',
            'optimization_level' => 'balanced',
            'include_examples' => true,
            'context_depth' => 3
        ], $config);
        
        $this->templateEngine = new TemplateEngine();
        $this->contextExtractor = new ContextExtractor();
        $this->optimizer = new PromptOptimizer($this->config);
    }
    
    /**
     * 生成代码分析提示词
     */
    public function generateAnalysisPrompt(array $analysisResult, string $category): string
    {
        // 提取关键上下文
        $context = $this->contextExtractor->extract($analysisResult, [
            'category' => $category,
            'depth' => $this->config['context_depth'],
            'include_surrounding' => true
        ]);
        
        // 选择模板
        $template = $this->templateEngine->getTemplate($category, $this->config['language']);
        
        // 填充模板
        $prompt = $this->templateEngine->render($template, [
            'context' => $context,
            'issues' => $analysisResult['issues'] ?? [],
            'code_snippet' => $this->formatCodeSnippet($analysisResult),
            'severity' => $this->calculateSeverity($analysisResult),
            'suggestions' => $this->generateSuggestions($analysisResult, $category)
        ]);
        
        // 优化提示词
        return $this->optimizer->optimize($prompt);
    }
    
    /**
     * 生成安全审计提示词
     */
    public function generateSecurityPrompt(array $vulnerabilities): string
    {
        $prompts = [];
        
        // 按严重性分组
        $grouped = $this->groupBySeverity($vulnerabilities);
        
        foreach ($grouped as $severity => $vulns) {
            $prompts[] = $this->buildSecuritySection($severity, $vulns);
        }
        
        // 添加总结和建议
        $prompts[] = $this->generateSecuritySummary($vulnerabilities);
        
        return $this->optimizer->optimize(implode("\n\n", $prompts));
    }
    
    /**
     * 生成性能优化提示词
     */
    public function generatePerformancePrompt(array $metrics, array $bottlenecks): string
    {
        $template = $this->templateEngine->getTemplate('performance', $this->config['language']);
        
        return $this->templateEngine->render($template, [
            'metrics' => $this->formatMetrics($metrics),
            'bottlenecks' => $this->analyzeBottlenecks($bottlenecks),
            'optimization_paths' => $this->suggestOptimizations($bottlenecks),
            'priority_matrix' => $this->buildPriorityMatrix($bottlenecks)
        ]);
    }
    
    /**
     * 生成重构建议提示词
     */
    public function generateRefactoringPrompt(array $codeSmells, array $metrics): string
    {
        $context = [
            'code_smells' => $this->categorizeCodeSmells($codeSmells),
            'complexity_metrics' => $metrics,
            'refactoring_patterns' => $this->suggestPatterns($codeSmells),
            'impact_analysis' => $this->analyzeRefactoringImpact($codeSmells, $metrics)
        ];
        
        $prompt = $this->templateEngine->render(
            $this->templateEngine->getTemplate('refactoring', $this->config['language']),
            $context
        );
        
        return $this->optimizer->optimize($prompt);
    }
    
    /**
     * 生成依赖管理提示词
     */
    public function generateDependencyPrompt(array $dependencies, array $conflicts): string
    {
        return $this->templateEngine->render(
            $this->templateEngine->getTemplate('dependency', $this->config['language']),
            [
                'dependencies' => $this->analyzeDependencies($dependencies),
                'conflicts' => $this->resolveConflicts($conflicts),
                'security_updates' => $this->checkSecurityUpdates($dependencies),
                'architecture_impact' => $this->assessArchitectureImpact($dependencies)
            ]
        );
    }
    
    /**
     * 动态调整提示词基于反馈
     */
    public function adjustPromptWithFeedback(string $originalPrompt, array $feedback): string
    {
        $adjustments = [
            'clarity' => $feedback['clarity'] ?? 1.0,
            'relevance' => $feedback['relevance'] ?? 1.0,
            'detail_level' => $feedback['detail_level'] ?? 'balanced'
        ];
        
        // 根据反馈调整优化策略
        $this->optimizer->setAdjustments($adjustments);
        
        // 重新优化提示词
        return $this->optimizer->optimize($originalPrompt);
    }
    
    /**
     * 批量生成提示词
     */
    public function generateBatch(array $analysisResults): array
    {
        $prompts = [];
        
        foreach ($analysisResults as $key => $result) {
            $category = $this->detectCategory($result);
            $prompts[$key] = [
                'prompt' => $this->generateAnalysisPrompt($result, $category),
                'category' => $category,
                'tokens' => $this->optimizer->countTokens($prompts[$key]['prompt'] ?? ''),
                'language' => $this->config['language']
            ];
        }
        
        return $prompts;
    }
    
    private function formatCodeSnippet(array $analysisResult): string
    {
        $snippet = $analysisResult['code'] ?? '';
        $lineNumbers = $analysisResult['line_numbers'] ?? [];
        
        // 添加行号和高亮问题行
        $lines = explode("\n", $snippet);
        $formatted = [];
        
        foreach ($lines as $index => $line) {
            $lineNum = $lineNumbers[$index] ?? ($index + 1);
            $prefix = in_array($lineNum, $analysisResult['problem_lines'] ?? []) ? '>>> ' : '    ';
            $formatted[] = sprintf("%s%4d: %s", $prefix, $lineNum, $line);
        }
        
        return implode("\n", $formatted);
    }
    
    private function calculateSeverity(array $analysisResult): string
    {
        $severityScore = 0;
        
        foreach ($analysisResult['issues'] ?? [] as $issue) {
            $severityScore += $this->getSeverityWeight($issue['severity'] ?? 'low');
        }
        
        if ($severityScore >= 10) return 'critical';
        if ($severityScore >= 5) return 'high';
        if ($severityScore >= 2) return 'medium';
        return 'low';
    }
    
    private function getSeverityWeight(string $severity): int
    {
        return match($severity) {
            'critical' => 5,
            'high' => 3,
            'medium' => 1,
            'low' => 0.5,
            default => 0
        };
    }
    
    private function generateSuggestions(array $analysisResult, string $category): array
    {
        $suggestions = [];
        
        foreach ($analysisResult['issues'] ?? [] as $issue) {
            $suggestions[] = $this->generateSuggestionForIssue($issue, $category);
        }
        
        return $suggestions;
    }
    
    private function generateSuggestionForIssue(array $issue, string $category): array
    {
        return [
            'issue' => $issue['type'] ?? 'unknown',
            'description' => $issue['message'] ?? '',
            'fix' => $this->suggestFix($issue, $category),
            'example' => $this->provideExample($issue, $category),
            'reference' => $this->getReference($issue)
        ];
    }
    
    private function suggestFix(array $issue, string $category): string
    {
        // 基于问题类型和类别生成修复建议
        $fixes = [
            'sql_injection' => '使用预处理语句或参数化查询',
            'xss' => '对用户输入进行适当的转义和过滤',
            'performance' => '考虑添加索引或优化查询结构',
            'complexity' => '将复杂函数拆分为更小的、单一职责的函数'
        ];
        
        return $fixes[$issue['type']] ?? '请根据具体情况进行修复';
    }
    
    private function provideExample(array $issue, string $category): string
    {
        if (!$this->config['include_examples']) {
            return '';
        }
        
        // 从示例库中获取相关示例
        return $this->templateEngine->getExample($issue['type'], $category);
    }
    
    private function getReference(array $issue): string
    {
        $references = [
            'sql_injection' => 'https://owasp.org/www-community/attacks/SQL_Injection',
            'xss' => 'https://owasp.org/www-community/attacks/xss/',
            'performance' => 'https://www.php.net/manual/en/features.performance.php'
        ];
        
        return $references[$issue['type']] ?? '';
    }
    
    private function detectCategory(array $result): string
    {
        // 基于结果内容自动检测类别
        if (!empty($result['vulnerabilities'])) return 'security';
        if (!empty($result['performance_metrics'])) return 'performance';
        if (!empty($result['code_smells'])) return 'refactoring';
        if (!empty($result['dependencies'])) return 'dependency';
        
        return 'general';
    }
}