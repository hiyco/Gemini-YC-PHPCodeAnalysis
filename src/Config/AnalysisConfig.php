<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Analysis Configuration Management
 */

namespace YcPca\Config;

/**
 * Configuration manager for analysis engine and analyzers
 * 
 * Features:
 * - Analyzer enable/disable control
 * - Performance tuning parameters
 * - Rule customization
 * - Environment-specific settings
 */
class AnalysisConfig
{
    private const DEFAULT_CONFIG = [
        'version' => '1.0.0',
        'max_analyzers' => 20,
        'enable_caching' => true,
        'cache_size' => 1000,
        'parallel_processing' => true,
        'max_workers' => 4,
        'memory_limit' => '1G',
        'timeout_seconds' => 300,
        'analyzers' => [
            'syntax' => ['enabled' => true, 'priority' => 100],
            'security' => ['enabled' => true, 'priority' => 90],
            'performance' => ['enabled' => true, 'priority' => 80],
            'quality' => ['enabled' => true, 'priority' => 70],
            'style' => ['enabled' => false, 'priority' => 60]
        ],
        'rules' => [
            'syntax' => [
                'strict_types' => true,
                'php_version' => '8.1'
            ],
            'security' => [
                'detect_sql_injection' => true,
                'detect_xss' => true,
                'detect_csrf' => true,
                'check_input_validation' => true,
                'scan_file_uploads' => true
            ],
            'performance' => [
                'detect_n_plus_one' => true,
                'check_algorithm_complexity' => true,
                'memory_leak_detection' => true,
                'cache_usage_analysis' => true
            ],
            'quality' => [
                'max_complexity' => 10,
                'max_method_length' => 50,
                'max_class_length' => 500,
                'detect_code_smells' => true
            ],
            'style' => [
                'enforce_psr12' => false,
                'line_length_limit' => 120,
                'indentation' => 'spaces'
            ]
        ],
        'exclusions' => [
            'directories' => ['vendor', 'node_modules', '.git', 'storage/cache'],
            'files' => ['*.min.php', 'autoload.php'],
            'patterns' => ['*_test.php', '*Test.php']
        ],
        'reporting' => [
            'include_context' => true,
            'include_suggestions' => true,
            'max_context_lines' => 5,
            'group_similar_issues' => true
        ]
    ];

    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge_recursive(self::DEFAULT_CONFIG, $config);
    }

    /**
     * Get configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getNestedValue($this->config, $key, $default);
    }

    /**
     * Set configuration value
     */
    public function set(string $key, mixed $value): self
    {
        $this->setNestedValue($this->config, $key, $value);
        return $this;
    }

    /**
     * Get configuration version
     */
    public function getVersion(): string
    {
        return $this->config['version'];
    }

    /**
     * Get maximum number of analyzers
     */
    public function getMaxAnalyzers(): int
    {
        return $this->config['max_analyzers'];
    }

    /**
     * Check if caching is enabled
     */
    public function isCachingEnabled(): bool
    {
        return $this->config['enable_caching'];
    }

    /**
     * Get cache size limit
     */
    public function getCacheSize(): int
    {
        return $this->config['cache_size'];
    }

    /**
     * Check if parallel processing is enabled
     */
    public function isParallelProcessingEnabled(): bool
    {
        return $this->config['parallel_processing'];
    }

    /**
     * Get maximum worker count for parallel processing
     */
    public function getMaxWorkers(): int
    {
        return $this->config['max_workers'];
    }

    /**
     * Get memory limit
     */
    public function getMemoryLimit(): string
    {
        return $this->config['memory_limit'];
    }

    /**
     * Get timeout in seconds
     */
    public function getTimeoutSeconds(): int
    {
        return $this->config['timeout_seconds'];
    }

    /**
     * Check if analyzer is enabled
     */
    public function isAnalyzerEnabled(string $analyzerName): bool
    {
        return $this->config['analyzers'][$analyzerName]['enabled'] ?? false;
    }

    /**
     * Get analyzer priority
     */
    public function getAnalyzerPriority(string $analyzerName): int
    {
        return $this->config['analyzers'][$analyzerName]['priority'] ?? 50;
    }

    /**
     * Enable analyzer
     */
    public function enableAnalyzer(string $analyzerName): self
    {
        $this->config['analyzers'][$analyzerName]['enabled'] = true;
        return $this;
    }

    /**
     * Disable analyzer
     */
    public function disableAnalyzer(string $analyzerName): self
    {
        $this->config['analyzers'][$analyzerName]['enabled'] = false;
        return $this;
    }

    /**
     * Set analyzer priority
     */
    public function setAnalyzerPriority(string $analyzerName, int $priority): self
    {
        $this->config['analyzers'][$analyzerName]['priority'] = $priority;
        return $this;
    }

    /**
     * Get all analyzer configurations
     */
    public function getAnalyzersConfig(): array
    {
        return $this->config['analyzers'];
    }

    /**
     * Get enabled analyzers sorted by priority
     */
    public function getEnabledAnalyzers(): array
    {
        $enabled = array_filter(
            $this->config['analyzers'],
            fn(array $config) => $config['enabled'] ?? false
        );
        
        uasort($enabled, fn(array $a, array $b) => 
            ($b['priority'] ?? 50) - ($a['priority'] ?? 50)
        );
        
        return array_keys($enabled);
    }

    /**
     * Get rules for specific analyzer
     */
    public function getAnalyzerRules(string $analyzerName): array
    {
        return $this->config['rules'][$analyzerName] ?? [];
    }

    /**
     * Set rules for specific analyzer
     */
    public function setAnalyzerRules(string $analyzerName, array $rules): self
    {
        $this->config['rules'][$analyzerName] = $rules;
        return $this;
    }

    /**
     * Get exclusion patterns
     */
    public function getExclusions(): array
    {
        return $this->config['exclusions'];
    }

    /**
     * Check if path should be excluded
     */
    public function shouldExcludePath(string $path): bool
    {
        $exclusions = $this->getExclusions();
        
        // Check directories
        foreach ($exclusions['directories'] as $directory) {
            if (str_contains($path, '/' . $directory . '/') || str_ends_with($path, '/' . $directory)) {
                return true;
            }
        }
        
        // Check file patterns
        foreach ($exclusions['files'] as $pattern) {
            if (fnmatch($pattern, basename($path))) {
                return true;
            }
        }
        
        // Check path patterns
        foreach ($exclusions['patterns'] as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get reporting configuration
     */
    public function getReportingConfig(): array
    {
        return $this->config['reporting'];
    }

    /**
     * Check if context should be included in reports
     */
    public function shouldIncludeContext(): bool
    {
        return $this->config['reporting']['include_context'];
    }

    /**
     * Check if suggestions should be included in reports
     */
    public function shouldIncludeSuggestions(): bool
    {
        return $this->config['reporting']['include_suggestions'];
    }

    /**
     * Get maximum context lines to include
     */
    public function getMaxContextLines(): int
    {
        return $this->config['reporting']['max_context_lines'];
    }

    /**
     * Get full configuration array
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Load configuration from array
     */
    public function loadFromArray(array $config): self
    {
        $this->config = array_merge_recursive($this->config, $config);
        return $this;
    }

    /**
     * Load configuration from JSON file
     */
    public function loadFromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Configuration file not found: {$filePath}");
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read configuration file: {$filePath}");
        }
        
        $config = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in configuration file: " . json_last_error_msg());
        }
        
        return $this->loadFromArray($config);
    }

    /**
     * Save configuration to JSON file
     */
    public function saveToFile(string $filePath): self
    {
        $content = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($filePath, $content) === false) {
            throw new \RuntimeException("Failed to write configuration file: {$filePath}");
        }
        
        return $this;
    }

    /**
     * Get configuration summary
     */
    public function getSummary(): array
    {
        return [
            'version' => $this->getVersion(),
            'enabled_analyzers' => count($this->getEnabledAnalyzers()),
            'total_analyzers' => count($this->config['analyzers']),
            'caching_enabled' => $this->isCachingEnabled(),
            'parallel_processing' => $this->isParallelProcessingEnabled(),
            'max_workers' => $this->getMaxWorkers(),
            'memory_limit' => $this->getMemoryLimit(),
            'timeout_seconds' => $this->getTimeoutSeconds()
        ];
    }

    /**
     * Validate configuration
     */
    public function validate(): array
    {
        $errors = [];
        
        // Validate basic structure
        if (!is_array($this->config)) {
            $errors[] = 'Configuration must be an array';
            return $errors;
        }
        
        // Validate numeric values
        if ($this->getMaxAnalyzers() <= 0) {
            $errors[] = 'max_analyzers must be greater than 0';
        }
        
        if ($this->getCacheSize() <= 0) {
            $errors[] = 'cache_size must be greater than 0';
        }
        
        if ($this->getMaxWorkers() <= 0) {
            $errors[] = 'max_workers must be greater than 0';
        }
        
        if ($this->getTimeoutSeconds() <= 0) {
            $errors[] = 'timeout_seconds must be greater than 0';
        }
        
        // Validate memory limit format
        if (!preg_match('/^\d+[GMK]?$/', $this->getMemoryLimit())) {
            $errors[] = 'Invalid memory_limit format';
        }
        
        // Validate analyzer configurations
        foreach ($this->config['analyzers'] as $name => $config) {
            if (!is_array($config)) {
                $errors[] = "Analyzer '{$name}' configuration must be an array";
                continue;
            }
            
            if (!isset($config['enabled']) || !is_bool($config['enabled'])) {
                $errors[] = "Analyzer '{$name}' must have boolean 'enabled' setting";
            }
            
            if (!isset($config['priority']) || !is_int($config['priority'])) {
                $errors[] = "Analyzer '{$name}' must have integer 'priority' setting";
            }
        }
        
        return $errors;
    }

    /**
     * Get nested configuration value using dot notation
     */
    private function getNestedValue(array $config, string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }

    /**
     * Set nested configuration value using dot notation
     */
    private function setNestedValue(array &$config, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$config;
        
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }
    }
}