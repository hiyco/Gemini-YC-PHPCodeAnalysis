# 贡献指南

感谢您对 **YC-PHPCodeAnalysis&MCP** 项目的关注和贡献！

## 🎯 贡献方式

### 🐛 报告问题
- 使用 [GitHub Issues](https://github.com/hiyco/YC-PHPCodeAnalysis-MCP/issues) 报告 Bug
- 详细描述问题的重现步骤
- 提供环境信息（PHP版本、操作系统等）

### 💡 功能建议
- 在 Issues 中提交功能请求
- 说明功能的使用场景和价值
- 欢迎提供设计思路

### 🔧 代码贡献

#### 开发环境设置
```bash
# 克隆项目
git clone https://github.com/hiyco/YC-PHPCodeAnalysis-MCP.git
cd YC-PHPCodeAnalysis-MCP

# 安装依赖
composer install

# 复制环境配置
cp .env.example .env

# 运行测试
composer test
```

#### Pull Request 流程
1. Fork 本项目
2. 创建功能分支：`git checkout -b feature/your-feature`
3. 提交更改：`git commit -am 'Add your feature'`
4. 推送分支：`git push origin feature/your-feature`
5. 创建 Pull Request

## 📋 开发规范

### 代码风格
- 遵循 PSR-12 编码标准
- 使用有意义的变量和函数命名
- 添加适当的注释和文档

### 测试要求
- 为新功能添加相应的测试
- 确保所有测试通过
- 维持或提高代码覆盖率

### 提交信息规范
```
type(scope): description

Types: feat, fix, docs, style, refactor, test, chore
Examples:
- feat(mcp): add QWEN model support
- fix(security): resolve SQL injection vulnerability
- docs(readme): update installation instructions
```

## 🔍 代码审查

Pull Request 将经过以下检查：
- ✅ 自动化测试通过
- ✅ 代码风格检查通过
- ✅ 安全扫描通过
- ✅ 功能验证
- ✅ 文档更新

## 🤝 社区准则

### 行为准则
- 尊重所有贡献者
- 建设性的讨论和反馈
- 包容和友好的交流环境

### 沟通渠道
- **GitHub Issues**: 问题报告和功能讨论
- **GitHub Discussions**: 一般性讨论和问答
- **Email**: 私人联系或安全问题报告

## 📚 开发文档

### 架构文档
- [MCP服务器架构](mcp-server-architecture.md)
- [安全扫描模块](docs/security-scanner-architecture.md)
- [VSCode扩展架构](docs/vscode-extension-architecture.md)

### API文档
- [MCP SDK使用指南](docs/MCP-SDK-USAGE.md)
- [核心API参考](docs/api-reference.md)

## 🏆 贡献者认可

我们重视每一位贡献者的努力：
- 代码贡献者将被列入 Contributors 列表
- 重要贡献将在 Release Notes 中特别感谢
- 持续贡献者可获得项目维护者权限

## 📞 联系我们

- **项目维护者**: YC Team
- **技术问题**: 通过 GitHub Issues
- **安全问题**: yichaoling@gmail.com
- **商务合作**: yichaoling@gmail.com

---

再次感谢您的贡献！让我们一起构建更好的PHP代码分析工具。 🚀