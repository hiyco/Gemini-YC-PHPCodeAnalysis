<?php
namespace YC\CodeAnalysis\SecurityScanner\Composer;

use YC\CodeAnalysis\Core\Contracts\ScannerInterface;
use YC\CodeAnalysis\SecurityScanner\Models\DependencyTree;
use YC\CodeAnalysis\SecurityScanner\Models\PackageInfo;

/**
 * Composer依赖扫描器
 * 
 * 负责分析composer.json/lock文件，构建依赖树，检查版本兼容性
 */
class ComposerDependencyScanner implements ScannerInterface
{
    private ComposerLockParser $lockParser;
    private ComposerJsonParser $jsonParser;
    private DependencyResolver $dependencyResolver;
    private VersionCompatibilityChecker $versionChecker;

    public function __construct(
        ComposerLockParser $lockParser,
        ComposerJsonParser $jsonParser,
        DependencyResolver $dependencyResolver,
        VersionCompatibilityChecker $versionChecker
    ) {
        $this->lockParser = $lockParser;
        $this->jsonParser = $jsonParser;
        $this->dependencyResolver = $dependencyResolver;
        $this->versionChecker = $versionChecker;
    }

    /**
     * 扫描项目依赖
     */
    public function scan(string $projectPath): array
    {
        $composerLock = $this->findComposerLock($projectPath);
        $composerJson = $this->findComposerJson($projectPath);
        
        if (!$composerLock && !$composerJson) {
            throw new \RuntimeException('No composer.json or composer.lock found');
        }

        // 解析依赖信息
        $lockData = $composerLock ? $this->lockParser->parse($composerLock) : [];
        $jsonData = $composerJson ? $this->jsonParser->parse($composerJson) : [];

        // 构建依赖树
        $dependencyTree = $this->buildDependencyTree($lockData, $jsonData);

        // 检查版本兼容性
        $compatibilityIssues = $this->checkVersionCompatibility($dependencyTree);

        // 分析依赖深度和复杂度
        $dependencyMetrics = $this->analyzeDependencyMetrics($dependencyTree);

        return [
            'dependency_tree' => $dependencyTree,
            'compatibility_issues' => $compatibilityIssues,
            'metrics' => $dependencyMetrics,
            'packages' => $this->extractPackageList($dependencyTree),
            'dev_packages' => $this->extractDevPackages($jsonData),
            'platform_requirements' => $this->extractPlatformRequirements($jsonData)
        ];
    }

    /**
     * 构建依赖树
     */
    private function buildDependencyTree(array $lockData, array $jsonData): DependencyTree
    {
        $tree = new DependencyTree();
        
        // 添加直接依赖
        foreach ($jsonData['require'] ?? [] as $package => $version) {
            $packageInfo = $this->createPackageInfo($package, $version, $lockData);
            $tree->addRootDependency($packageInfo);
        }

        // 递归构建子依赖
        foreach ($tree->getRootDependencies() as $rootPackage) {
            $this->buildSubDependencies($rootPackage, $lockData, $tree);
        }

        return $tree;
    }

    /**
     * 构建子依赖
     */
    private function buildSubDependencies(PackageInfo $package, array $lockData, DependencyTree $tree): void
    {
        $lockPackage = $this->findPackageInLock($package->getName(), $lockData);
        if (!$lockPackage) {
            return;
        }

        foreach ($lockPackage['require'] ?? [] as $depName => $depVersion) {
            if (strpos($depName, 'php') === 0 || strpos($depName, 'ext-') === 0) {
                continue; // 跳过PHP扩展
            }

            $depPackage = $this->createPackageInfo($depName, $depVersion, $lockData);
            $tree->addDependency($package, $depPackage);
            
            // 检测循环依赖
            if (!$tree->hasCircularDependency($package, $depPackage)) {
                $this->buildSubDependencies($depPackage, $lockData, $tree);
            }
        }
    }

