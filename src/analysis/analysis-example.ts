/**
 * PHP代码分析引擎使用示例
 * 
 * 展示如何使用完整的分析引擎进行高性能PHP代码分析
 */

import { PhpAnalysisEngine, AnalysisContext, PhpAnalysisConfig } from './php-analysis-engine';
import { CacheManager } from './performance/cache-manager';
import { WorkerPool } from './performance/worker-pool';

// 示例PHP代码
const samplePhpCode = `<?php
namespace App\\Services;

use App\\Models\\User;
use Illuminate\\Database\\Eloquent\\Collection;

class UserService
{
    private $userRepository;
    
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    
    /**
     * 获取所有活跃用户
     * 
     * @return Collection<User>
     */
    public function getActiveUsers(): Collection
    {
        // 潜在的N+1查询问题
        $users = $this->userRepository->all();
        
        foreach ($users as $user) {
            // 每次循环都会执行查询
            $user->posts = $user->posts()->get();
        }
        
        return $users->filter(function ($user) {
            return $user->isActive();
        });
    }
    
    /**
     * 创建新用户
     * 
     * @param array $data
     * @return User
     * @throws \\InvalidArgumentException
     */
    public function createUser(array $data): User
    {
        // 输入验证缺失 - 安全问题
        if (empty($data['email'])) {
            throw new \\InvalidArgumentException('Email is required');
        }
        
        // SQL注入风险 - 直接字符串拼接
        $query = "SELECT * FROM users WHERE email = '" . $data['email'] . "'";
        
        // 未使用的变量
        $unusedVariable = "This variable is never used";
        
        $user = new User();
        $user->fill($data);
        $user->save();
        
        return $user;
    }
    
    /**
     * 复杂度过高的方法示例
     */
    public function complexMethod($input)
    {
        if ($input === null) {
            if (isset($_GET['type'])) {
                if ($_GET['type'] === 'admin') {
                    if (auth()->check()) {
                        if (auth()->user()->isAdmin()) {
                            for ($i = 0; $i < 10; $i++) {
                                if ($i % 2 === 0) {
                                    foreach ($input as $item) {
                                        if ($item->status === 'active') {
                                            // 嵌套过深
                                            return true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return false;
    }
}

class UserRepository
{
    public function all()
    {
        return User::all();
    }
    
    // 方法参数过多
    public function createUser(
        string $name, 
        string $email, 
        string $password,
        string $firstName,
        string $lastName,
        string $phone,
        string $address,
        string $city,
        string $country,
        string $postalCode
    ) {
        // 实现逻辑
    }
}
?>`;

/**
 * 基础使用示例
 */
