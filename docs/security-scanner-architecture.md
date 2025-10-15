# YC-PHPCodeAnalysis&MCP 安全扫描模块架构文档

## 系统概述

本安全扫描模块为 **YC-PHPCodeAnalysis&MCP** 项目设计的专业级开源库和数据库安全检测系统，集成AI增强分析能力，提供全方位的安全风险评估和修复建议。

### 核心功能

1. **开源库扫描**
   - Composer依赖分析和漏洞检测
   - 许可证合规性检查
   - 供应链安全分析
   - 过期依赖识别和升级建议

2. **数据库安全检测**
   - SQL注入漏洞检测
   - 查询性能分析
   - 连接管理优化
   - 敏感数据保护

3. **风险评估和报告**
   - 多维度风险评分
   - 业务影响分析
   - 修复优先级排序
   - 合规状态监控

## 系统架构

### 架构分层设计

```
┌─────────────────────────────────────────────────────────────┐
│                    表现层 (Presentation Layer)                │
├─────────────────────────────────────────────────────────────┤
│  CLI命令     │  Web API      │  报告生成器    │  监控面板     │
├─────────────────────────────────────────────────────────────┤
│                    应用层 (Application Layer)                 │
├─────────────────────────────────────────────────────────────┤
│ 安全扫描管理器 │ 风险评估引擎 │ 扫描调度器    │ 配置管理     │
├─────────────────────────────────────────────────────────────┤
│                     领域层 (Domain Layer)                    │
├─────────────────────────────────────────────────────────────┤
│ 依赖扫描器  │ 漏洞检测器   │ 数据库扫描器  │ 许可证扫描器  │
│ 供应链扫描器│ SQL注入检测器│ 性能分析器    │ 敏感数据检测器 │
├─────────────────────────────────────────────────────────────┤
│                   基础设施层 (Infrastructure Layer)           │
├─────────────────────────────────────────────────────────────┤
│ 数据库连接  │ 外部API集成  │ 缓存系统      │ 日志记录     │
│ CVE数据库   │ NVD连接器    │ Snyk集成      │ 文件系统     │
└─────────────────────────────────────────────────────────────┘
```

### 核心组件

#### 1. 安全扫描管理器 (SecurityScannerManager)
- **职责**: 协调和管理所有扫描模块
- **特性**: 
  - 并行扫描支持
  - 扫描进度跟踪
  - 结果聚合和报告生成
  - 错误恢复和重试机制

#### 2. 依赖扫描器 (ComposerDependencyScanner)
- **职责**: 分析Composer依赖树，检测版本兼容性
- **特性**:
  - 递归依赖分析
  - 循环依赖检测
  - 版本冲突识别
  - 过期包检测

#### 3. 漏洞数据库管理器 (VulnerabilityDatabaseManager)
- **职责**: 集成多个漏洞数据源，提供统一查询接口
- **特性**:
  - 多数据源集成 (CVE, NVD, Snyk)
  - 增量更新机制
  - 智能缓存策略
  - 漏洞评分规范化

#### 4. 数据库安全扫描器 (DatabaseSecurityScanner)
- **职责**: 检测数据库相关的安全问题
- **特性**:
  - SQL注入模式识别
  - 查询性能分析
  - 硬编码凭据检测
  - ORM最佳实践检查

#### 5. 风险评估系统 (SecurityRiskAssessment)
- **职责**: 综合分析安全风险，生成评估报告
- **特性**:
  - 多维度风险计算
  - 业务影响评估
  - 修复建议优先级排序
  - 趋势分析和预测

## 技术实现

### 漏洞检测算法

#### SQL注入检测算法
```php
// 检测危险模式
$injectionPatterns = [
    '/(\s|^)(union\s+select)/i',
    '/(\s|^)(select\s+.*\s+from)/i',
    '/(\s|^)(drop\s+table)/i',
    // ... 更多模式
];

// 风险评分算法
$riskScore = 0;
if ($containsUserInput) $riskScore += 3;
if ($isStringConcatenation) $riskScore += 2;
if ($lacksValidation) $riskScore += 2;
```

#### 依赖风险评分算法
```php
// 综合风险评分
$riskScore = ($vulnerabilityRatio * 60) + ($averageVulnerabilities * 4);
$securityRisk = min(100, $riskScore);

// 维护风险计算
$maintenanceRisk = ($outdatedRatio * 50) + ($abandonedRatio * 50);
```

### 性能优化策略

#### 1. 并行扫描
- 使用PHP的pcntl扩展实现进程并行
- 独立扫描模块，避免资源竞争
- 临时文件交换扫描结果

#### 2. 智能缓存
- 漏洞数据缓存 (1小时TTL)
- 依赖分析结果缓存
- 增量更新策略

#### 3. 批量操作
- 数据库批量插入 (1000条/批次)
- API请求限流 (5次/秒)
- 内存优化和垃圾回收

## 数据库设计

### 核心表结构

