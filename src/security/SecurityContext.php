<?php

declare(strict_types=1);

namespace YcPca\Security;

/**
 * Security analysis context containing relevant information for vulnerability detection
 */
class SecurityContext
{
    private string $filePath;
    private array $functions = [];
    private array $classes = [];
    private array $variables = [];
    private array $inputSources = [];
    private array $outputSinks = [];
    private array $authenticationPoints = [];
    private array $databaseQueries = [];
    private array $fileOperations = [];
    private array $sensitivePatterns = [];
    private array $taintedPaths = [];
    private array $taintedVariables = [];

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    // Setters
    public function setFunctions(array $functions): void
    {
        $this->functions = $functions;
    }

    public function setClasses(array $classes): void
    {
        $this->classes = $classes;
    }

    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }

    public function setInputSources(array $inputSources): void
    {
        $this->inputSources = $inputSources;
        // Automatically mark variables from input sources as tainted
        foreach ($inputSources as $source) {
            if (isset($source['variable'])) {
                $this->taintedVariables[] = $source['variable'];
            }
        }
    }

    public function setOutputSinks(array $outputSinks): void
    {
        $this->outputSinks = $outputSinks;
    }

    public function setAuthenticationPoints(array $authenticationPoints): void
    {
        $this->authenticationPoints = $authenticationPoints;
    }

    public function setDatabaseQueries(array $databaseQueries): void
    {
        $this->databaseQueries = $databaseQueries;
    }

    public function setFileOperations(array $fileOperations): void
    {
        $this->fileOperations = $fileOperations;
    }

    public function setSensitivePatterns(array $sensitivePatterns): void
    {
        $this->sensitivePatterns = $sensitivePatterns;
    }

    public function setTaintedPaths(array $taintedPaths): void
    {
        $this->taintedPaths = $taintedPaths;
        // Extract tainted variables from paths
        foreach ($taintedPaths as $path) {
            if (isset($path['variables'])) {
                $this->taintedVariables = array_merge(
                    $this->taintedVariables,
                    $path['variables']
                );
            }
        }
        $this->taintedVariables = array_unique($this->taintedVariables);
    }

    // Getters
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getFunctions(): array
    {
        return $this->functions;
    }

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getInputSources(): array
    {
        return $this->inputSources;
    }

    public function getOutputSinks(): array
    {
        return $this->outputSinks;
    }

    public function getAuthenticationPoints(): array
    {
        return $this->authenticationPoints;
    }

    public function getDatabaseQueries(): array
    {
        return $this->databaseQueries;
    }

    public function getFileOperations(): array
    {
        return $this->fileOperations;
    }

    public function getSensitivePatterns(): array
    {
        return $this->sensitivePatterns;
    }

    public function getTaintedPaths(): array
    {
        return $this->taintedPaths;
    }

    /**
     * Check if a variable is tainted (potentially contains user input)
     */
    public function isTaintedVariable(string $variableName): bool
    {
        return in_array($variableName, $this->taintedVariables);
    }

    /**
     * Mark a variable as tainted
     */
    public function markVariableAsTainted(string $variableName): void
    {
        if (!in_array($variableName, $this->taintedVariables)) {
            $this->taintedVariables[] = $variableName;
        }
    }

    /**
     * Get all tainted variables
     */
    public function getTaintedVariables(): array
    {
        return $this->taintedVariables;
    }

    /**
     * Check if file contains authentication logic
     */
    public function hasAuthenticationLogic(): bool
    {
        return !empty($this->authenticationPoints);
    }

    /**
     * Check if file contains database operations
     */
    public function hasDatabaseOperations(): bool
    {
        return !empty($this->databaseQueries);
    }

    /**
     * Check if file contains file operations
     */
    public function hasFileOperations(): bool
    {
        return !empty($this->fileOperations);
    }

    /**
     * Check if file contains sensitive data patterns
     */
    public function hasSensitiveData(): bool
    {
        return !empty($this->sensitivePatterns);
    }

    /**
     * Get function by name
     */
    public function getFunction(string $name): ?array
    {
        foreach ($this->functions as $function) {
            if ($function['name'] === $name) {
                return $function;
            }
        }
        return null;
    }

    /**
     * Get class by name
     */
    public function getClass(string $name): ?array
    {
        foreach ($this->classes as $class) {
            if ($class['name'] === $name) {
                return $class;
            }
        }
        return null;
    }

    /**
     * Check if a function exists in context
     */
    public function hasFunction(string $name): bool
    {
        return $this->getFunction($name) !== null;
    }

    /**
     * Check if a class exists in context
     */
    public function hasClass(string $name): bool
    {
        return $this->getClass($name) !== null;
    }

    /**
     * Get security score based on context
     */
    public function getSecurityScore(): float
    {
        $score = 10.0;
        
        // Deduct points for risk factors
        if ($this->hasDatabaseOperations()) {
            $score -= 1.0;
        }
        
        if ($this->hasFileOperations()) {
            $score -= 0.5;
        }
        
        if ($this->hasSensitiveData()) {
            $score -= 2.0;
        }
        
        if (!empty($this->taintedVariables)) {
            $score -= min(2.0, count($this->taintedVariables) * 0.2);
        }
        
        if (!empty($this->inputSources)) {
            $score -= min(1.5, count($this->inputSources) * 0.15);
        }
        
        return max(0.0, $score);
    }

    /**
     * Export context as array
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'functions' => $this->functions,
            'classes' => $this->classes,
            'variables' => $this->variables,
            'input_sources' => $this->inputSources,
            'output_sinks' => $this->outputSinks,
            'authentication_points' => $this->authenticationPoints,
            'database_queries' => $this->databaseQueries,
            'file_operations' => $this->fileOperations,
            'sensitive_patterns' => $this->sensitivePatterns,
            'tainted_paths' => $this->taintedPaths,
            'tainted_variables' => $this->taintedVariables,
            'security_score' => $this->getSecurityScore(),
        ];
    }
}