export async function basicUsageExample(): Promise<void> {
  console.log('=== PHP代码分析引擎基础使用示例 ===\n');
  
  // 1. 创建分析引擎配置
  const config: Partial<PhpAnalysisConfig> = {
    phpVersion: '8.3',
    maxWorkers: 4,
    memoryLimit: 256, // 256MB
    cacheSize: 64,    // 64MB
    enableSyntaxAnalysis: true,
    enableSemanticAnalysis: true,
    enableQualityAnalysis: true,
    enableSecurityAnalysis: true,
    enablePerformanceAnalysis: true,
    enableIncrementalAnalysis: true
  };

  // 2. 初始化分析引擎
  const engine = new PhpAnalysisEngine(config);
  
  // 3. 监听分析事件
  engine.on('engineInitialized', () => {
    console.log('分析引擎初始化完成');
  });
  
  engine.on('memoryLimitExceeded', (memUsage) => {
    console.warn('内存使用超限:', memUsage);
  });

  try {
    // 4. 创建分析上下文
    const context: AnalysisContext = {
      projectRoot: '/path/to/project',
      phpVersion: '8.3',
      frameworks: ['laravel'],
      excludePatterns: ['vendor/*', 'node_modules/*'],
      includePatterns: ['app/**/*.php', 'src/**/*.php']
    };

    // 5. 执行单文件分析
    console.log('开始分析PHP文件...');
    const result = await engine.analyzeFile(
      'app/Services/UserService.php',
      samplePhpCode,
      context
    );

    // 6. 输出分析结果
    console.log('\\n=== 分析结果 ===');
    console.log(`文件: ${result.filePath}`);
    console.log(`分析耗时: ${result.duration.toFixed(2)}ms`);
    console.log(`内存使用: ${(result.memoryUsage / 1024 / 1024).toFixed(2)}MB`);
    console.log();

    // 语法分析结果
    console.log('语法分析:');
    console.log(`- 语法有效: ${result.syntax.valid ? '✓' : '✗'}`);
    console.log(`- 错误数量: ${result.syntax.errors.length}`);
    console.log(`- 警告数量: ${result.syntax.warnings.length}`);
    console.log(`- AST节点数: ${result.syntax.nodeCount}`);
    console.log(`- 复杂度: ${result.syntax.complexity}`);
    console.log();

    // 语义分析结果
    if (result.semantic) {
      console.log('语义分析:');
      console.log(`- 类数量: ${result.semantic.classes.length}`);
      console.log(`- 函数数量: ${result.semantic.functions.length}`);
      console.log(`- 变量数量: ${result.semantic.variables.length}`);
      console.log(`- 类型推断: ${result.semantic.typeInferences.length}`);
      console.log();
    }

    // 代码质量分析
    if (result.quality) {
      console.log('代码质量:');
      console.log(`- 圈复杂度: ${result.quality.cyclomaticComplexity}`);
      console.log(`- 认知复杂度: ${result.quality.cognitiveComplexity}`);
      console.log(`- 可维护性指数: ${result.quality.maintainabilityIndex}`);
      console.log(`- 代码异味: ${result.quality.codeSmells.length}`);
      console.log();
    }

    // 安全分析
    if (result.security) {
      console.log('安全分析:');
      console.log(`- 漏洞数量: ${result.security.vulnerabilities.length}`);
      console.log(`- 风险评分: ${result.security.riskScore}/100`);
      console.log(`- 安全热点: ${result.security.securityHotspots.length}`);
      console.log();
    }

    // 性能分析
    if (result.performance) {
      console.log('性能分析:');
      console.log(`- 性能瓶颈: ${result.performance.bottlenecks.length}`);
      console.log(`- 内存热点: ${result.performance.memoryHotspots.length}`);
      console.log(`- 优化建议: ${result.performance.optimizationSuggestions.length}`);
      console.log();
    }

    // 建议和指标
    console.log('修复建议:');
    result.suggestions.slice(0, 5).forEach((suggestion, index) => {
      console.log(`${index + 1}. [${suggestion.severity.toUpperCase()}] ${suggestion.message}`);
      console.log(`   位置: 第${suggestion.startLine}行`);
      if (suggestion.fixable) {
        console.log(`   可自动修复: ✓`);
      }
      console.log();
    });

    console.log('整体指标:');
    console.log(`- 代码行数: ${result.metrics.linesOfCode}`);
    console.log(`- 逻辑代码行数: ${result.metrics.logicalLinesOfCode}`);
    console.log(`- 注释比例: ${(result.metrics.commentRatio * 100).toFixed(1)}%`);
    console.log(`- 技术债务: ${result.metrics.technicalDebt}分钟`);
    console.log(`- 可维护性评分: ${result.metrics.maintainabilityScore}/100`);

  } catch (error) {
    console.error('分析过程中出现错误:', error);
  } finally {
    // 7. 获取性能统计
    const stats = engine.getStats();
    console.log('\\n=== 引擎性能统计 ===');
    console.log(`已分析文件: ${stats.filesAnalyzed}`);
    console.log(`平均分析时间: ${stats.averageAnalysisTime.toFixed(2)}ms`);
    console.log(`缓存命中率: ${(stats.cacheHitRate * 100).toFixed(1)}%`);
    console.log(`AST缓存大小: ${stats.astCacheSize}`);
    console.log(`结果缓存大小: ${stats.resultCacheSize}`);
    console.log(`内存使用: ${(stats.memoryUsage / 1024 / 1024).toFixed(2)}MB`);

    // 8. 清理资源
    await engine.destroy();
    console.log('\\n分析引擎已清理完成');
  }
}

