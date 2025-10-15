<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Main CLI command for PHP code analysis
 */

namespace YcPca\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use YcPca\Analysis\AnalysisEngine;
use YcPca\Analysis\Analyzer\SecurityAnalyzer;
use YcPca\Analysis\Analyzer\SyntaxAnalyzer;
use YcPca\Analysis\Security\SecurityRuleEngine;
use YcPca\Analysis\Security\Rule\SqlInjectionRule;
use YcPca\Analysis\Syntax\SyntaxRuleEngine;
use YcPca\Ast\PhpAstParser;
use YcPca\Model\FileContext;
use YcPca\Report\ReportGenerator;

/**
 * CLI command for analyzing PHP code
 * 
 * Features:
 * - Syntax analysis
 * - Security vulnerability detection
 * - Performance analysis
 * - Multiple output formats
 */
class AnalyzeCommand extends Command
{
    protected static $defaultName = 'analyze';
    protected static $defaultDescription = 'Analyze PHP code for syntax, security, and performance issues';

    private PhpAstParser $astParser;
    private AnalysisEngine $analysisEngine;
    private ReportGenerator $reportGenerator;

    public function __construct()
    {
        parent::__construct();
        
        $this->astParser = new PhpAstParser();
        $this->analysisEngine = new AnalysisEngine();
        $this->reportGenerator = new ReportGenerator();
        
        $this->initializeAnalyzers();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path to PHP file or directory to analyze')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (console, json, xml, html)', 'console')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path')
            ->addOption('include-syntax', null, InputOption::VALUE_NONE, 'Include syntax analysis')
            ->addOption('include-security', null, InputOption::VALUE_NONE, 'Include security analysis')
            ->addOption('include-performance', null, InputOption::VALUE_NONE, 'Include performance analysis')
            ->addOption('severity', 's', InputOption::VALUE_OPTIONAL, 'Minimum severity level (info, low, medium, high, critical)', 'medium')
            ->addOption('exclude', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Exclude patterns (glob)')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'Configuration file path')
            ->addOption('parallel', 'p', InputOption::VALUE_NONE, 'Enable parallel processing')
            ->addOption('cache', null, InputOption::VALUE_NONE, 'Enable result caching')
            ->addOption('baseline', 'b', InputOption::VALUE_OPTIONAL, 'Baseline file for comparison')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show detailed statistics')
            ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'Memory limit (e.g., 512M, 1G)', '512M')
            ->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Analysis timeout in seconds', '300')
            ->setHelp($this->getCommandHelp());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('YC PHP Code Analysis');
        
        // Validate and normalize inputs
        $path = $this->validatePath($input->getArgument('path'));
        $config = $this->loadConfiguration($input, $io);
        
        if ($path === null) {
            $io->error('Invalid path provided');
            return Command::FAILURE;
        }
        
        // Set resource limits
        $this->setResourceLimits($input, $io);
        
        // Configure analyzers based on options
        $this->configureAnalyzers($input, $config);
        
        $startTime = microtime(true);
        