    /**
     * 创建包信息对象
     */
    private function createPackageInfo(string $name, string $version, array $lockData): PackageInfo
    {
        $lockPackage = $this->findPackageInLock($name, $lockData);
        
        return new PackageInfo([
            'name' => $name,
            'version' => $version,
            'installed_version' => $lockPackage['version'] ?? null,
            'description' => $lockPackage['description'] ?? null,
            'license' => $lockPackage['license'] ?? [],
            'authors' => $lockPackage['authors'] ?? [],
            'homepage' => $lockPackage['homepage'] ?? null,
            'source' => $lockPackage['source'] ?? null,
            'dist' => $lockPackage['dist'] ?? null,
            'autoload' => $lockPackage['autoload'] ?? null,
            'require' => $lockPackage['require'] ?? [],
            'require_dev' => $lockPackage['require-dev'] ?? []
        ]);
    }

    /**
     * 检查版本兼容性
     */
    private function checkVersionCompatibility(DependencyTree $tree): array
    {
        $issues = [];
        
        foreach ($tree->getAllPackages() as $package) {
            // 检查版本约束冲突
            $conflicts = $this->versionChecker->checkConflicts($package, $tree);
            if (!empty($conflicts)) {
                $issues[] = [
                    'type' => 'version_conflict',
                    'package' => $package->getName(),
                    'conflicts' => $conflicts,
                    'severity' => 'high'
                ];
            }

            // 检查过时版本
            $outdatedInfo = $this->versionChecker->checkOutdated($package);
            if ($outdatedInfo['is_outdated']) {
                $issues[] = [
                    'type' => 'outdated_version',
                    'package' => $package->getName(),
                    'current' => $package->getInstalledVersion(),
                    'latest' => $outdatedInfo['latest_version'],
                    'severity' => $this->calculateOutdatedSeverity($outdatedInfo)
                ];
            }

            // 检查不安全版本
            $securityInfo = $this->versionChecker->checkSecurity($package);
            if ($securityInfo['has_vulnerabilities']) {
                $issues[] = [
                    'type' => 'security_vulnerability',
                    'package' => $package->getName(),
                    'vulnerabilities' => $securityInfo['vulnerabilities'],
                    'severity' => 'critical'
                ];
            }
        }

        return $issues;
    }

    /**
     * 分析依赖指标
     */
    private function analyzeDependencyMetrics(DependencyTree $tree): array
    {
        return [
            'total_packages' => count($tree->getAllPackages()),
            'direct_dependencies' => count($tree->getRootDependencies()),
            'max_depth' => $tree->getMaxDepth(),
            'circular_dependencies' => $tree->getCircularDependencies(),
            'duplicate_packages' => $tree->getDuplicatePackages(),
            'security_risk_score' => $this->calculateSecurityRiskScore($tree),
            'maintenance_risk_score' => $this->calculateMaintenanceRiskScore($tree),
            'license_compliance_score' => $this->calculateLicenseComplianceScore($tree)
        ];
    }

    /**
     * 计算安全风险评分
     */
    private function calculateSecurityRiskScore(DependencyTree $tree): float
    {
        $totalPackages = count($tree->getAllPackages());
        $vulnerablePackages = 0;
        $totalVulnerabilities = 0;
        
        foreach ($tree->getAllPackages() as $package) {
            $vulnerabilities = $this->getPackageVulnerabilities($package);
            if (!empty($vulnerabilities)) {
                $vulnerablePackages++;
                $totalVulnerabilities += count($vulnerabilities);
            }
        }
        
        if ($totalPackages === 0) {
            return 0.0;
        }
        
        $vulnerabilityRatio = $vulnerablePackages / $totalPackages;
        $averageVulnerabilities = $totalVulnerabilities / $totalPackages;
        
        // 风险评分公式：(漏洞包比例 * 0.6 + 平均漏洞数 * 0.4) * 100
        return min(100, ($vulnerabilityRatio * 60) + ($averageVulnerabilities * 4));
    }

