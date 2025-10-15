<?php

namespace YC\PromptSystem;

/**
 * AI工具适配器接口
 */
interface AIAdapterInterface
{
    /**
     * 格式化提示词以适配特定AI工具
     */
    public function formatPrompt(string $prompt, array $context): string;
    
    /**
     * 获取工具特定的元数据
     */
    public function getMetadata(): array;
    
    /**
     * 验证提示词是否符合工具要求
     */
    public function validate(string $prompt): bool;
    
    /**
     * 获取Token限制
     */
    public function getTokenLimit(): int;
    
    /**
     * 计算Token数量
     */
    public function countTokens(string $text): int;
}

/**
 * Claude适配器
 */
class ClaudeAdapter implements AIAdapterInterface
{
    private array $config;
    private TokenCounter $tokenCounter;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'model' => 'claude-3-opus',
            'max_tokens' => 100000,
            'temperature' => 0.7,
            'format' => 'xml'
        ], $config);
        
        $this->tokenCounter = new TokenCounter('claude');
    }
    
    public function formatPrompt(string $prompt, array $context): string
    {
        // Claude偏好XML格式的结构化提示
        $formatted = "<analysis>\n";
        
        // 添加系统角色定义
        $formatted .= "<role>您是一位专业的PHP代码分析专家，精通安全审计、性能优化和代码质量评估。</role>\n\n";
        
        // 添加上下文
        if (!empty($context['code'])) {
            $formatted .= "<code_context>\n";
            $formatted .= htmlspecialchars($context['code'], ENT_XML1, 'UTF-8');
            $formatted .= "\n</code_context>\n\n";
        }
        
        // 添加主要任务
        $formatted .= "<task>\n{$prompt}\n</task>\n\n";
        
        // 添加输出要求
        $formatted .= "<output_requirements>\n";
        $formatted .= "- 提供具体的代码修复建议\n";
        $formatted .= "- 解释每个问题的潜在风险\n";
        $formatted .= "- 给出优先级排序\n";
        $formatted .= "- 包含代码示例\n";
        $formatted .= "</output_requirements>\n";
        
        $formatted .= "</analysis>";
        
        return $formatted;
    }
    
    public function getMetadata(): array
    {
        return [
            'adapter' => 'claude',
            'version' => '1.0',
            'model' => $this->config['model'],
            'capabilities' => [
                'code_analysis' => true,
                'long_context' => true,
                'structured_output' => true,
                'multi_language' => true
            ]
        ];
    }
    
    public function validate(string $prompt): bool
    {
        // 检查token限制
        if ($this->countTokens($prompt) > $this->getTokenLimit()) {
            return false;
        }
        
        // 检查是否包含必要的XML标签
        if ($this->config['format'] === 'xml') {
            return str_contains($prompt, '<') && str_contains($prompt, '>');
        }
        
        return true;
    }
    
    public function getTokenLimit(): int
    {
        return $this->config['max_tokens'];
    }
    
    public function countTokens(string $text): int
    {
        return $this->tokenCounter->count($text);
    }
}

/**
 * GPT适配器
 */
class GPTAdapter implements AIAdapterInterface
{
    private array $config;
    private TokenCounter $tokenCounter;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'model' => 'gpt-4',
            'max_tokens' => 8192,
            'temperature' => 0.7,
            'format' => 'markdown'
        ], $config);
        
        $this->tokenCounter = new TokenCounter('gpt');
    }
    
    public function formatPrompt(string $prompt, array $context): string
    {
        // GPT偏好Markdown格式
        $formatted = "# PHP代码分析任务\n\n";
        
        // 系统提示
        $formatted .= "## 角色\n";
        $formatted .= "你是一位经验丰富的PHP开发专家，专注于代码质量、安全性和性能优化。\n\n";
        
        // 上下文
        if (!empty($context['code'])) {
            $formatted .= "## 代码上下文\n";
            $formatted .= "```php\n";
            $formatted .= $context['code'];
            $formatted .= "\n```\n\n";
        }
        
        // 任务描述
        $formatted .= "## 分析任务\n";
        $formatted .= $prompt . "\n\n";
        
        // 输出格式
        $formatted .= "## 期望输出格式\n";
        $formatted .= "1. **问题识别**: 列出发现的所有问题\n";
        $formatted .= "2. **风险评估**: 说明每个问题的严重性和影响\n";
        $formatted .= "3. **修复建议**: 提供具体的代码修复方案\n";
        $formatted .= "4. **最佳实践**: 推荐相关的最佳实践\n";
        
        return $formatted;
    }
    
    public function getMetadata(): array
    {
        return [
            'adapter' => 'gpt',
            'version' => '1.0',
            'model' => $this->config['model'],
            'capabilities' => [
                'code_analysis' => true,
                'reasoning' => true,
                'code_generation' => true
            ]
        ];
    }
    
    public function validate(string $prompt): bool
    {
        return $this->countTokens($prompt) <= $this->getTokenLimit();
    }
    
    public function getTokenLimit(): int
    {
        return $this->config['max_tokens'];
    }
    
    public function countTokens(string $text): int
    {
        return $this->tokenCounter->count($text);
    }
}

