# YC-PHPCodeAnalysis&MCP - VSCode 扩展

[English](#english) | [中文](#中文)

## 中文

**YC-PHPCodeAnalysis&MCP** 专业的 PHP 代码分析工具，集成了传统代码分析功能和AI增强能力，提供安全扫描、性能优化和智能分析功能，为 Visual Studio Code 而设计。

### 🌟 核心功能

#### 🛡️ 安全分析
- **OWASP Top 10** 漏洞检测
- 实时安全问题高亮显示
- 详细的悬浮提示和解释
- 分类整理的安全问题面板

#### ⚡ 性能分析
- 性能瓶颈检测
- 算法复杂度分析
- N+1 查询检测
- 性能指标和基准测试

#### 📊 代码质量
- 语法检查和验证
- 代码风格分析
- 最佳实践强制执行
- 详细的诊断报告

#### 🎯 交互功能
- **语法高亮**：安全问题用红色/黄色高亮
- **悬浮提示**：详细的安全和性能建议
- **状态栏**：实时分析状态和问题数量
- **树形视图**：安全、性能和基准测试结果的组织面板

### 🚀 快速开始

#### 系统要求
- Visual Studio Code 1.85.0 或更高版本
- PHP 8.0 或更高版本
- YC-PCA 分析引擎（自动检测或手动配置）

#### 安装步骤
1. 从扩展市场安装本扩展
2. 在 VS Code 中打开 PHP 项目
3. 扩展会自动激活并开始分析 PHP 文件

#### 配置选项
在 VS Code 设置中搜索 "YC-PCA" 来配置：

- `yc-pca.enabled`: 启用/禁用扩展
- `yc-pca.phpVersion`: 目标 PHP 版本 (8.0-8.3)
- `yc-pca.securityEnabled`: 启用安全扫描
- `yc-pca.performanceEnabled`: 启用性能分析
- `yc-pca.highlightingEnabled`: 启用安全问题高亮
- `yc-pca.minimumSeverity`: 显示的最低问题严重级别

### 📋 命令列表

- **YC-PCA: 分析当前文件** - 分析当前打开的 PHP 文件
- **YC-PCA: 分析工作空间** - 分析工作空间中的所有 PHP 文件
- **YC-PCA: 运行性能基准测试** - 执行性能基准测试
- **YC-PCA: 生成分析报告** - 创建综合分析报告
- **YC-PCA: 切换安全高亮显示** - 启用/禁用问题高亮
- **YC-PCA: 显示安全问题** - 打开安全问题面板
- **YC-PCA: 刷新安全分析** - 手动刷新安全分析
- **YC-PCA: 清除诊断信息** - 清除所有诊断信息

### 🔒 安全功能

#### 支持的漏洞类型
- **A01: 访问控制缺陷** - 授权绕过、IDOR
- **A02: 加密缺陷** - 弱哈希、硬编码密钥
- **A03: 注入攻击** - SQL 注入、XSS、命令注入
- **A04: 不安全设计** - 业务逻辑缺陷
- **A05: 安全配置错误** - 默认设置、信息泄露

#### 实时分析
扩展在您输入时提供即时反馈：
- 关键安全问题红色高亮
- 警告级别问题黄色高亮
- 带有详细说明和修复建议的悬浮提示

### ⚡ 性能功能

#### 性能监控
- **算法分析**：检测 O(n²) 和低效循环
- **数据库问题**：N+1 查询、低效查询
- **内存分析**：内存泄漏、过度分配
- **基准测试集成**：性能回归检测

### 📊 视图和面板

#### 安全问题面板
所有安全发现的组织视图：
- 按 OWASP 分类分组
- 基于严重程度排序
- 直接导航到问题位置

#### 性能问题面板
性能分析结果：
- 按影响级别分类
- 性能指标和建议
- 基准测试集成

#### 基准测试结果面板
性能基准测试：
- 历史性能跟踪
- 回归检测
- 详细指标和统计信息

### 📈 状态栏集成

状态栏显示：
- 当前分析状态
- 问题数量（安全/性能）
- 最后基准测试运行时间
- 快速访问菜单

### 🛠️ 故障排除

#### 扩展无法工作
1. 检查是否安装了 PHP 8.0+
2. 验证 YC-PCA 引擎是否可用
3. 检查输出面板中的扩展日志
4. 重启 VS Code

#### 未检测到问题
1. 确保文件具有 .php 扩展名
2. 检查最低严重级别设置
3. 验证安全/性能分析已启用
4. 尝试使用命令手动分析

#### 性能问题
1. 调整分析超时设置
2. 启用文件排除模式
3. 对大型项目使用工作空间级别分析

### 🤝 贡献

此扩展是 **YC-PHPCodeAnalysis&MCP** 项目的一部分。问题和贡献：
- 通过 GitHub Issues 报告错误
- 欢迎功能请求
- 接受拉取请求

### 📄 许可证

MIT 许可证 - 有关详细信息，请参阅 LICENSE 文件。

### 🆘 支持

获取支持和文档：
- GitHub 仓库
- 问题跟踪器
- 社区讨论

---

## English

Professional PHP code analysis with security scanning and performance optimization for Visual Studio Code.

### 🌟 Features

#### 🛡️ Security Analysis
- **OWASP Top 10** vulnerability detection
- Real-time security issue highlighting
- Comprehensive hover tooltips with detailed explanations
- Security issues panel with categorized findings

#### ⚡ Performance Analysis
- Performance bottleneck detection
- Algorithm complexity analysis
- N+1 query detection
- Performance metrics and benchmarking

#### 📊 Code Quality
- Syntax checking and validation
- Code style analysis
- Best practices enforcement
- Detailed diagnostic reporting

#### 🎯 Interactive Features
- **Syntax Highlighting**: Security issues highlighted in red/yellow
- **Hover Tooltips**: Detailed security and performance advice
- **Status Bar**: Real-time analysis status and issue counts
- **Tree Views**: Organized panels for security, performance, and benchmark results

### 🚀 Getting Started

#### Requirements
- Visual Studio Code 1.85.0 or higher
- PHP 8.0 or higher
- YC-PCA analysis engine (automatically detected or configured)

#### Installation
1. Install the extension from the marketplace
2. Open a PHP project in VS Code
3. The extension will automatically activate and start analyzing PHP files

#### Configuration
Open VS Code settings and search for "YC-PCA" to configure:

- `yc-pca.enabled`: Enable/disable the extension
- `yc-pca.phpVersion`: Target PHP version (8.0-8.3)
- `yc-pca.securityEnabled`: Enable security scanning
- `yc-pca.performanceEnabled`: Enable performance analysis
- `yc-pca.highlightingEnabled`: Enable security issue highlighting
- `yc-pca.minimumSeverity`: Minimum issue severity to show

### 📋 Commands

- **YC-PCA: Analyze Current File** - Analyze the currently open PHP file
- **YC-PCA: Analyze Workspace** - Analyze all PHP files in the workspace
- **YC-PCA: Run Benchmarks** - Execute performance benchmarks
- **YC-PCA: Generate Report** - Create comprehensive analysis report
- **YC-PCA: Toggle Security Highlighting** - Enable/disable issue highlighting
- **YC-PCA: Show Security Issues** - Open security issues panel
- **YC-PCA: Refresh Security Decorations** - Manually refresh security analysis
- **YC-PCA: Clear All Diagnostics** - Clear all diagnostic information

### 🔒 Security Features

#### Supported Vulnerability Types
- **A01: Broken Access Control** - Authorization bypasses, IDOR
- **A02: Cryptographic Failures** - Weak hashing, hardcoded keys
- **A03: Injection** - SQL injection, XSS, command injection
- **A04: Insecure Design** - Business logic flaws
- **A05: Security Misconfiguration** - Default settings, info disclosure

#### Real-time Analysis
The extension provides immediate feedback as you type:
- Red highlighting for critical security issues
- Yellow highlighting for warnings
- Hover tooltips with detailed explanations and fix recommendations

### ⚡ Performance Features

#### Performance Monitoring
- **Algorithm Analysis**: Detects O(n²) and inefficient loops
- **Database Issues**: N+1 queries, inefficient queries
- **Memory Analysis**: Memory leaks, excessive allocations
- **Benchmark Integration**: Performance regression detection

### 📊 Views and Panels

#### Security Issues Panel
Organized view of all security findings:
- Grouped by OWASP categories
- Severity-based sorting
- Direct navigation to issue locations

#### Performance Issues Panel
Performance analysis results:
- Categorized by impact level
- Performance metrics and suggestions
- Benchmark integration

#### Benchmark Results Panel
Performance benchmarking:
- Historical performance tracking
- Regression detection
- Detailed metrics and statistics

### 📈 Status Bar Integration

The status bar shows:
- Current analysis status
- Issue counts (security/performance)
- Last benchmark run time
- Quick access menu

### 🛠️ Troubleshooting

#### Extension Not Working
1. Check that PHP 8.0+ is installed
2. Verify YC-PCA engine is available
3. Check extension logs in Output panel
4. Restart VS Code

#### No Issues Detected
1. Ensure files have .php extension
2. Check minimum severity settings
3. Verify security/performance analysis is enabled
4. Try analyzing manually with commands

#### Performance Issues
1. Adjust analysis timeout settings
2. Enable file exclusion patterns
3. Use workspace-level analysis for large projects

### 🤝 Contributing

This extension is part of the **YC-PHPCodeAnalysis&MCP** project. For issues and contributions:
- Report bugs via GitHub Issues
- Feature requests welcome
- Pull requests accepted

### 📄 License

MIT License - see LICENSE file for details.

### 🆘 Support

For support and documentation:
- GitHub repository
- Issue tracker
- Community discussions

---

**YC-PHPCodeAnalysis&MCP** - Making PHP code more secure, performant, and maintainable.

**YC PHP 代码分析** - 让 PHP 代码更安全、更高性能、更易维护。