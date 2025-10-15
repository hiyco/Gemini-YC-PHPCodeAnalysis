# Gemini-YC-PHPCodeAnalysis

<div align="center">

**Languages:** [English](README-EN.md) | [简体中文](README.md)

**专业级PHP代码分析平台 + Model Context Protocol SDK，提供全面的语法检查、安全审计、性能优化和AI模型集成**

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.3-blue.svg)](https://www.php.net/)
[![Composer](https://img.shields.io/badge/Composer-Ready-green.svg)](https://getcomposer.org/)
[![Tests](https://img.shields.io/badge/Tests-85%2B-success.svg)](tests/)

</div>

## 🎯 项目概述

**Gemini-YC-PHPCodeAnalysis** 是一个基Gemini CLi 工具扩展集成了完整 MCP (Model Context Protocol) SDK 的专业级PHP代码分析平台。它不仅提供传统的代码质量检查功能，还集成了主流AI大模型，支持智能代码分析、AI驱动的代码审查和自动化代码优化建议,

### ✨ 核心特性

**🔍 传统代码分析能力：**
- **AST解析器** - 基于nikic/php-parser的高性能抽象语法树解析
- **安全审计** - OWASP Top 10漏洞检测，支持SQL注入、XSS等
- **性能分析** - 内置基准测试系统，支持性能回归检测
- **代码质量** - 语法检查、复杂度分析、最佳实践检测
- **高性能** - LRU缓存、并行处理、大文件优化

**🤖 AI集成能力：**
- **多模型支持** - 集成阿里QWEN、DeepSeek、字节豆包、百度文心一言、OpenAI、Claude
- **MCP协议** - 完整的Model Context Protocol服务器/客户端实现
- **智能分析** - AI驱动的代码质量检查和安全漏洞检测
- **自动建议** - 基于AI的代码优化和重构建议
- **多传输层** - 支持STDIO、WebSocket、HTTP等传输协议

**🛠️ 工程化特性：**
- **CLI工具** - 命令行界面，支持多种输出格式
- **全面测试** - 85+测试用例，涵盖单元、集成、性能测试
- **丰富工具** - 内置文件操作、系统命令、数据处理等MCP工具

## 🏗️ 系统架构

```
YC-PHPCodeAnalysis&MCP 混合架构
┌─────────────────────────────────────────────────────────────────────┐
│  CLI Interface: bin/pca | MCP Server | AI Integration Tools         │
├─────────────────────────────────────────────────────────────────────┤
│  MCP Layer: Protocol | Transport | Model Providers | Built-in Tools │
├─────────────────────────────────────────────────────────────────────┤
│  AI Models: QWEN | DeepSeek | Doubao | ERNIE | OpenAI | Claude      │
├─────────────────────────────────────────────────────────────────────┤
│  Analysis Engine: AnalysisEngine | Analyzer Interface | Results     │
├─────────────────────────────────────────────────────────────────────┤
│  Analyzers: SecurityAnalyzer | SyntaxAnalyzer | PerformanceAnalyzer │
├─────────────────────────────────────────────────────────────────────┤
│  Rule Engines: SecurityRules | SyntaxRules | Performance Rules      │
├─────────────────────────────────────────────────────────────────────┤
│  AST Parser: nikic/php-parser | LRU Cache | Error Recovery          │
├─────────────────────────────────────────────────────────────────────┤
│  Models: FileContext | AnalysisResult | Issue | Report Generator    │
└─────────────────────────────────────────────────────────────────────┘
```

## 🚀 快速开始

### 系统要求

- **PHP**: ≥8.3
- **Composer**: ≥2.0
- **内存**: ≥256MB (推荐512MB+)
- **扩展**: json, mbstring, tokenizer

### 1. 安装依赖

```bash
composer install
```

### 2. 验证安装

```bash
./bin/pca --version
```

### 3. 分析代码

```bash
# 传统分析模式
./bin/pca analyze path/to/file.php

# 分析整个目录
./bin/pca analyze src/

# AI增强分析模式
./bin/pca analyze src/ --ai-enhanced --provider qwen --api-key YOUR_API_KEY
```

### 4. MCP服务器模式

```bash
# 启动MCP服务器
php examples/mcp-server-example.php
```

## 🎯 使用示例

### 传统代码分析

```bash
# 分析单个文件
./bin/pca analyze path/to/file.php

# 分析整个目录
./bin/pca analyze src/

# 生成JSON报告
./bin/pca analyze src/ --format json --output report.json

# 只检查安全漏洞
./bin/pca analyze src/ --include-security --severity high
```

### AI增强分析

```bash
# 使用QWEN模型进行智能分析
./bin/pca ai-analyze src/ --provider qwen --api-key YOUR_QWEN_KEY

# 使用DeepSeek进行代码审查
./bin/pca code-review file.php --provider deepseek --api-key YOUR_DEEPSEEK_KEY

# 多模型对比分析
./bin/pca analyze src/ --ai-compare --providers qwen,deepseek,doubao
```

### MCP服务器部署

```bash
# 启动带AI功能的MCP服务器
php examples/mcp-server-example.php

# 使用MCP工具
echo '{"method": "ai_chat", "params": {"message": "分析这段PHP代码", "provider": "qwen", "api_key": "YOUR_KEY"}}' | php examples/mcp-server-example.php
```

### Gemini CLI 扩展

#### 安装
```bash
# 从 GitHub 仓库安装
gemini extensions install https://github.com/hiyco/Gemini-YC-PHPCodeAnalysis
```

#### 基本用法
```bash
# 分析当前项目
/code_review

# 获取帮助
/help
```

### 传统编程接口

```php
<?php
use YcPca\Ast\PhpAstParser;
use YcPca\Analysis\AnalysisEngine;
use YcPca\Analysis\Analyzer\SecurityAnalyzer;
use YcPca\Analysis\Security\SecurityRuleEngine;
use YcPca\Analysis\Security\Rule\SqlInjectionRule;
use YcPca\Model\FileContext;

// 初始化组件
$astParser = new PhpAstParser();
$analysisEngine = new AnalysisEngine();

// 配置安全分析器
$securityRuleEngine = new SecurityRuleEngine();
$securityRuleEngine->addRule(new SqlInjectionRule());
$securityAnalyzer = new SecurityAnalyzer($securityRuleEngine);
$analysisEngine->addAnalyzer($securityAnalyzer);

// 分析文件
$context = new FileContext('/path/to/file.php');
$ast = $astParser->parse($context);
$result = $analysisEngine->analyze($context, $ast);

// 处理结果
foreach ($result->getIssues() as $issue) {
    echo sprintf(
        "[%s] %s at line %d: %s\n",
        $issue->getSeverity(),
        $issue->getCategory(),
        $issue->getLine(),
        $issue->getTitle()
    );
}
```

### MCP-AI集成接口

```php
<?php
use YcPca\Mcp\Model\ModelProviderFactory;
use YcPca\Mcp\Server\McpServer;
use YcPca\Mcp\Server\Tools\BuiltinTools;

// AI模型调用
$provider = ModelProviderFactory::create('qwen', [
    'api_key' => 'your-api-key'
]);

$response = $provider->complete('请分析这段PHP代码的安全性问题');
echo $response->getContent();

// MCP服务器创建
$server = new McpServer();
BuiltinTools::registerAll($server);

// 注册AI增强工具
$server->registerTool(
    'ai_code_review',
    function (array $args) use ($provider): string {
        $code = $args['code'];
        $response = $provider->complete("请对以下PHP代码进行专业审查：\n\n```php\n{$code}\n```");
        return $response->getContent();
    },
    [
        'type' => 'object',
        'properties' => [
            'code' => ['type' => 'string', 'description' => '要审查的PHP代码']
        ],
        'required' => ['code']
    ],
    '使用AI进行代码审查'
);

$server->start();
```

## 🔍 支持的检查类型

### 传统静态分析

**安全漏洞检测 (OWASP Top 10):**
- **A01: 访问控制缺陷** - 权限验证、直接对象引用
- **A02: 加密故障** - 弱加密算法、硬编码密钥
- **A03: 注入攻击** - SQL注入、命令注入、XSS
- **A04: 不安全设计** - 业务逻辑漏洞
- **A05: 安全配置错误** - 默认配置、信息泄露

**代码质量检查:**
- **命名规范** - 类、方法、变量命名约定
- **代码复杂度** - 圈复杂度、认知复杂度
- **代码重复** - 重复代码块检测
- **最佳实践** - PSR标准、设计模式

**性能分析:**
- **算法复杂度** - 时间和空间复杂度分析
- **性能瓶颈** - 循环、递归、数据库查询
- **内存使用** - 内存泄漏、大对象分析

### AI增强分析

**智能代码审查:**
- **上下文理解** - 基于业务逻辑的深度分析
- **模式识别** - 识别复杂的反模式和代码异味
- **安全漏洞深度检测** - AI驱动的漏洞发现
- **性能优化建议** - 基于最佳实践的智能建议

**多模型对比分析:**
- **QWEN** - 中文友好，擅长业务逻辑理解
- **DeepSeek** - 代码理解强，适合技术架构分析
- **Claude** - 安全性分析专业，提供详细建议
- **GPT** - 综合分析能力强，适合全面审查

**自动化建议:**
- **代码重构建议** - AI生成的重构方案
- **性能优化提案** - 具体的优化代码建议
- **安全加固方案** - 针对性的安全改进措施

## 📊 性能基准测试

**YC-PHPCodeAnalysis&MCP** 包含内置的性能基准测试系统，用于监控分析引擎性能和AI模型响应时间。

### 运行基准测试

```bash
# 运行性能基准测试示例
php examples/benchmark_demo.php

# 查看基准测试结果
ls examples/benchmark_*
```

### 编程接口使用基准测试

```php
use YcPca\Benchmark\BenchmarkRunner;
use YcPca\Benchmark\BenchmarkSuite;
use YcPca\Benchmark\Benchmarks\ParsingBenchmark;

$runner = new BenchmarkRunner($astParser, $analysisEngine);

$suite = new BenchmarkSuite('parsing_performance');
$suite->addBenchmark(new ParsingBenchmark());

$runner->addBenchmarkSuite($suite);
$results = $runner->runAllBenchmarks();

echo $runner->generateReport($results);
```

### 回归检测

```bash
# 建立性能基线
cp examples/benchmark_results_*.json examples/baseline_results.json

# 后续测试会自动与基线对比，检测性能回归
php examples/benchmark_demo.php
```

## ⚙️ 配置

### CLI配置选项

```bash
# 分析选项
--format          # 输出格式: console, json, xml
--output          # 输出文件路径
--severity        # 最低严重级别: info, low, medium, high, critical
--include-security# 包含安全检查
--exclude         # 排除文件模式
--parallel        # 并行处理
--memory-limit    # 内存限制
--timeout         # 超时限制

# 报告选项
--stats           # 显示详细统计信息
--verbose, -v     # 详细输出
--quiet, -q       # 静默模式
```

### 编程配置

```php
// 自定义缓存配置
$astParser = new PhpAstParser([
    'cache_enabled' => true,
    'cache_size' => 1000,
    'cache_ttl' => 3600
]);

// 分析引擎配置
$analysisEngine = new AnalysisEngine();
$analysisEngine->setCachingEnabled(true)
              ->setParallelProcessing(true);

// 基准测试配置
$benchmarkConfig = [
    'iterations' => 10,
    'warmup_runs' => 3,
    'regression_time_threshold' => 15.0,
    'regression_memory_threshold' => 25.0
];
```

## 🧪 测试

```bash
# 运行所有测试
composer test

# 运行特定测试套件
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit tests/Integration
vendor/bin/phpunit tests/Performance

# 运行代码覆盖率测试
composer test:coverage
```

## 📁 项目结构

```
YC-PHPCodeAnalysis&MCP/
├── bin/                    # CLI可执行文件
├── src/                    # 核心源代码
│   ├── Ast/               # AST解析器
│   ├── Analysis/          # 分析引擎和分析器
│   ├── Mcp/               # MCP-PHP SDK
│   │   ├── Model/         # AI模型提供商
│   │   ├── Protocol/      # MCP协议实现
│   │   ├── Server/        # MCP服务器
│   │   └── Transport/     # 传输层
│   ├── Benchmark/         # 性能基准测试系统
│   ├── Cli/               # 命令行接口
│   ├── Model/             # 数据模型
│   └── Report/            # 报告生成器
├── tests/                 # 测试文件
│   ├── Unit/             # 单元测试
│   ├── Integration/      # 集成测试
│   ├── Performance/      # 性能测试
│   ├── Feature/          # 功能测试
│   ├── Mcp/              # MCP测试
│   └── Fixtures/         # 测试夹具
├── examples/             # 使用示例
│   └── mcp-server-example.php # MCP服务器示例
├── docs/                 # 文档目录
│   └── MCP-SDK-USAGE.md  # MCP使用文档
├── MCP-README.md         # MCP功能说明
└── composer.json         # 依赖配置
```

## 🛣️ 开发计划

### 已完成
- ✅ PHP AST解析器核心
- ✅ 基础分析框架架构
- ✅ 语法检查模块
- ✅ 基础安全扫描引擎
- ✅ CLI工具原型
- ✅ 单元测试框架
- ✅ 性能基准测试系统
- ✅ **MCP-PHP SDK完整实现**
- ✅ **6大AI模型提供商集成 (QWEN/DeepSeek/Doubao/ERNIE/OpenAI/Claude)**
- ✅ **MCP服务器和丰富的内置工具**
- ✅ **AI增强代码分析功能**

### 计划中
- 🔄 更多安全规则 (XSS, CSRF, 文件上传漏洞等)
- 🔄 代码质量度量 (圈复杂度、重复度等)
- 🔄 AI分析结果与传统分析的融合
- 🔄 MCP客户端实现
- 🔄 VSCode扩展 (集成MCP功能)
- 🔄 Web界面 (支持AI交互)
- 🔄 CI/CD集成插件

## 📝 更新日志

### v1.0.0 (开发中)
- 实现PHP AST解析器，支持缓存和性能优化
- 构建模块化分析框架，支持多种分析器
- 添加SQL注入检测和基础安全规则
- 实现CLI工具，支持多种输出格式
- 建立完整的测试框架，85+测试用例
- 集成性能基准测试系统，支持回归检测

## 🤝 贡献

欢迎贡献代码！请查看 [CONTRIBUTING.md](CONTRIBUTING.md) 了解详细信息。

1. Fork 项目
2. 创建功能分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

## 📄 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 🔗 相关链接

- [文档](docs/)
- [问题反馈](https://github.com/hiyco/YC-PHPCodeAnalysis-MCP/issues)
- [更新日志](CHANGELOG.md)
- [安全政策](SECURITY.md)

## 💡 鸣谢

- [nikic/php-parser](https://github.com/nikic/PHP-Parser) - PHP AST解析
- [Symfony Console](https://symfony.com/doc/current/components/console.html) - CLI框架
- [PHPUnit](https://phpunit.de/) - 测试框架
- [OWASP](https://owasp.org/) - 安全标准参考

---

**YC-PHPCodeAnalysis&MCP** - 让PHP代码更安全、更高效、更优雅，结合AI增强分析能力。
