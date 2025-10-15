<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Test helper utilities and common functions
 */

namespace YcPca\Tests\Helpers;

use YcPca\Analysis\Issue\Issue;

/**
 * Collection of helper functions for testing
 * 
 * Features:
 * - Issue creation helpers
 * - Mock data generators
 * - Validation utilities
 * - Performance measurement
 */
class TestHelper
{
    /**
     * Create a test issue with minimal required data
     */
    public static function createTestIssue(array $overrides = []): Issue
    {
        $defaults = [
            'id' => 'test_issue_' . uniqid(),
            'title' => 'Test Issue',
            'description' => 'This is a test issue for unit testing',
            'severity' => Issue::SEVERITY_MEDIUM,
            'category' => Issue::CATEGORY_QUALITY,
            'line' => 1,
            'column' => 1,
            'endLine' => 1,
            'endColumn' => 10,
            'ruleId' => 'test_rule',
            'ruleName' => 'Test Rule',
            'tags' => ['test'],
            'suggestions' => ['Fix this issue'],
            'codeSnippet' => '<?php echo "test";',
            'metadata' => []
        ];
        
        $data = array_merge($defaults, $overrides);
        
        return new Issue(
            id: $data['id'],
            title: $data['title'],
            description: $data['description'],
            severity: $data['severity'],
            category: $data['category'],
            line: $data['line'],
            column: $data['column'],
            endLine: $data['endLine'],
            endColumn: $data['endColumn'],
            ruleId: $data['ruleId'],
            ruleName: $data['ruleName'],
            tags: $data['tags'],
            suggestions: $data['suggestions'],
            codeSnippet: $data['codeSnippet'],
            metadata: $data['metadata']
        );
    }

    /**
     * Create multiple test issues with different severities
     */
    public static function createTestIssues(int $count = 5): array
    {
        $severities = [
            Issue::SEVERITY_CRITICAL,
            Issue::SEVERITY_HIGH,
            Issue::SEVERITY_MEDIUM,
            Issue::SEVERITY_LOW,
            Issue::SEVERITY_INFO
        ];
        
        $issues = [];
        
        for ($i = 0; $i < $count; $i++) {
            $severity = $severities[$i % count($severities)];
            
            $issues[] = self::createTestIssue([
                'id' => "test_issue_{$i}",
                'title' => "Test Issue {$i}",
                'severity' => $severity,
                'line' => $i + 1
            ]);
        }
        
        return $issues;
    }

    /**
     * Create test issues by severity distribution
     */
    public static function createTestIssuesBySeverity(array $severityDistribution): array
    {
        $issues = [];
        $lineNumber = 1;
        
        foreach ($severityDistribution as $severity => $count) {
            for ($i = 0; $i < $count; $i++) {
                $issues[] = self::createTestIssue([
                    'id' => "test_issue_{$severity}_{$i}",
                    'title' => "Test {$severity} Issue {$i}",
                    'severity' => $severity,
                    'line' => $lineNumber++
                ]);
            }
        }
        
        return $issues;
    }

    /**
     * Generate test PHP code with specific vulnerability patterns
     */
    public static function generateVulnerablePhpCode(array $vulnerabilityTypes): string
    {
        $code = "<?php\n\nclass VulnerableTestClass\n{\n";
        $methodCount = 1;
        
        foreach ($vulnerabilityTypes as $type => $config) {
            $methodName = "vulnerability" . ucfirst($type) . $methodCount;
            
            $methodCode = match($type) {
                'sql_injection' => self::generateSqlInjectionCode($config),
                'xss' => self::generateXssCode($config),
                'eval_usage' => self::generateEvalCode($config),
                'file_inclusion' => self::generateFileInclusionCode($config),
                'command_injection' => self::generateCommandInjectionCode($config),
                'unserialize' => self::generateUnserializeCode($config),
                default => '        return "safe code";'
            };
            
            $code .= "\n    public function {$methodName}(\$input)\n    {\n{$methodCode}\n    }\n";
            $methodCount++;
        }
        
        $code .= "\n}\n";
        
        return $code;
    }

    /**
     * Measure execution time and memory usage
     */
    public static function measurePerformance(callable $callback): array
    {
        $initialMemory = memory_get_usage(true);
        $initialPeak = memory_get_peak_usage(true);
        $startTime = microtime(true);
        
        $result = $callback();
        
        $endTime = microtime(true);
        $finalMemory = memory_get_usage(true);
        $finalPeak = memory_get_peak_usage(true);
        
        return [
            'result' => $result,
            'execution_time' => $endTime - $startTime,
            'memory_used' => $finalMemory - $initialMemory,
            'peak_memory' => max($finalPeak - $initialPeak, 0),
            'initial_memory' => $initialMemory,
            'final_memory' => $finalMemory
        ];
    }