/**
 * 批量分析示例
 */
export async function batchAnalysisExample(): Promise<void> {
  console.log('\\n=== 批量分析示例 ===\\n');

  const engine = new PhpAnalysisEngine({
    maxWorkers: 6,
    memoryLimit: 512,
    enableCache: true
  });

  const files = [
    { path: 'app/Models/User.php', content: '<?php class User extends Model {} ?>' },
    { path: 'app/Services/UserService.php', content: samplePhpCode },
    { path: 'app/Controllers/UserController.php', content: '<?php class UserController {} ?>' },
    { path: 'app/Repositories/UserRepository.php', content: '<?php class UserRepository {} ?>' }
  ];

  const context: AnalysisContext = {
    projectRoot: '/path/to/project',
    phpVersion: '8.3',
    frameworks: ['laravel'],
    excludePatterns: [],
    includePatterns: ['**/*.php']
  };

  try {
    console.log(`开始批量分析 ${files.length} 个文件...`);
    const startTime = Date.now();
    
    const results = await engine.analyzeFiles(files, context);
    
    const endTime = Date.now();
    const totalTime = endTime - startTime;

    console.log('\\n批量分析完成!');
    console.log(`总耗时: ${totalTime}ms`);
    console.log(`平均每文件: ${(totalTime / files.length).toFixed(2)}ms`);
    console.log(`吞吐量: ${((files.length / totalTime) * 1000).toFixed(2)} 文件/秒`);

    // 汇总统计
    let totalErrors = 0;
    let totalWarnings = 0;
    let totalSuggestions = 0;

    results.forEach((result, index) => {
      console.log(`\\n文件 ${index + 1}: ${result.filePath}`);
      console.log(`  错误: ${result.syntax.errors.length}`);
      console.log(`  警告: ${result.syntax.warnings.length}`);
      console.log(`  建议: ${result.suggestions.length}`);
      console.log(`  耗时: ${result.duration.toFixed(2)}ms`);
      
      totalErrors += result.syntax.errors.length;
      totalWarnings += result.syntax.warnings.length;
      totalSuggestions += result.suggestions.length;
    });

    console.log('\\n=== 汇总统计 ===');
    console.log(`总错误数: ${totalErrors}`);
    console.log(`总警告数: ${totalWarnings}`);
    console.log(`总建议数: ${totalSuggestions}`);

  } catch (error) {
    console.error('批量分析失败:', error);
  } finally {
    await engine.destroy();
  }
}

/**
 * 高级功能示例
 */
export async function advancedFeaturesExample(): Promise<void> {
  console.log('\\n=== 高级功能示例 ===\\n');

  // 1. 缓存管理器示例
  console.log('1. 缓存管理器示例');
  const cacheManager = new CacheManager({
    memorySize: 64,
    persistentCache: true,
    cacheDirectory: './temp-cache',
    enableCompression: true,
    enableStatistics: true
  });

  // 设置和获取缓存
  await cacheManager.set('test-key', { data: 'test-value', timestamp: Date.now() });
  const cachedValue = await cacheManager.get('test-key');
  console.log('缓存测试:', cachedValue ? '成功' : '失败');

  const cacheStats = cacheManager.getStatistics();
  console.log('缓存统计:', {
    内存命中率: `${(cacheStats.memoryCache.hitRate * 100).toFixed(1)}%`,
    整体命中率: `${(cacheStats.overall.overallHitRate * 100).toFixed(1)}%`,
    条目数量: cacheStats.memoryCache.entryCount
  });

  // 2. 工作线程池示例
  console.log('\\n2. 工作线程池示例');
  const workerPool = new WorkerPool({
    minWorkers: 2,
    maxWorkers: 4,
    enableMonitoring: true
  });

  try {
    // 添加分析任务
    const analysisTask = workerPool.addTask('fullAnalysis', {
      code: samplePhpCode,
      filePath: 'test.php',
      sourceCode: samplePhpCode,
      phpVersion: '8.3'
    });

    const result = await analysisTask;
    console.log('工作线程分析完成:', result ? '成功' : '失败');

    const poolStats = workerPool.getStats();
    console.log('线程池统计:', {
      活跃线程: poolStats.activeWorkers,
      空闲线程: poolStats.idleWorkers,
      完成任务: poolStats.completedTasks,
      平均执行时间: `${poolStats.averageExecutionTime.toFixed(2)}ms`,
      吞吐量: `${poolStats.throughput.toFixed(2)} 任务/秒`
    });

  } catch (error) {
    console.error('工作线程池测试失败:', error);
  }

  // 清理资源
  await cacheManager.destroy();
  await workerPool.destroy();
  
  console.log('\\n高级功能示例完成');
}

