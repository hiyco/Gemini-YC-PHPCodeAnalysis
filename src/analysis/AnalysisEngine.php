<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Core Analysis Engine with multi-analyzer support
 */

namespace YcPca\Analysis;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YcPca\Analysis\Analyzer\AnalyzerInterface;
use YcPca\Analysis\Report\AnalysisReport;
use YcPca\Analysis\Report\ReportAggregator;
use YcPca\Ast\PhpAstParser;
use YcPca\Config\AnalysisConfig;
use YcPca\Exception\AnalysisException;
use YcPca\Model\AnalysisResult;

/**
 * Core Analysis Engine coordinating multiple analyzers
 * 
 * Features:
 * - Multi-analyzer coordination
 * - Configurable analysis pipeline
 * - Performance monitoring
 * - Result aggregation
 * - Parallel processing support
 */
class AnalysisEngine
{
    private LoggerInterface $logger;
    private PhpAstParser $parser;
    private ReportAggregator $reportAggregator;
    private AnalysisConfig $config;
    
    /** @var AnalyzerInterface[] */
    private array $analyzers = [];
    
    private array $engineStats = [
        'files_analyzed' => 0,
        'total_analysis_time' => 0.0,
        'analyzer_executions' => 0,
        'errors_encountered' => 0,
        'warnings_generated' => 0
    ];

    public function __construct(
        ?PhpAstParser $parser = null,
        ?LoggerInterface $logger = null,
        ?AnalysisConfig $config = null
    ) {
        $this->parser = $parser ?? new PhpAstParser();
        $this->logger = $logger ?? new NullLogger();
        $this->config = $config ?? new AnalysisConfig();
        $this->reportAggregator = new ReportAggregator($this->logger);
        
        $this->logger->info('Analysis engine initialized', [
            'config_version' => $this->config->getVersion(),
            'max_analyzers' => $this->config->getMaxAnalyzers()
        ]);
    }

    /**
     * Register an analyzer for the analysis pipeline
     */
    public function registerAnalyzer(AnalyzerInterface $analyzer): self
    {
        $analyzerName = $analyzer->getName();
        
        if (isset($this->analyzers[$analyzerName])) {
            $this->logger->warning('Analyzer already registered, replacing', [
                'analyzer' => $analyzerName
            ]);
        }
        
        $this->analyzers[$analyzerName] = $analyzer;
        
        $this->logger->info('Analyzer registered', [
            'analyzer' => $analyzerName,
            'total_analyzers' => count($this->analyzers)
        ]);
        
        return $this;
    }

    /**
     * Unregister an analyzer
     */
    public function unregisterAnalyzer(string $analyzerName): self
    {
        if (isset($this->analyzers[$analyzerName])) {
            unset($this->analyzers[$analyzerName]);
            
            $this->logger->info('Analyzer unregistered', [
                'analyzer' => $analyzerName,
                'remaining_analyzers' => count($this->analyzers)
            ]);
        }
        
        return $this;
    }

