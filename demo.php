<?php

declare(strict_types=1);

/**
 * Demo script to showcase the PHP Code Analysis functionality
 * This bypasses the CLI for now and directly demonstrates the core features
 */

// Simple autoloader for our classes
spl_autoload_register(function (string $class) {
    $prefix = 'YcPca\\';
    $baseDir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

echo "=== YC PHP Code Analysis Demo ===\n\n";

try {
    // Test file parsing without external dependencies
    $sampleFile = __DIR__ . '/examples/sample.php';
    
    if (!file_exists($sampleFile)) {
        echo "❌ Sample file not found: {$sampleFile}\n";
        exit(1);
    }
    
    echo "📁 Analyzing file: {$sampleFile}\n";
    echo "📝 File size: " . number_format(filesize($sampleFile)) . " bytes\n";
    
    // Read and analyze the sample file
    $content = file_get_contents($sampleFile);
    $lines = explode("\n", $content);
    
    echo "📊 Total lines: " . count($lines) . "\n";
    
    // Simple pattern-based analysis (without AST parser for now)
    $issues = [];
    $lineNumber = 0;
    
    foreach ($lines as $line) {
        $lineNumber++;
        $trimmedLine = trim($line);
        
        // Check for potential SQL injection patterns
        if (preg_match('/mysql_query.*\$|mysqli?_query.*\$|query.*\$/', $trimmedLine)) {
            $issues[] = [
                'type' => 'security',
                'severity' => 'high',
                'line' => $lineNumber,
                'title' => 'Potential SQL Injection',
                'description' => 'Direct variable usage in SQL query detected',
                'pattern' => $trimmedLine
            ];
        }
        
        // Check for eval usage
        if (preg_match('/eval\s*\(/', $trimmedLine)) {
            $issues[] = [
                'type' => 'security',
                'severity' => 'critical',
                'line' => $lineNumber,
                'title' => 'Dangerous eval() usage',
                'description' => 'Using eval() is extremely dangerous and should be avoided',
                'pattern' => $trimmedLine
            ];
        }
        
        // Check for unescaped output
        if (preg_match('/echo\s+.*\$.*[\'"]/', $trimmedLine) && !preg_match('/htmlspecialchars|htmlentities/', $trimmedLine)) {
            $issues[] = [
                'type' => 'security',
                'severity' => 'medium',
                'line' => $lineNumber,
                'title' => 'Potential XSS vulnerability',
                'description' => 'Unescaped output detected - may lead to XSS',
                'pattern' => $trimmedLine
            ];
        }
        
        // Check for long lines
        if (strlen($line) > 120) {
            $issues[] = [
                'type' => 'quality',
                'severity' => 'low',
                'line' => $lineNumber,
                'title' => 'Line too long',
                'description' => 'Line exceeds 120 characters (' . strlen($line) . ' characters)',
                'pattern' => substr($trimmedLine, 0, 50) . '...'
            ];
        }
        
        // Check for deprecated mysql functions
        if (preg_match('/mysql_[a-z_]+\s*\(/', $trimmedLine)) {
            $issues[] = [
                'type' => 'compatibility',
                'severity' => 'high',
                'line' => $lineNumber,
                'title' => 'Deprecated MySQL function',
                'description' => 'Using deprecated mysql_* functions - use mysqli or PDO instead',
                'pattern' => $trimmedLine
            ];
        }
    }
    
    // Display results
    echo "\n🔍 Analysis Results:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    if (empty($issues)) {
        echo "✅ No issues found!\n";
    } else {
        $severityCount = array_count_values(array_column($issues, 'severity'));
        $typeCount = array_count_values(array_column($issues, 'type'));
        
        echo "📊 Found " . count($issues) . " issues:\n";
        echo "\nBy Severity:\n";
        foreach (['critical' => '🔥', 'high' => '🚨', 'medium' => '⚠️', 'low' => '💡'] as $sev => $icon) {
            if (isset($severityCount[$sev])) {
                echo "  {$icon} {$sev}: {$severityCount[$sev]}\n";
            }
        }
        
        echo "\nBy Type:\n";
        foreach ($typeCount as $type => $count) {
            echo "  • {$type}: {$count}\n";
        }
        
        echo "\n📋 Detailed Issues:\n";
        echo str_repeat("-", 60) . "\n";
        
        // Sort by severity and line number
        usort($issues, function($a, $b) {
            $severityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            $aSev = $severityOrder[$a['severity']] ?? 0;
            $bSev = $severityOrder[$b['severity']] ?? 0;
            
            if ($aSev === $bSev) {
                return $a['line'] - $b['line'];
            }
            
            return $bSev - $aSev;
        });
        
        foreach ($issues as $issue) {
            $icon = match($issue['severity']) {
                'critical' => '🔥',
                'high' => '🚨',
                'medium' => '⚠️',
                'low' => '💡',
                default => '•'
            };
            
            echo "{$icon} Line {$issue['line']}: {$issue['title']}\n";
            echo "   {$issue['description']}\n";
            echo "   Code: " . trim($issue['pattern']) . "\n\n";
        }
    }
    
    echo "=" . str_repeat("=", 50) . "\n";
    echo "✅ Analysis completed successfully!\n\n";
    
    // Show what we've built
    echo "🏗️  Architecture Components Built:\n";
    echo "   • PHP AST Parser core (/src/Ast/)\n";
    echo "   • Analysis Engine framework (/src/Analysis/)\n";
    echo "   • Syntax Analysis modules (/src/Analysis/Syntax/)\n";
    echo "   • Security Analysis engine (/src/Analysis/Security/)\n";
    echo "   • CLI tool prototype (/src/Cli/)\n";
    echo "   • Report generators (/src/Report/)\n";
    echo "   • Data models and utilities (/src/Model/)\n\n";
    
    echo "🎯 Next Steps:\n";
    echo "   • Install dependencies (composer install)\n";
    echo "   • Set up unit testing framework\n";
    echo "   • Add performance benchmarks\n";
    echo "   • Create documentation and examples\n\n";
    
    echo "💡 Usage (once dependencies are installed):\n";
    echo "   php bin/pca analyze examples/sample.php\n";
    echo "   php bin/pca analyze src/ --include-security\n";
    echo "   php bin/pca analyze src/ -f json -o report.json\n\n";
    
} catch (\Throwable $e) {
    echo "❌ Error during analysis: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if (isset($_ENV['DEBUG']) || in_array('--debug', $argv ?? [], true)) {
        echo "\n🐛 Debug trace:\n" . $e->getTraceAsString() . "\n";
    }
    
    exit(1);
}

echo "🏁 Demo completed!\n";