/**
 * 性能基准测试
 */
export async function performanceBenchmark(): Promise<void> {
  console.log('\\n=== 性能基准测试 ===\\n');

  const testSizes = [1, 10, 50, 100];
  
  for (const size of testSizes) {
    console.log(`测试 ${size} 个文件的分析性能...`);
    
    const engine = new PhpAnalysisEngine({
      maxWorkers: Math.min(8, size),
      memoryLimit: 512,
      enableCache: true
    });

    // 生成测试文件
    const testFiles = Array.from({ length: size }, (_, index) => ({
      path: `test-file-${index}.php`,
      content: samplePhpCode.replace('UserService', `UserService${index}`)
    }));

    const context: AnalysisContext = {
      projectRoot: '/benchmark',
      phpVersion: '8.3',
      frameworks: ['laravel'],
      excludePatterns: [],
      includePatterns: ['**/*.php']
    };

    const startTime = performance.now();
    const startMemory = process.memoryUsage().heapUsed;

    try {
      const results = await engine.analyzeFiles(testFiles, context);
      
      const endTime = performance.now();
      const endMemory = process.memoryUsage().heapUsed;
      
      const duration = endTime - startTime;
      const memoryUsed = endMemory - startMemory;
      const throughput = (size / duration) * 1000;

      console.log(`  文件数: ${size}`);
      console.log(`  总耗时: ${duration.toFixed(2)}ms`);
      console.log(`  平均耗时: ${(duration / size).toFixed(2)}ms/文件`);
      console.log(`  内存使用: ${(memoryUsed / 1024 / 1024).toFixed(2)}MB`);
      console.log(`  吞吐量: ${throughput.toFixed(2)} 文件/秒`);
      console.log(`  错误总数: ${results.reduce((sum, r) => sum + r.syntax.errors.length, 0)}`);
      console.log(`  建议总数: ${results.reduce((sum, r) => sum + r.suggestions.length, 0)}`);
      
      const engineStats = engine.getStats();
      console.log(`  缓存命中率: ${(engineStats.cacheHitRate * 100).toFixed(1)}%`);
      console.log();

    } catch (error) {
      console.error(`  测试 ${size} 个文件时出错:`, error);
    } finally {
      await engine.destroy();
    }
  }

  console.log('性能基准测试完成');
}

/**
 * 运行所有示例
 */
export async function runAllExamples(): Promise<void> {
  console.log('PHP代码分析引擎 - 完整示例演示');
  console.log('=====================================\\n');

  try {
    await basicUsageExample();
    await batchAnalysisExample();
    await advancedFeaturesExample();
    await performanceBenchmark();
    
    console.log('\\n所有示例运行完成! 🎉');
  } catch (error) {
    console.error('示例运行过程中出现错误:', error);
  }
}

// 如果直接运行此文件，执行所有示例
if (require.main === module) {
  runAllExamples().catch(console.error);
}