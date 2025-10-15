# YC-PHPCodeAnalysis&MCP

**English** | [中文](README.md)

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![MCP Protocol](https://img.shields.io/badge/MCP-Enabled-green.svg)](https://github.com/hiyco/YC-PHPCodeAnalysis-MCP)
[![VSCode Extension](https://img.shields.io/badge/VSCode-Extension-brightgreen.svg)](https://marketplace.visualstudio.com/)

> 🚀 **Professional PHP Code Analysis Platform with AI-Enhanced MCP Integration**
> It is a complete Gemini CLi tool extension integrated A comprehensive PHP static analysis tool featuring security scanning, performance optimization, and multi-AI model integration through MCP (Model Context Protocol).

---

## ✨ Key Features

### 🔍 **Advanced Static Analysis**
- **AST-based Analysis**: Deep code structure analysis using PHP-Parser
- **Multi-layered Detection**: Syntax, semantic, and security vulnerability scanning
- **Performance Profiling**: Code efficiency analysis and optimization suggestions
- **Dependency Management**: Library usage analysis and version compatibility checks

### 🛡️ **Security-First Approach**
- **OWASP Compliance**: Comprehensive security rule engine following industry standards
- **Vulnerability Detection**: SQL injection, XSS, RCE, and other common attack vectors
- **Risk Assessment**: Intelligent risk scoring and prioritization system
- **Security Reports**: Detailed security analysis with remediation guidance

### 🤖 **AI-Enhanced MCP Integration**
- **Multi-AI Support**: QWEN, DeepSeek, Doubao, ERNIE, OpenAI, Claude
- **Intelligent Code Review**: AI-powered code quality analysis and suggestions
- **Smart Optimization**: AI-driven performance enhancement recommendations
- **Context-Aware Analysis**: AI understands project structure and coding patterns

### 🎨 **Developer Experience**
- **VSCode Integration**: Seamless IDE integration with real-time analysis
- **CLI Interface**: Command-line tools for CI/CD pipeline integration
- **Web Dashboard**: Interactive web interface for comprehensive project analysis
- **API Support**: RESTful API for third-party integrations

---

## 🚀 Quick Start

### Installation

#### Method 1: Composer Installation
```bash
# Install via Composer
composer global require yc-php/code-analysis

# Verify installation
pca --version
```

#### Method 2: Manual Installation
```bash
# Clone repository
git clone https://github.com/hiyco/YC-PHPCodeAnalysis-MCP.git
cd YC-PHPCodeAnalysis-MCP

# Install dependencies
composer install

# Make executable
chmod +x bin/pca
```

#### Method 3: Docker Installation
```bash
# Pull Docker image
docker pull ghcr.io/hiyco/yc-phpcodanalysis-mcp:latest

# Run analysis
docker run --rm -v $(pwd):/app ghcr.io/hiyco/yc-phpcodanalysis-mcp:latest analyze /app
```

### Basic Usage

#### Command Line Interface
```bash
# Analyze single file
pca analyze src/User.php

# Analyze entire project
pca analyze src/ --recursive

# Security-focused analysis
pca security-scan src/ --level=strict

# Performance analysis
pca performance src/ --with-suggestions

# Generate comprehensive report
pca report src/ --format=html --output=report.html
```

#### Configuration File
Create `pca.config.json` in your project root:

```json
{
  "analysis": {
    "paths": ["src/", "app/"],
    "exclude": ["vendor/", "tests/"],
    "rules": {
      "security": "strict",
      "performance": "enabled",
      "coding_standards": "PSR-12"
    }
  },
  "mcp": {
    "enabled": true,
    "providers": ["qwen", "deepseek"],
    "features": ["code_review", "optimization_suggestions"]
  },
  "reporting": {
    "formats": ["html", "json", "sarif"],
    "output_dir": "reports/"
  }
}
```

---

## 🤖 MCP (Model Context Protocol) Integration

### Supported AI Models

| Provider | Models | Capabilities |
|----------|--------|--------------|
| **QWEN** | qwen-turbo, qwen-plus, qwen-max | Code analysis, optimization |
| **DeepSeek** | deepseek-coder, deepseek-chat | Code review, bug detection |
| **Doubao** | doubao-lite, doubao-pro | Security analysis |
| **ERNIE** | ernie-3.5, ernie-4.0 | Performance optimization |
| **OpenAI** | gpt-3.5-turbo, gpt-4 | Comprehensive analysis |
| **Claude** | claude-3-haiku, claude-3-sonnet | Code quality assessment |

### MCP Server Setup

#### 1. Start MCP Server
```bash
# Start MCP server
php bin/mcp-server.php --port=3000

# Or use Docker
docker-compose up mcp-server
```

#### 2. Configure AI Providers
```bash
# Configure QWEN
pca mcp:config --provider=qwen --api-key=YOUR_API_KEY

# Configure multiple providers
pca mcp:setup --interactive
```

#### 3. AI-Enhanced Analysis
```bash
# AI code review
pca ai:review src/UserController.php --provider=qwen

# AI performance optimization
pca ai:optimize src/ --provider=deepseek --format=suggestions

# AI security audit
pca ai:security-audit src/ --provider=claude --level=comprehensive
```

### MCP Integration Examples

#### Code Review with AI
```php
<?php
// Example: AI-powered code review
$reviewer = new AICodeReviewer('qwen');
$analysis = $reviewer->reviewFile('src/UserService.php');

echo $analysis->getSuggestions();
echo $analysis->getSecurityIssues();
echo $analysis->getPerformanceOptimizations();
```

#### Intelligent Optimization
```php
<?php
// Example: AI-driven optimization
$optimizer = new AIOptimizer(['deepseek', 'claude']);
$optimizations = $optimizer->analyzeProject('./src');

foreach ($optimizations as $optimization) {
    echo "File: {$optimization->getFile()}\n";
    echo "Issue: {$optimization->getIssue()}\n";
    echo "Solution: {$optimization->getSolution()}\n";
    echo "Priority: {$optimization->getPriority()}\n\n";
}
```

---

## 📊 Analysis Features

### Security Analysis
- **Injection Attacks**: SQL injection, XSS, Command injection detection
- **Authentication Issues**: Weak password policies, session management flaws
- **Access Control**: Privilege escalation, unauthorized access vulnerabilities
- **Data Validation**: Input sanitization and validation checks
- **Cryptography**: Weak encryption, insecure random number generation

### Performance Analysis
- **Algorithm Complexity**: Big O analysis and optimization suggestions
- **Database Optimization**: Query analysis and indexing recommendations
- **Memory Usage**: Memory leak detection and optimization
- **Caching Strategies**: Intelligent caching recommendations
- **Code Efficiency**: Loop optimization, redundant code elimination

### Code Quality
- **PSR Standards**: PSR-1, PSR-4, PSR-12 compliance checking
- **SOLID Principles**: Design pattern analysis and suggestions
- **Maintainability**: Cyclomatic complexity, code duplication detection
- **Documentation**: PHPDoc analysis and missing documentation detection
- **Testing**: Test coverage analysis and testing strategy recommendations

---

## 🎯 VSCode Extension

### Installation
```bash
# Install from VSCode Marketplace
ext install yc-php.code-analysis-mcp

# Or install from VSIX
code --install-extension yc-pca-analysis-1.0.0.vsix
```

### Features
- **Real-time Analysis**: Live code analysis as you type
- **Inline Suggestions**: AI-powered suggestions directly in editor
- **Problem Panel**: Integrated problem detection and navigation
- **Quick Fixes**: One-click fixes for common issues
- **AI Chat**: Interactive AI assistance for code questions

### Extension Settings
```json
{
  "ycPca.analysis.enabled": true,
  "ycPca.analysis.realtime": true,
  "ycPca.mcp.enabled": true,
  "ycPca.mcp.preferredProvider": "qwen",
  "ycPca.security.level": "strict",
  "ycPca.performance.enabled": true
}
```

---

## 💎 Gemini CLI Extension

### Installation
```bash
# Install from GitHub repository
gemini extensions install https://github.com/hiyco/Gemini-YC-PHPCodeAnalysis
```

### Basic Usage
```bash
# Analyze the current project
/code_review

# Get help
/help
```

---

## 🔧 Configuration & Customization

### Rule Configuration
Create custom analysis rules in `rules/custom/`:

```php
<?php
// rules/custom/CustomSecurityRule.php
class CustomSecurityRule extends AbstractSecurityRule
{
    public function analyze(Node $node): array
    {
        $issues = [];

        if ($this->detectCustomVulnerability($node)) {
            $issues[] = new SecurityIssue(
                'Custom vulnerability detected',
                $node->getStartLine(),
                SecurityLevel::HIGH
            );
        }

        return $issues;
    }
}
```

### Performance Monitoring
```bash
# Enable performance monitoring
pca performance:monitor src/ --continuous

# Generate benchmark reports
php examples/benchmark_demo.php

# View benchmark results
ls examples/benchmark_*
```

### Custom AI Models
```php
<?php
// Add custom AI model provider
class CustomAIProvider implements ModelProviderInterface
{
    public function analyze(string $code, array $options = []): AnalysisResult
    {
        // Custom AI integration logic
        return new AnalysisResult($suggestions, $issues);
    }
}

// Register custom provider
$factory = new ModelProviderFactory();
$factory->register('custom-ai', CustomAIProvider::class);
```

---

## 📈 CI/CD Integration

### GitHub Actions
```yaml
# .github/workflows/code-analysis.yml
name: PHP Code Analysis

on: [push, pull_request]

jobs:
  analysis:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v3

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'

    - name: Install PCA
      run: composer global require yc-php/code-analysis

    - name: Run Analysis
      run: |
        pca analyze src/ --format=sarif --output=results.sarif
        pca security-scan src/ --format=json --output=security.json

    - name: Upload Results
      uses: github/codeql-action/upload-sarif@v2
      with:
        sarif_file: results.sarif
```

### Jenkins Pipeline
```groovy
pipeline {
    agent any

    stages {
        stage('Code Analysis') {
            steps {
                sh 'composer install'
                sh 'pca analyze src/ --format=junit --output=analysis.xml'
                sh 'pca security-scan src/ --format=json --output=security.json'
            }

            post {
                always {
                    junit 'analysis.xml'
                    archiveArtifacts artifacts: 'security.json', fingerprint: true
                }
            }
        }
    }
}
```

---

## 🛠️ Development

### Project Structure
```
YC-PHPCodeAnalysis-MCP/
├── src/                          # Core source code
│   ├── Analysis/                 # Analysis engines
│   ├── Mcp/                     # MCP integration
│   ├── Security/                # Security rules
│   ├── Performance/             # Performance analysis
│   └── Utils/                   # Utility classes
├── bin/                         # Executable scripts
├── rules/                       # Analysis rules
├── tests/                       # Test suites
├── docs/                        # Documentation
├── examples/                    # Usage examples
├── vscode-extension/           # VSCode extension
└── docker/                     # Docker configurations
```

### Building from Source
```bash
# Clone repository
git clone https://github.com/hiyco/YC-PHPCodeAnalysis-MCP.git
cd YC-PHPCodeAnalysis-MCP

# Install dependencies
composer install
npm install

# Build project
composer build

# Run tests
composer test

# Build VSCode extension
cd vscode-extension
npm run build
npm run package
```

### Testing
```bash
# Run unit tests
phpunit tests/

# Run integration tests
phpunit tests/Integration/

# Run MCP tests
phpunit tests/Mcp/

# Generate coverage report
phpunit --coverage-html coverage/
```

---

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Guidelines
- Follow PSR-12 coding standards
- Write comprehensive tests for new features
- Update documentation for API changes
- Use semantic versioning for releases

### Bug Reports
Please use our [Issue Template](.github/ISSUE_TEMPLATE.md) when reporting bugs.

### Feature Requests
Feature requests are welcome! Please provide detailed use cases and examples.

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- **PHP-Parser**: AST parsing capabilities
- **PHPStan**: Static analysis inspiration
- **Psalm**: Type analysis concepts
- **OpenAI**: AI integration support
- **Anthropic**: Claude AI model integration

---

## 📞 Support

- **Documentation**: [https://docs.yc-php.com/pca](https://docs.yc-php.com/pca)
- **Issues**: [GitHub Issues](https://github.com/hiyco/YC-PHPCodeAnalysis-MCP/issues)
- **Discussions**: [GitHub Discussions](https://github.com/hiyco/YC-PHPCodeAnalysis-MCP/discussions)
- **Email**: yichaoling@gmail.com

---

## 🌟 Star History

[![Star History Chart](https://api.star-history.com/svg?repos=hiyco/YC-PHPCodeAnalysis-MCP&type=Date)](https://star-history.com/#hiyco/YC-PHPCodeAnalysis-MCP&Date)

---

<div align="center">

**Made with ❤️ by YC Development Team**

[⭐ Star on GitHub](https://github.com/hiyco/YC-PHPCodeAnalysis-MCP) | [📖 Documentation](https://docs.yc-php.com) | [🐛 Report Bug](https://github.com/hiyco/YC-PHPCodeAnalysis-MCP/issues)

</div>