    /**
     * Assert array has specific structure
     */
    public static function assertArrayStructure(array $expected, array $actual): void
    {
        foreach ($expected as $key => $expectedValue) {
            if (!array_key_exists($key, $actual)) {
                throw new \PHPUnit\Framework\AssertionFailedError("Missing key: {$key}");
            }
            
            if (is_array($expectedValue)) {
                if (!is_array($actual[$key])) {
                    throw new \PHPUnit\Framework\AssertionFailedError("Key {$key} should be array");
                }
                self::assertArrayStructure($expectedValue, $actual[$key]);
            } elseif ($expectedValue !== '*') {
                if ($actual[$key] !== $expectedValue) {
                    throw new \PHPUnit\Framework\AssertionFailedError(
                        "Key {$key} expected {$expectedValue}, got {$actual[$key]}"
                    );
                }
            }
        }
    }

    /**
     * Create test configuration
     */
    public static function createTestConfig(array $overrides = []): array
    {
        $defaults = [
            'analyzers' => [
                'syntax' => ['enabled' => true],
                'security' => ['enabled' => true],
                'performance' => ['enabled' => false]
            ],
            'rules' => [
                'strict_mode' => false,
                'severity_threshold' => 'medium'
            ],
            'output' => [
                'format' => 'json',
                'include_suggestions' => true
            ]
        ];
        
        return array_merge_recursive($defaults, $overrides);
    }

    /**
     * Generate test file content with specific patterns
     */
    public static function generateTestFile(string $pattern, array $options = []): string
    {
        $lineCount = $options['lines'] ?? 50;
        $classCount = $options['classes'] ?? 1;
        $methodsPerClass = $options['methods_per_class'] ?? 5;
        
        $content = "<?php\n\ndeclare(strict_types=1);\n\n";
        
        for ($classIndex = 0; $classIndex < $classCount; $classIndex++) {
            $className = "TestClass{$classIndex}";
            $content .= "class {$className}\n{\n";
            
            for ($methodIndex = 0; $methodIndex < $methodsPerClass; $methodIndex++) {
                $methodName = "method{$methodIndex}";
                $content .= "    public function {$methodName}(): void\n    {\n";
                
                // Add pattern-specific content
                $content .= self::generatePatternContent($pattern, $options);
                
                $content .= "    }\n\n";
            }
            
            $content .= "}\n\n";
        }
        
        return $content;
    }

    /**
     * Private helper methods for code generation
     */
    private static function generateSqlInjectionCode(array $config): string
    {
        $dangerous = $config['dangerous'] ?? true;
        
        if ($dangerous) {
            return '        $query = "SELECT * FROM users WHERE id = " . $input;
        return mysql_query($query);';
        } else {
            return '        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$input]);
        return $stmt->fetchAll();';
        }
    }

    private static function generateXssCode(array $config): string
    {
        $escaped = $config['escaped'] ?? false;
        
        if ($escaped) {
            return '        echo htmlspecialchars($input, ENT_QUOTES, \'UTF-8\');';
        } else {
            return '        echo "<div>" . $input . "</div>";';
        }
    }

    private static function generateEvalCode(array $config): string
    {
        return '        return eval($input);';
    }

    private static function generateFileInclusionCode(array $config): string
    {
        $validated = $config['validated'] ?? false;
        
        if ($validated) {
            return '        $allowedFiles = ["config.php", "helpers.php"];
        if (in_array($input, $allowedFiles)) {
            include $input;
        }';
        } else {
            return '        include $input;';
        }
    }

    private static function generateCommandInjectionCode(array $config): string
    {
        $escaped = $config['escaped'] ?? false;
        
        if ($escaped) {
            return '        $command = escapeshellcmd($input);
        return shell_exec($command);';
        } else {
            return '        return shell_exec($input);';
        }
    }

    private static function generateUnserializeCode(array $config): string
    {
        $validated = $config['validated'] ?? false;
        
        if ($validated) {
            return '        $allowed = ["stdClass", "MyClass"];
        return unserialize($input, ["allowed_classes" => $allowed]);';
        } else {
            return '        return unserialize($input);';
        }
    }

    private static function generatePatternContent(string $pattern, array $options): string
    {
        return match($pattern) {
            'simple' => '        return "simple content";\n',
            'complex' => '        $data = [];\n        for ($i = 0; $i < 10; $i++) {\n            $data[] = $i * 2;\n        }\n        return $data;\n',
            'with_issues' => '        $query = "SELECT * FROM table WHERE id = " . $_GET["id"];\n        echo $query;\n',
            default => '        // Generated content\n'
        };
    }
}