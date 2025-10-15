<?php

namespace YC\PromptSystem;

/**
 * MCP协议集成 - 处理与MCP服务器的通信
 */
class MCPIntegration
{
    private PromptGenerator $promptGenerator;
    private ContextCompressor $compressor;
    private array $adapters;
    private array $config;
    private CacheManager $cache;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'server_url' => 'stdio://php-code-analyzer',
            'timeout' => 30,
            'retry_attempts' => 3,
            'cache_enabled' => true,
            'compression_enabled' => true
        ], $config);
        
        $this->promptGenerator = new PromptGenerator($config);
        $this->compressor = new ContextCompressor();
        $this->cache = new CacheManager();
        
        // 初始化适配器
        $this->initializeAdapters();
    }
    
    /**
     * 发送分析请求到MCP服务器
     */
    public function sendAnalysisRequest(array $codeData, string $aiTool = 'claude'): array
    {
        // 1. 生成提示词
        $prompt = $this->generatePromptForAnalysis($codeData, $aiTool);
        
        // 2. 压缩上下文
        $compressedContext = $this->prepareContext($codeData);
        
        // 3. 构建MCP请求
        $request = $this->buildMCPRequest($prompt, $compressedContext, $aiTool);
        
        // 4. 发送请求
        $response = $this->sendToMCPServer($request);
        
        // 5. 处理响应
        return $this->processResponse($response);
    }
    
    /**
     * 提供工具调用接口
     */
    public function provideTools(): array
    {
        return [
            [
                'name' => 'analyze_php_code',
                'description' => 'Analyze PHP code for security, performance, and quality issues',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string', 'description' => 'PHP code to analyze'],
                        'file_path' => ['type' => 'string', 'description' => 'Path to the PHP file'],
                        'analysis_type' => [
                            'type' => 'string',
                            'enum' => ['security', 'performance', 'quality', 'all'],
                            'description' => 'Type of analysis to perform'
                        ],
                        'ai_tool' => [
                            'type' => 'string',
                            'enum' => ['claude', 'gpt', 'copilot', 'generic'],
                            'description' => 'AI tool to format prompt for'
                        ]
                    ],
                    'required' => ['code']
                ]
            ],
            [
                'name' => 'get_fix_suggestion',
                'description' => 'Get specific fix suggestions for identified issues',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'issue_type' => ['type' => 'string'],
                        'code_context' => ['type' => 'string'],
                        'severity' => ['type' => 'string']
                    ],
                    'required' => ['issue_type', 'code_context']
                ]
            ],
            [
                'name' => 'explain_vulnerability',
                'description' => 'Explain a security vulnerability in detail',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'vulnerability_type' => ['type' => 'string'],
                        'code_example' => ['type' => 'string'],
                        'language' => ['type' => 'string', 'enum' => ['zh', 'en']]
                    ],
                    'required' => ['vulnerability_type']
                ]
            ]
        ];
    }
    
    /**
     * 处理工具调用
     */
    public function handleToolCall(string $toolName, array $arguments): array
    {
        return match($toolName) {
            'analyze_php_code' => $this->analyzeCode($arguments),
            'get_fix_suggestion' => $this->getFixSuggestion($arguments),
            'explain_vulnerability' => $this->explainVulnerability($arguments),
            default => ['error' => 'Unknown tool: ' . $toolName]
        };
    }
    
    /**
     * 提供资源列表
     */
    public function provideResources(): array
    {
        return [
            [
                'uri' => 'prompt://security-analysis',
                'name' => 'Security Analysis Prompts',
                'description' => 'Prompts for PHP security vulnerability analysis',
                'mimeType' => 'text/plain'
            ],
            [
                'uri' => 'prompt://performance-analysis',
                'name' => 'Performance Analysis Prompts',
                'description' => 'Prompts for PHP performance optimization',
                'mimeType' => 'text/plain'
            ],
            [
                'uri' => 'prompt://code-quality',
                'name' => 'Code Quality Prompts',
                'description' => 'Prompts for PHP code quality assessment',
                'mimeType' => 'text/plain'
            ],
            [
                'uri' => 'context://analysis-results',
                'name' => 'Analysis Results Context',
                'description' => 'Compressed context from PHP code analysis',
                'mimeType' => 'application/json'
            ]
        ];
    }
    
    /**
     * 读取资源内容
     */
    public function readResource(string $uri): array
    {
        $parts = explode('://', $uri);
        $type = $parts[0];
        $resource = $parts[1];
        
        return match($type) {
            'prompt' => $this->getPromptResource($resource),
            'context' => $this->getContextResource($resource),
            default => ['error' => 'Unknown resource type']
        };
    }
    
    /**
     * 提供提示消息
     */
    public function getPrompts(): array
    {
        return [
            [
                'name' => 'analyze_security',
                'description' => 'Analyze PHP code for security vulnerabilities',
                'arguments' => [
                    ['name' => 'code', 'description' => 'PHP code to analyze', 'required' => true],
                    ['name' => 'context', 'description' => 'Additional context', 'required' => false]
                ]
            ],
            [
                'name' => 'optimize_performance',
                'description' => 'Suggest performance optimizations for PHP code',
                'arguments' => [
                    ['name' => 'code', 'description' => 'PHP code to optimize', 'required' => true],
                    ['name' => 'metrics', 'description' => 'Current performance metrics', 'required' => false]
                ]
            ],
            [
                'name' => 'refactor_code',
                'description' => 'Suggest refactoring improvements',
                'arguments' => [
                    ['name' => 'code', 'description' => 'PHP code to refactor', 'required' => true],
                    ['name' => 'patterns', 'description' => 'Preferred design patterns', 'required' => false]
                ]
            ]
        ];
    }
    
    /**
     * 处理提示请求
     */
    public function handlePrompt(string $promptName, array $arguments): string
    {
        $category = $this->mapPromptToCategory($promptName);
        
        $analysisResult = [
            'code' => $arguments['code'] ?? '',
            'context' => $arguments['context'] ?? [],
            'metrics' => $arguments['metrics'] ?? []
        ];
        
        return $this->promptGenerator->generateAnalysisPrompt($analysisResult, $category);
    }
    
    private function generatePromptForAnalysis(array $codeData, string $aiTool): string
    {
        // 检测分析类型
        $category = $this->detectAnalysisCategory($codeData);
        
        // 生成基础提示词
        $basePrompt = $this->promptGenerator->generateAnalysisPrompt($codeData, $category);
        
        // 使用适配器格式化
        $adapter = $this->adapters[$aiTool];
        return $adapter->formatPrompt($basePrompt, $codeData);
    }
    
    private function prepareContext(array $codeData): array
    {
        if (!$this->config['compression_enabled']) {
            return $codeData;
        }
        
        // 检查缓存
        $cacheKey = $this->generateCacheKey($codeData);
        if ($this->config['cache_enabled'] && $cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        // 压缩上下文
        $compressed = $this->compressor->compress($codeData);
        
        // 缓存压缩结果
        if ($this->config['cache_enabled']) {
            $this->cache->set($cacheKey, $compressed, 3600); // 缓存1小时
        }
        
        return $compressed;
    }
    
    private function buildMCPRequest(string $prompt, array $context, string $aiTool): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => 'completion',
            'params' => [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'context' => $context,
                'metadata' => [
                    'ai_tool' => $aiTool,
                    'timestamp' => time(),
                    'version' => '1.0'
                ]
            ],
            'id' => uniqid('mcp_', true)
        ];
    }
    
    private function sendToMCPServer(array $request): array
    {
        // 实现MCP服务器通信
        // 这里是一个简化的示例，实际需要实现完整的MCP协议
        
        $attempts = 0;
        $lastError = null;
        
        while ($attempts < $this->config['retry_attempts']) {
            try {
                $response = $this->executeRequest($request);
                if ($response) {
                    return $response;
                }
            } catch (\Exception $e) {
                $lastError = $e;
                $attempts++;
                usleep(100000 * $attempts); // 指数退避
            }
        }
        
        throw new \RuntimeException('Failed to send MCP request: ' . $lastError->getMessage());
    }
    
    private function executeRequest(array $request): array
    {
        // 模拟MCP服务器响应
        // 实际实现需要使用进程间通信或网络请求
        
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'analysis' => 'Code analysis completed',
                'issues' => [],
                'suggestions' => []
            ],
            'id' => $request['id']
        ];
    }
    
    private function processResponse(array $response): array
    {
        if (isset($response['error'])) {
            throw new \RuntimeException('MCP Error: ' . json_encode($response['error']));
        }
        
        $result = $response['result'] ?? [];
        
        // 解压上下文如果需要
        if (isset($result['compressed_context'])) {
            $result['context'] = $this->compressor->decompress($result['compressed_context']);
            unset($result['compressed_context']);
        }
        
        return $result;
    }
    
    private function initializeAdapters(): void
    {
        $this->adapters = [
            'claude' => new ClaudeAdapter($this->config['claude'] ?? []),
            'gpt' => new GPTAdapter($this->config['gpt'] ?? []),
            'copilot' => new CopilotAdapter($this->config['copilot'] ?? []),
            'generic' => new GenericAdapter()
        ];
    }
    
    private function detectAnalysisCategory(array $codeData): string
    {
        if (!empty($codeData['vulnerabilities'])) return 'security';
        if (!empty($codeData['performance_metrics'])) return 'performance';
        if (!empty($codeData['code_smells'])) return 'refactoring';
        if (!empty($codeData['dependencies'])) return 'dependency';
        
        return 'general';
    }
    
    private function generateCacheKey(array $data): string
    {
        return 'mcp_context_' . md5(json_encode($data));
    }
    
    private function analyzeCode(array $arguments): array
    {
        $analysisType = $arguments['analysis_type'] ?? 'all';
        $aiTool = $arguments['ai_tool'] ?? 'claude';
        
        // 执行分析
        $analysisResult = [
            'code' => $arguments['code'],
            'file_path' => $arguments['file_path'] ?? 'unknown.php',
            'issues' => [],
            'metrics' => []
        ];
        
        // 根据分析类型执行不同的检查
        if ($analysisType === 'security' || $analysisType === 'all') {
            $analysisResult['issues'][] = $this->performSecurityAnalysis($arguments['code']);
        }
        
        if ($analysisType === 'performance' || $analysisType === 'all') {
            $analysisResult['metrics'] = $this->performPerformanceAnalysis($arguments['code']);
        }
        
        if ($analysisType === 'quality' || $analysisType === 'all') {
            $analysisResult['code_smells'] = $this->performQualityAnalysis($arguments['code']);
        }
        
        // 生成提示词
        $prompt = $this->generatePromptForAnalysis($analysisResult, $aiTool);
        
        return [
            'prompt' => $prompt,
            'analysis' => $analysisResult,
            'compressed_context' => $this->prepareContext($analysisResult)
        ];
    }
    
    private function getFixSuggestion(array $arguments): array
    {
        $issueType = $arguments['issue_type'];
        $context = $arguments['code_context'];
        $severity = $arguments['severity'] ?? 'medium';
        
        // 生成修复建议
        $suggestion = $this->promptGenerator->generateSuggestions(
            ['issues' => [['type' => $issueType, 'severity' => $severity]]],
            'security'
        );
        
        return [
            'issue_type' => $issueType,
            'suggestion' => $suggestion,
            'example_fix' => $this->getExampleFix($issueType),
            'references' => $this->getReferences($issueType)
        ];
    }
    
    private function explainVulnerability(array $arguments): array
    {
        $vulnType = $arguments['vulnerability_type'];
        $language = $arguments['language'] ?? 'zh';
        
        // 获取漏洞解释
        $explanation = $this->getVulnerabilityExplanation($vulnType, $language);
        
        return [
            'vulnerability' => $vulnType,
            'explanation' => $explanation,
            'impact' => $this->getImpactDescription($vulnType, $language),
            'mitigation' => $this->getMitigationStrategies($vulnType, $language)
        ];
    }
    
    private function mapPromptToCategory(string $promptName): string
    {
        $mapping = [
            'analyze_security' => 'security',
            'optimize_performance' => 'performance',
            'refactor_code' => 'refactoring'
        ];
        
        return $mapping[$promptName] ?? 'general';
    }
    
    private function getPromptResource(string $resource): array
    {
        // 返回预定义的提示词模板
        $templates = [
            'security-analysis' => file_get_contents(__DIR__ . '/../templates/security.txt'),
            'performance-analysis' => file_get_contents(__DIR__ . '/../templates/performance.txt'),
            'code-quality' => file_get_contents(__DIR__ . '/../templates/quality.txt')
        ];
        
        return [
            'contents' => [
                [
                    'uri' => 'prompt://' . $resource,
                    'mimeType' => 'text/plain',
                    'text' => $templates[$resource] ?? ''
                ]
            ]
        ];
    }
    
    private function getContextResource(string $resource): array
    {
        // 返回分析结果上下文
        if ($resource === 'analysis-results') {
            return [
                'contents' => [
                    [
                        'uri' => 'context://analysis-results',
                        'mimeType' => 'application/json',
                        'text' => json_encode($this->cache->get('last_analysis_results') ?? [])
                    ]
                ]
            ];
        }
        
        return ['error' => 'Resource not found'];
    }
    
    // 简化的分析方法示例
    private function performSecurityAnalysis(string $code): array
    {
        // 实际实现需要完整的安全分析逻辑
        return [];
    }
    
    private function performPerformanceAnalysis(string $code): array
    {
        return [];
    }
    
    private function performQualityAnalysis(string $code): array
    {
        return [];
    }
    
    private function getExampleFix(string $issueType): string
    {
        return '';
    }
    
    private function getReferences(string $issueType): array
    {
        return [];
    }
    
    private function getVulnerabilityExplanation(string $type, string $lang): string
    {
        return '';
    }
    
    private function getImpactDescription(string $type, string $lang): string
    {
        return '';
    }
    
    private function getMitigationStrategies(string $type, string $lang): array
    {
        return [];
    }
}