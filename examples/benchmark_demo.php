<?php

declare(strict_types=1);

/**
 * Performance Benchmark Demo
 * 
 * This example demonstrates how to use the YC-PCA benchmark system
 * to measure and compare performance of the PHP code analysis engine.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use YcPca\Ast\PhpAstParser;
use YcPca\Analysis\AnalysisEngine;
use YcPca\Analysis\Analyzer\SecurityAnalyzer;
use YcPca\Analysis\Security\SecurityRuleEngine;
use YcPca\Analysis\Security\Rule\SqlInjectionRule;
use YcPca\Benchmark\BenchmarkRunner;
use YcPca\Benchmark\BenchmarkSuite;
use YcPca\Benchmark\Benchmarks\ParsingBenchmark;
use YcPca\Benchmark\Benchmarks\AnalysisBenchmark;
use YcPca\Benchmark\Benchmarks\SecurityBenchmark;

echo "=== YC-PCA Performance Benchmark Demo ===\n\n";

// Initialize core components
echo "Initializing analysis components...\n";
$astParser = new PhpAstParser();
$analysisEngine = new AnalysisEngine();

// Set up security analyzer
$securityRuleEngine = new SecurityRuleEngine();
$securityRuleEngine->addRule(new SqlInjectionRule());
$securityAnalyzer = new SecurityAnalyzer($securityRuleEngine);
$analysisEngine->addAnalyzer($securityAnalyzer);

// Create benchmark runner with custom configuration
$benchmarkConfig = [
    'iterations' => 5, // Run each benchmark 5 times
    'warmup_runs' => 2, // 2 warmup runs before measurement
    'regression_time_threshold' => 15.0, // 15% slower is considered regression
    'regression_memory_threshold' => 25.0, // 25% more memory is considered regression
];

$runner = new BenchmarkRunner($astParser, $analysisEngine, $benchmarkConfig);

// Create benchmark suites
echo "Creating benchmark suites...\n";

// Parsing Performance Suite
$parsingSuite = new BenchmarkSuite(
    'parsing_performance',
    'AST parsing performance across different file sizes and complexity'
);
$parsingSuite->addBenchmark(new ParsingBenchmark());

// Analysis Performance Suite
$analysisSuite = new BenchmarkSuite(
    'analysis_performance', 
    'Analysis engine performance across different code patterns'
);
$analysisSuite->addBenchmark(new AnalysisBenchmark());

// Security Analysis Suite
$securitySuite = new BenchmarkSuite(
    'security_analysis',
    'Security analysis performance for OWASP Top 10 detection'
);
$securitySuite->addBenchmark(new SecurityBenchmark());

// Add suites to runner
$runner->addBenchmarkSuite($parsingSuite)
       ->addBenchmarkSuite($analysisSuite)
       ->addBenchmarkSuite($securitySuite);

echo "Starting benchmark execution...\n\n";

try {
    // Run all benchmarks
    $results = $runner->runAllBenchmarks();
    
    echo "Benchmark execution completed!\n";
    echo "Total execution time: " . sprintf('%.2fs', $results->getTotalExecutionTime()) . "\n\n";
    
    // Display summary statistics
    echo "=== Performance Summary ===\n";
    $stats = $results->getOverallStatistics();
    
    echo "Total benchmarks executed: " . $stats['total_benchmarks'] . "\n";
    echo "Total test suites: " . $stats['total_suites'] . "\n";
    echo "Average benchmark time: " . sprintf('%.2fms', $stats['average_benchmark_time'] * 1000) . "\n";
    echo "Average memory usage: " . sprintf('%.2fMB', $stats['average_memory_usage'] / 1024 / 1024) . "\n";
    echo "Fastest benchmark: " . sprintf('%.2fms', $stats['fastest_benchmark_time'] * 1000) . "\n";
    echo "Slowest benchmark: " . sprintf('%.2fms', $stats['slowest_benchmark_time'] * 1000) . "\n";
    echo "Lowest memory usage: " . sprintf('%.2fMB', $stats['lowest_memory_usage'] / 1024 / 1024) . "\n";
    echo "Highest memory usage: " . sprintf('%.2fMB', $stats['highest_memory_usage'] / 1024 / 1024) . "\n\n";
    
    // Check for performance violations
    echo "=== Performance Analysis ===\n";
    $violations = $results->getPerformanceViolations();
    if (!empty($violations)) {
        echo "Performance violations detected:\n";
        foreach ($violations as $benchmark => $violation) {
            echo "- {$benchmark}:\n";
            if (isset($violation['time_violation'])) {
                $tv = $violation['time_violation'];
                echo "  Time: Expected ≤{$tv['expected']}s, Got {$tv['actual']}s ({$tv['ratio']}x slower)\n";
            }
            if (isset($violation['memory_violation'])) {
                $mv = $violation['memory_violation'];
                echo "  Memory: Expected ≤" . sprintf('%.1fMB', $mv['expected'] / 1024 / 1024) . 
                     ", Got " . sprintf('%.1fMB', $mv['actual'] / 1024 / 1024) . 
                     " ({$mv['ratio']}x more)\n";
            }
        }
    } else {
        echo "✅ All benchmarks performed within expected thresholds!\n";
    }
    
    // Check for unstable benchmarks
    $unstable = $results->getUnstableBenchmarks();
    if (!empty($unstable)) {
        echo "\nUnstable benchmarks (high variability):\n";
        foreach ($unstable as $benchmark => $data) {
            echo "- {$benchmark}: CV=" . sprintf('%.2f%%', $data['coefficient_of_variation'] * 100) . 
                 " (Range: " . sprintf('%.2f-%.2fms', $data['min_time'] * 1000, $data['max_time'] * 1000) . ")\n";
        }
    } else {
        echo "✅ All benchmarks showed stable performance!\n";
    }
    
    // Export results for future comparison
    $resultsFile = __DIR__ . '/benchmark_results_' . date('Y-m-d_H-i-s') . '.json';
    $runner->exportResults($results, $resultsFile);
    echo "\n📊 Results exported to: {$resultsFile}\n";
    
    // Generate detailed report
    echo "\n=== Detailed Performance Report ===\n";
    $report = $runner->generateReport($results);
    echo $report;
    
    // Save report to file
    $reportFile = __DIR__ . '/benchmark_report_' . date('Y-m-d_H-i-s') . '.txt';
    file_put_contents($reportFile, $report);
    echo "\n📄 Detailed report saved to: {$reportFile}\n";
    
    // Example: Load baseline for comparison (if available)
    $baselineFile = __DIR__ . '/baseline_results.json';
    if (file_exists($baselineFile)) {
        echo "\n=== Regression Analysis ===\n";
        $baseline = $runner->importResults($baselineFile);
        if ($baseline) {
            $comparison = $runner->compareWithBaseline($results, $baseline);
            $comparisonReport = $runner->generateReport($results, $comparison);
            echo "Regression analysis completed. Check the detailed report above for comparison results.\n";
        }
    } else {
        echo "\n💡 Tip: Save current results as baseline for future regression testing:\n";
        echo "cp '{$resultsFile}' '" . __DIR__ . "/baseline_results.json'\n";
    }

} catch (Exception $e) {
    echo "❌ Benchmark execution failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n🎉 Benchmark demo completed successfully!\n";
echo "\nNext steps:\n";
echo "1. Review the detailed report for performance insights\n";
echo "2. Set up baseline results for regression testing\n";
echo "3. Integrate benchmarks into your CI/CD pipeline\n";
echo "4. Use benchmark results to guide optimization efforts\n";