/**
 * Copilot适配器
 */
class CopilotAdapter implements AIAdapterInterface
{
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_tokens' => 4096,
            'format' => 'comment'
        ], $config);
    }
    
    public function formatPrompt(string $prompt, array $context): string
    {
        // Copilot适合简洁的注释风格提示
        $formatted = "";
        
        if (!empty($context['code'])) {
            $formatted .= "// Analyzing the following PHP code:\n";
            $formatted .= "// " . str_replace("\n", "\n// ", trim($context['code'])) . "\n\n";
        }
        
        $formatted .= "// Task: " . $prompt . "\n";
        $formatted .= "// Please provide:\n";
        $formatted .= "// 1. Issue identification\n";
        $formatted .= "// 2. Suggested fixes\n";
        $formatted .= "// 3. Code examples\n";
        
        return $formatted;
    }
    
    public function getMetadata(): array
    {
        return [
            'adapter' => 'copilot',
            'version' => '1.0',
            'capabilities' => [
                'code_completion' => true,
                'inline_suggestions' => true,
                'quick_fixes' => true
            ]
        ];
    }
    
    public function validate(string $prompt): bool
    {
        return strlen($prompt) <= $this->getTokenLimit() * 4; // 粗略估算
    }
    
    public function getTokenLimit(): int
    {
        return $this->config['max_tokens'];
    }
    
    public function countTokens(string $text): int
    {
        // Copilot的简单token估算
        return intval(strlen($text) / 4);
    }
}

/**
 * 通用适配器
 */
class GenericAdapter implements AIAdapterInterface
{
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_tokens' => 4096,
            'format' => 'plain'
        ], $config);
    }
    
    public function formatPrompt(string $prompt, array $context): string
    {
        $formatted = "=== PHP Code Analysis Request ===\n\n";
        
        if (!empty($context['code'])) {
            $formatted .= "Code Context:\n";
            $formatted .= "---\n";
            $formatted .= $context['code'];
            $formatted .= "\n---\n\n";
        }
        
        $formatted .= "Analysis Task:\n";
        $formatted .= $prompt . "\n\n";
        
        $formatted .= "Expected Output:\n";
        $formatted .= "- Identified issues with severity levels\n";
        $formatted .= "- Specific fix recommendations\n";
        $formatted .= "- Code examples where applicable\n";
        
        return $formatted;
    }
    
    public function getMetadata(): array
    {
        return [
            'adapter' => 'generic',
            'version' => '1.0',
            'capabilities' => [
                'basic_analysis' => true
            ]
        ];
    }
    
    public function validate(string $prompt): bool
    {
        return strlen($prompt) <= $this->getTokenLimit() * 4;
    }
    
    public function getTokenLimit(): int
    {
        return $this->config['max_tokens'];
    }
    
    public function countTokens(string $text): int
    {
        // 通用的token估算
        return intval(strlen($text) / 4);
    }
}

/**
 * 适配器工厂
 */
class AdapterFactory
{
    private static array $adapters = [
        'claude' => ClaudeAdapter::class,
        'gpt' => GPTAdapter::class,
        'copilot' => CopilotAdapter::class,
        'generic' => GenericAdapter::class
    ];
    
    public static function create(string $type, array $config = []): AIAdapterInterface
    {
        if (!isset(self::$adapters[$type])) {
            throw new \InvalidArgumentException("Unknown adapter type: {$type}");
        }
        
        $class = self::$adapters[$type];
        return new $class($config);
    }
    
    public static function registerAdapter(string $type, string $class): void
    {
        if (!is_subclass_of($class, AIAdapterInterface::class)) {
            throw new \InvalidArgumentException("Class must implement AIAdapterInterface");
        }
        
        self::$adapters[$type] = $class;
    }
    
    public static function getAvailableAdapters(): array
    {
        return array_keys(self::$adapters);
    }
}