        try {
            // Perform analysis
            $results = $this->performAnalysis($path, $input, $io);
            
            if (empty($results)) {
                $io->warning('No files found to analyze');
                return Command::SUCCESS;
            }
            
            // Generate and output report
            $this->generateReport($results, $input, $output, $io);
            
            // Show summary
            $this->showSummary($results, $startTime, $input, $io);
            
            // Determine exit code based on severity threshold
            $exitCode = $this->determineExitCode($results, $input->getOption('severity'));
            
            return $exitCode;
            
        } catch (\Throwable $e) {
            $io->error([
                'Analysis failed with error:',
                $e->getMessage()
            ]);
            
            if ($input->getOption('verbose')) {
                $io->block($e->getTraceAsString(), 'DEBUG', 'fg=gray');
            }
            
            return Command::FAILURE;
        }
    }

    /**
     * Initialize default analyzers
     */
    private function initializeAnalyzers(): void
    {
        // Add syntax analyzer
        $syntaxRuleEngine = new SyntaxRuleEngine();
        $syntaxAnalyzer = new SyntaxAnalyzer($syntaxRuleEngine);
        $this->analysisEngine->addAnalyzer($syntaxAnalyzer);
        
        // Add security analyzer
        $securityRuleEngine = new SecurityRuleEngine();
        $securityRuleEngine->addRule(new SqlInjectionRule());
        $securityAnalyzer = new SecurityAnalyzer($securityRuleEngine);
        $this->analysisEngine->addAnalyzer($securityAnalyzer);
    }

    /**
     * Validate file/directory path
     */
    private function validatePath(string $path): ?string
    {
        $realPath = realpath($path);
        
        if ($realPath === false) {
            return null;
        }
        
        if (!is_readable($realPath)) {
            return null;
        }
        
        return $realPath;
    }

    /**
     * Load configuration from file or defaults
     */
    private function loadConfiguration(InputInterface $input, SymfonyStyle $io): array
    {
        $config = $this->getDefaultConfig();
        
        $configFile = $input->getOption('config');
        if ($configFile && file_exists($configFile)) {
            try {
                $fileConfig = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
                $config = array_merge($config, $fileConfig);
                $io->note("Loaded configuration from: {$configFile}");
            } catch (\JsonException $e) {
                $io->warning("Invalid configuration file: {$e->getMessage()}");
            }
        }
        
        return $config;
    }

    /**
     * Set resource limits
     */
    private function setResourceLimits(InputInterface $input, SymfonyStyle $io): void
    {
        $memoryLimit = $input->getOption('memory-limit');
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
            $io->note("Memory limit set to: {$memoryLimit}");
        }
        
        $timeout = (int) $input->getOption('timeout');
        if ($timeout > 0) {
            set_time_limit($timeout);
            $io->note("Timeout set to: {$timeout} seconds");
        }
    }

    /**
     * Configure analyzers based on input options
     */
    private function configureAnalyzers(InputInterface $input, array $config): void
    {
        // Enable/disable analyzers based on options
        $enableSyntax = $input->getOption('include-syntax');
        $enableSecurity = $input->getOption('include-security');
        $enablePerformance = $input->getOption('include-performance');
        
        // If no specific options, enable syntax and security by default
        if (!$enableSyntax && !$enableSecurity && !$enablePerformance) {
            $enableSyntax = true;
            $enableSecurity = true;
        }
        
        foreach ($this->analysisEngine->getAnalyzers() as $analyzer) {
            $analyzerClass = get_class($analyzer);
            
            if (str_contains($analyzerClass, 'SyntaxAnalyzer')) {
                $analyzer->setEnabled($enableSyntax);
            } elseif (str_contains($analyzerClass, 'SecurityAnalyzer')) {
                $analyzer->setEnabled($enableSecurity);
            } elseif (str_contains($analyzerClass, 'PerformanceAnalyzer')) {
                $analyzer->setEnabled($enablePerformance);
            }
        }
        
        // Configure parallel processing
        if ($input->getOption('parallel')) {
            $this->analysisEngine->setParallelProcessing(true);
        }
        
        // Configure caching
        if ($input->getOption('cache')) {
            $this->analysisEngine->setCachingEnabled(true);
        }
    }

    /**
     * Perform the actual analysis
     */
    private function performAnalysis(string $path, InputInterface $input, SymfonyStyle $io): array
    {
        $excludePatterns = $input->getOption('exclude') ?: [];
        $files = $this->findPhpFiles($path, $excludePatterns);
        
        $io->progressStart(count($files));
        $io->note(sprintf('Analyzing %d PHP files...', count($files)));
        
        $results = [];
        
        foreach ($files as $file) {
            try {
                $fileContext = new FileContext($file);
                $ast = $this->astParser->parse($fileContext);
                
                if ($ast !== null) {
                    $analysisResult = $this->analysisEngine->analyze($fileContext, $ast);
                    $results[$file] = $analysisResult;
                }
                
                $io->progressAdvance();
                
            } catch (\Throwable $e) {
                $io->warning("Failed to analyze {$file}: {$e->getMessage()}");
                continue;
            }
        }
        
        $io->progressFinish();
        
        return $results;
    }

    /**
     * Find PHP files in given path
     */
    private function findPhpFiles(string $path, array $excludePatterns = []): array
    {
        $files = [];
        
        if (is_file($path)) {
            return [$path];
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            
            $filePath = $file->getPathname();
            
            // Check exclude patterns
            $excluded = false;
            foreach ($excludePatterns as $pattern) {
                if (fnmatch($pattern, $filePath)) {
                    $excluded = true;
                    break;
                }
            }
            
            if (!$excluded) {
                $files[] = $filePath;
            }
        }
        
        return $files;
    }

    /**
     * Generate and output report
     */
    private function generateReport(array $results, InputInterface $input, OutputInterface $output, SymfonyStyle $io): void
    {
        $format = $input->getOption('format');
        $outputFile = $input->getOption('output');
        
        $report = $this->reportGenerator->generate($results, [
            'format' => $format,
            'severity_threshold' => $input->getOption('severity'),
            'include_stats' => $input->getOption('stats'),
            'baseline' => $input->getOption('baseline')
        ]);
        
        if ($outputFile) {
            file_put_contents($outputFile, $report);
            $io->success("Report written to: {$outputFile}");
        } else {
            $output->write($report);
        }
    }

    /**
     * Show analysis summary
     */
    private function showSummary(array $results, float $startTime, InputInterface $input, SymfonyStyle $io): void
    {
        $totalFiles = count($results);
        $totalIssues = 0;
        $issuesBySeverity = [];
        
        foreach ($results as $result) {
            $issues = $result->getIssues();
            $totalIssues += count($issues);
            
            foreach ($issues as $issue) {
                $severity = $issue->getSeverity();
                $issuesBySeverity[$severity] = ($issuesBySeverity[$severity] ?? 0) + 1;
            }
        }
        
        $executionTime = microtime(true) - $startTime;
        
        $io->section('Analysis Summary');
        $io->table(['Metric', 'Value'], [
            ['Files analyzed', $totalFiles],
            ['Total issues', $totalIssues],
            ['Execution time', sprintf('%.2fs', $executionTime)],
            ['Memory usage', $this->formatBytes(memory_get_peak_usage(true))]
        ]);
        
        if (!empty($issuesBySeverity)) {
            $io->section('Issues by Severity');
            $rows = [];
            foreach (['critical', 'high', 'medium', 'low', 'info'] as $severity) {
                if (isset($issuesBySeverity[$severity])) {
                    $rows[] = [ucfirst($severity), $issuesBySeverity[$severity]];
                }
            }
            $io->table(['Severity', 'Count'], $rows);
        }
    }

    /**
     * Determine exit code based on findings
     */
    private function determineExitCode(array $results, string $severityThreshold): int
    {
        $severityLevels = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $threshold = $severityLevels[$severityThreshold] ?? 2;
        
        foreach ($results as $result) {
            foreach ($result->getIssues() as $issue) {
                $issueSeverity = $severityLevels[$issue->getSeverity()] ?? 0;
                if ($issueSeverity >= $threshold) {
                    return Command::FAILURE;
                }
            }
        }
        
        return Command::SUCCESS;
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'parallel_processing' => true,
            'caching_enabled' => false,
            'memory_limit' => '512M',
            'timeout' => 300,
            'exclude_patterns' => [
                '*/vendor/*',
                '*/node_modules/*',
                '*/cache/*',
                '*/tmp/*',
                '*/.git/*'
            ]
        ];
    }

    /**
     * Get command help text
     */
    private function getCommandHelp(): string
    {
        return <<<'HELP'
The <info>analyze</info> command analyzes PHP code for syntax, security, and performance issues.

<comment>Examples:</comment>
  <info>php bin/pca analyze src/</info>                    # Analyze src directory
  <info>php bin/pca analyze file.php -f json</info>        # Output as JSON
  <info>php bin/pca analyze src/ --include-security</info> # Security analysis only
  <info>php bin/pca analyze src/ -s high -o report.html</info> # High severity issues to HTML

<comment>Output Formats:</comment>
  - console (default): Human-readable console output
  - json: JSON format for integration
  - xml: XML format
  - html: HTML report

<comment>Severity Levels:</comment>
  - info: Informational messages
  - low: Low-priority issues
  - medium: Medium-priority issues (default threshold)
  - high: High-priority issues
  - critical: Critical security/functionality issues
HELP;
    }
}