    /**
     * Analyze a single PHP file
     */
    public function analyzeFile(string $filePath): AnalysisReport
    {
        $startTime = microtime(true);
        
        try {
            $this->logger->info('Starting file analysis', [
                'file' => $filePath,
                'analyzers' => array_keys($this->analyzers)
            ]);
            
            // Parse the file first
            $parseResult = $this->parser->parseFile($filePath);
            
            if ($parseResult->hasErrors()) {
                $this->logger->warning('Parse errors detected', [
                    'file' => $filePath,
                    'errors' => $parseResult->getErrors()
                ]);
            }
            
            // Run all registered analyzers
            $analysisResults = [];
            foreach ($this->analyzers as $analyzer) {
                if (!$this->shouldRunAnalyzer($analyzer, $parseResult)) {
                    continue;
                }
                
                $analysisResults[] = $this->runAnalyzer($analyzer, $parseResult);
            }
            
            // Aggregate results into a comprehensive report
            $report = $this->reportAggregator->aggregateResults($analysisResults);
            $report->setParseResult($parseResult);
            
            $this->updateEngineStats($startTime, count($analysisResults));
            
            $this->logger->info('File analysis completed', [
                'file' => $filePath,
                'duration' => microtime(true) - $startTime,
                'analyzers_run' => count($analysisResults),
                'issues_found' => $report->getTotalIssueCount()
            ]);
            
            return $report;
            
        } catch (\Throwable $e) {
            $this->engineStats['errors_encountered']++;
            
            $this->logger->error('File analysis failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new AnalysisException(
                "Analysis failed for file {$filePath}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Analyze multiple files
     */
    public function analyzeFiles(array $filePaths): array
    {
        $reports = [];
        $totalFiles = count($filePaths);
        
        $this->logger->info('Starting batch analysis', [
            'file_count' => $totalFiles,
            'analyzers' => array_keys($this->analyzers)
        ]);
        
        foreach ($filePaths as $index => $filePath) {
            try {
                $reports[$filePath] = $this->analyzeFile($filePath);
                
                if (($index + 1) % 100 === 0) {
                    $this->logger->info('Batch analysis progress', [
                        'completed' => $index + 1,
                        'total' => $totalFiles,
                        'progress' => round(($index + 1) / $totalFiles * 100, 1) . '%'
                    ]);
                }
                
            } catch (AnalysisException $e) {
                $this->logger->error('File analysis failed in batch', [
                    'file' => $filePath,
                    'error' => $e->getMessage()
                ]);
                
                // Continue with other files
                continue;
            }
        }
        
        $this->logger->info('Batch analysis completed', [
            'total_files' => $totalFiles,
            'successful' => count($reports),
            'failed' => $totalFiles - count($reports)
        ]);
        
        return $reports;
    }

    /**
     * Get comprehensive analysis statistics
     */
    public function getStats(): array
    {
        return array_merge($this->engineStats, [
            'registered_analyzers' => count($this->analyzers),
            'analyzer_names' => array_keys($this->analyzers),
            'parser_stats' => $this->parser->getStats(),
            'config_summary' => $this->config->getSummary(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ]);
    }

    /**
     * Reset engine statistics
     */
    public function resetStats(): self
    {
        $this->engineStats = array_fill_keys(array_keys($this->engineStats), 0);
        $this->parser->reset();
        
        $this->logger->info('Engine statistics reset');
        
        return $this;
    }

    /**
     * Get registered analyzer by name
     */
    public function getAnalyzer(string $name): ?AnalyzerInterface
    {
        return $this->analyzers[$name] ?? null;
    }

    /**
     * Get all registered analyzers
     */
    public function getAnalyzers(): array
    {
        return $this->analyzers;
    }

    /**
     * Update analysis configuration
     */
    public function updateConfig(AnalysisConfig $config): self
    {
        $this->config = $config;
        
        $this->logger->info('Configuration updated', [
            'version' => $config->getVersion()
        ]);
        
        return $this;
    }

    /**
     * Check if analyzer should run for given parse result
     */
    private function shouldRunAnalyzer(AnalyzerInterface $analyzer, AnalysisResult $parseResult): bool
    {
        // Skip if analyzer is disabled
        if (!$this->config->isAnalyzerEnabled($analyzer->getName())) {
            return false;
        }
        
        // Skip if file has parse errors and analyzer doesn't support error recovery
        if ($parseResult->hasErrors() && !$analyzer->supportsErrorRecovery()) {
            return false;
        }
        
        // Check file type compatibility
        if (!$analyzer->supportsFileType($parseResult->getContext()->getExtension())) {
            return false;
        }
        
        return true;
    }

    /**
     * Run a single analyzer safely
     */
    private function runAnalyzer(AnalyzerInterface $analyzer, AnalysisResult $parseResult): AnalyzerResult
    {
        $analyzerName = $analyzer->getName();
        $startTime = microtime(true);
        
        try {
            $this->logger->debug('Running analyzer', [
                'analyzer' => $analyzerName,
                'file' => $parseResult->getFilePath()
            ]);
            
            $result = $analyzer->analyze($parseResult);
            $this->engineStats['analyzer_executions']++;
            
            $executionTime = microtime(true) - $startTime;
            
            $this->logger->debug('Analyzer completed', [
                'analyzer' => $analyzerName,
                'duration' => $executionTime,
                'issues_found' => count($result->getIssues())
            ]);
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->engineStats['errors_encountered']++;
            
            $this->logger->error('Analyzer execution failed', [
                'analyzer' => $analyzerName,
                'file' => $parseResult->getFilePath(),
                'error' => $e->getMessage()
            ]);
            
            // Return empty result to continue with other analyzers
            return new AnalyzerResult(
                analyzerName: $analyzerName,
                filePath: $parseResult->getFilePath(),
                issues: [],
                hasErrors: true,
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * Update engine statistics
     */
    private function updateEngineStats(float $startTime, int $analyzersRun): void
    {
        $this->engineStats['files_analyzed']++;
        $this->engineStats['total_analysis_time'] += microtime(true) - $startTime;
        $this->engineStats['analyzer_executions'] += $analyzersRun;
    }
}