    /**
     * 计算维护风险评分
     */
    private function calculateMaintenanceRiskScore(DependencyTree $tree): float
    {
        $totalPackages = count($tree->getAllPackages());
        $outdatedPackages = 0;
        $abandonedPackages = 0;
        
        foreach ($tree->getAllPackages() as $package) {
            if ($this->isPackageOutdated($package)) {
                $outdatedPackages++;
            }
            if ($this->isPackageAbandoned($package)) {
                $abandonedPackages++;
            }
        }
        
        if ($totalPackages === 0) {
            return 0.0;
        }
        
        $outdatedRatio = $outdatedPackages / $totalPackages;
        $abandonedRatio = $abandonedPackages / $totalPackages;
        
        return min(100, ($outdatedRatio * 50) + ($abandonedRatio * 50));
    }

    /**
     * 在composer.lock中查找包
     */
    private function findPackageInLock(string $packageName, array $lockData): ?array
    {
        foreach ($lockData['packages'] ?? [] as $package) {
            if ($package['name'] === $packageName) {
                return $package;
            }
        }
        return null;
    }

    /**
     * 查找composer文件
     */
    private function findComposerLock(string $projectPath): ?string
    {
        $lockFile = rtrim($projectPath, '/') . '/composer.lock';
        return file_exists($lockFile) ? $lockFile : null;
    }

    /**
     * 查找composer.json
     */
    private function findComposerJson(string $projectPath): ?string
    {
        $jsonFile = rtrim($projectPath, '/') . '/composer.json';
        return file_exists($jsonFile) ? $jsonFile : null;
    }

    /**
     * 提取包列表
     */
    private function extractPackageList(DependencyTree $tree): array
    {
        return array_map(function ($package) {
            return [
                'name' => $package->getName(),
                'version' => $package->getVersion(),
                'installed_version' => $package->getInstalledVersion(),
                'license' => $package->getLicense(),
                'description' => $package->getDescription()
            ];
        }, $tree->getAllPackages());
    }

    /**
     * 提取开发依赖
     */
    private function extractDevPackages(array $jsonData): array
    {
        return $jsonData['require-dev'] ?? [];
    }

    /**
     * 提取平台要求
     */
    private function extractPlatformRequirements(array $jsonData): array
    {
        $requirements = [];
        foreach ($jsonData['require'] ?? [] as $package => $version) {
            if (strpos($package, 'php') === 0 || strpos($package, 'ext-') === 0) {
                $requirements[$package] = $version;
            }
        }
        return $requirements;
    }

    /**
     * 计算过时严重程度
     */
    private function calculateOutdatedSeverity(array $outdatedInfo): string
    {
        $monthsOld = $outdatedInfo['months_old'] ?? 0;
        $majorVersionsBehind = $outdatedInfo['major_versions_behind'] ?? 0;
        
        if ($majorVersionsBehind >= 2 || $monthsOld >= 24) {
            return 'critical';
        } elseif ($majorVersionsBehind >= 1 || $monthsOld >= 12) {
            return 'high';
        } elseif ($monthsOld >= 6) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * 获取包的漏洞信息
     */
    private function getPackageVulnerabilities(PackageInfo $package): array
    {
        // 这里会调用漏洞扫描器来获取具体的漏洞信息
        // 实现细节在漏洞扫描器中
        return [];
    }

    /**
     * 检查包是否过时
     */
    private function isPackageOutdated(PackageInfo $package): bool
    {
        $outdatedInfo = $this->versionChecker->checkOutdated($package);
        return $outdatedInfo['is_outdated'];
    }

    /**
     * 检查包是否被遗弃
     */
    private function isPackageAbandoned(PackageInfo $package): bool
    {
        // 检查包是否在维护者声明的abandoned状态
        // 或者长时间没有更新
        return false; // 简化实现
    }
}