```sql
-- 漏洞数据表
CREATE TABLE vulnerabilities (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    source VARCHAR(50) NOT NULL,
    cve_id VARCHAR(20) NOT NULL,
    package_name VARCHAR(255) NOT NULL,
    affected_versions JSON,
    severity ENUM('critical','high','medium','low','info'),
    title TEXT,
    description TEXT,
    published_date DATETIME,
    modified_date DATETIME,
    cvss_score DECIMAL(3,1),
    cvss_vector VARCHAR(255),
    cwe_ids JSON,
    references JSON,
    raw_data JSON,
    INDEX idx_package_name (package_name),
    INDEX idx_cve_id (cve_id),
    INDEX idx_severity (severity),
    INDEX idx_published_date (published_date)
);

-- 扫描历史表
CREATE TABLE security_scans (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    scan_id VARCHAR(64) UNIQUE NOT NULL,
    project_path VARCHAR(1024) NOT NULL,
    config JSON,
    status ENUM('running','completed','failed'),
    report JSON,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_scan_id (scan_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- 漏洞数据源更新记录
CREATE TABLE vulnerability_sources (
    source_name VARCHAR(50) PRIMARY KEY,
    last_update TIMESTAMP,
    update_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    status ENUM('active','inactive','error') DEFAULT 'active'
);
```

## 安全最佳实践

### 1. 数据验证和过滤
```php
// 输入验证
$projectPath = realpath($input);
if (!$projectPath || !is_dir($projectPath)) {
    throw new InvalidArgumentException('无效的项目路径');
}

// SQL注入防护
$stmt = $pdo->prepare('SELECT * FROM vulnerabilities WHERE package_name = ?');
$stmt->execute([$packageName]);
```

### 2. 错误处理和日志
```php
try {
    $result = $scanner->scan($projectPath);
} catch (SecurityException $e) {
    $this->logger->error('安全扫描失败', [
        'project' => $projectPath,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    throw $e;
}
```

### 3. 权限管理
- 最小权限原则
- 敏感文件访问控制
- API访问令牌验证

### 4. 数据加密
- 敏感配置加密存储
- 传输层TLS保护
- 日志数据脱敏

## 部署和配置

### 系统要求
- PHP 8.0+
- MySQL 8.0+ 或 PostgreSQL 12+
- Redis (可选，用于缓存)
- 足够的磁盘空间存储漏洞数据库

### 安装步骤

1. **依赖安装**
```bash
composer install --no-dev --optimize-autoloader
```

2. **数据库初始化**
```bash
php analyzer db:migrate
php analyzer security:update-db
```

3. **配置文件设置**
```json
{
    "security_scanner": {
        "enable_parallel_scanning": true,
        "max_scan_time": 1800,
        "vulnerability_sources": {
            "cve": {"enabled": true, "update_frequency": "daily"},
            "nvd": {"enabled": true, "update_frequency": "daily"},
            "snyk": {"enabled": true, "api_key": "your_api_key"}
        },
        "cache": {
            "driver": "redis",
            "ttl": 3600
        }
    }
}
```

4. **定时任务配置**
```bash
# 每日更新漏洞数据库
0 2 * * * php /path/to/analyzer security:scan --update-db

# 每周生成项目安全报告
0 1 * * 0 php /path/to/analyzer security:scan /path/to/project --format=json --output=/reports/weekly.json
```

## 使用示例

### CLI命令行使用

```bash
# 基本扫描
php analyzer security:scan /path/to/project

# 指定扫描模块
php analyzer security:scan --modules=dependency,database /path/to/project

# 并行扫描并输出JSON报告
php analyzer security:scan --parallel --format=json --output=report.json /path/to/project

# 更新漏洞数据库
php analyzer security:scan --update-db
```

### 编程接口使用

```php
use YC\CodeAnalysis\SecurityScanner\Core\SecurityScannerManager;

$scanner = $container->get(SecurityScannerManager::class);

// 配置扫描选项
$scanner->setConfig([
    'enable_parallel_scanning' => true,
    'modules' => ['dependency', 'database', 'license']
]);

// 执行扫描
$report = $scanner->scanProject('/path/to/project');

// 获取风险评估
$riskLevel = $report['summary']['overall_risk_level'];
$securityScore = $report['summary']['security_score'];

// 处理建议
foreach ($report['recommendations'] as $recommendation) {
    echo "优先级: {$recommendation['priority']}\n";
    echo "建议: {$recommendation['action']}\n";
}
```

## 监控和维护

### 监控指标
- 扫描成功率和失败率
- 漏洞数据库更新状态
- 平均扫描时间
- 系统资源使用情况

### 维护任务
- 定期更新漏洞数据库
- 清理过期的扫描记录
- 优化数据库索引
- 监控磁盘空间使用

### 故障排除
- 检查数据库连接
- 验证API访问权限
- 查看系统日志
- 测试网络连接

## 扩展开发

### 添加新的扫描器
1. 实现 `ScannerInterface` 接口
2. 注册到依赖注入容器
3. 配置到扫描管理器
4. 添加相应的测试用例

### 集成新的漏洞数据源
1. 实现 `VulnerabilitySourceInterface` 接口
2. 添加数据规范化处理
3. 配置更新调度
4. 测试数据质量

这个安全扫描模块提供了企业级的安全检测能力，具备良好的扩展性和维护性，能够有效识别和评估PHP项目中的安全风险，为开发团队提供及时、准确的安全指导。