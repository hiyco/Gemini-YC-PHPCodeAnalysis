<?php

declare(strict_types=1);

namespace YcPca\Performance;

/**
 * Performance analysis context containing relevant metrics and patterns
 */
class PerformanceContext
{
    private string $filePath;
    private array $loops = [];
    private array $recursiveFunctions = [];
    private array $databaseOperations = [];
    private array $fileOperations = [];
    private array $memoryOperations = [];
    private array $reflectionUsage = [];
    private array $serializationPoints = [];
    private array $functionCalls = [];
    private array $objectCreations = [];
    private array $cacheUsage = [];

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    // Setters
    public function setLoops(array $loops): void
    {
        $this->loops = $loops;
    }

    public function setRecursiveFunctions(array $recursiveFunctions): void
    {
        $this->recursiveFunctions = $recursiveFunctions;
    }

    public function setDatabaseOperations(array $databaseOperations): void
    {
        $this->databaseOperations = $databaseOperations;
    }

    public function setFileOperations(array $fileOperations): void
    {
        $this->fileOperations = $fileOperations;
    }

    public function setMemoryOperations(array $memoryOperations): void
    {
        $this->memoryOperations = $memoryOperations;
    }

    public function setReflectionUsage(array $reflectionUsage): void
    {
        $this->reflectionUsage = $reflectionUsage;
    }

    public function setSerializationPoints(array $serializationPoints): void
    {
        $this->serializationPoints = $serializationPoints;
    }

    public function setFunctionCalls(array $functionCalls): void
    {
        $this->functionCalls = $functionCalls;
    }

    public function setObjectCreations(array $objectCreations): void
    {
        $this->objectCreations = $objectCreations;
    }

    public function setCacheUsage(array $cacheUsage): void
    {
        $this->cacheUsage = $cacheUsage;
    }

    // Getters
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getLoops(): array
    {
        return $this->loops;
    }

    public function getRecursiveFunctions(): array
    {
        return $this->recursiveFunctions;
    }

    public function getDatabaseOperations(): array
    {
        return $this->databaseOperations;
    }

    public function getFileOperations(): array
    {
        return $this->fileOperations;
    }

    public function getMemoryOperations(): array
    {
        return $this->memoryOperations;
    }

    public function getReflectionUsage(): array
    {
        return $this->reflectionUsage;
    }

    public function getSerializationPoints(): array
    {
        return $this->serializationPoints;
    }

    public function getFunctionCalls(): array
    {
        return $this->functionCalls;
    }

    public function getObjectCreations(): array
    {
        return $this->objectCreations;
    }

    public function getCacheUsage(): array
    {
        return $this->cacheUsage;
    }

    /**
     * Get nested loop information
     */
    public function getNestedLoops(): array
    {
        return array_filter($this->loops, function ($loop) {
            return ($loop['nested_level'] ?? 0) > 0;
        });
    }

    /**
     * Get maximum nesting level
     */
    public function getMaxNestingLevel(): int
    {
        $maxLevel = 0;
        foreach ($this->loops as $loop) {
            $maxLevel = max($maxLevel, $loop['nested_level'] ?? 0);
        }
        return $maxLevel;
    }

    /**
     * Check if file has recursive functions
     */
    public function hasRecursion(): bool
    {
        return !empty($this->recursiveFunctions);
    }

    /**
     * Check if file has database operations
     */
    public function hasDatabaseOperations(): bool
    {
        return !empty($this->databaseOperations);
    }

    /**
     * Check if file has reflection usage
     */
    public function hasReflection(): bool
    {
        return !empty($this->reflectionUsage);
    }

    /**
     * Calculate complexity score
     */
    public function getComplexityScore(): float
    {
        $score = 0.0;
        
        // Loop complexity
        foreach ($this->loops as $loop) {
            $nestingLevel = $loop['nested_level'] ?? 0;
            $score += pow(2, $nestingLevel); // Exponential increase for nesting
        }
        
        // Recursion penalty
        $score += count($this->recursiveFunctions) * 5;
        
        // Database operations
        $score += count($this->databaseOperations) * 2;
        
        // File operations
        $score += count($this->fileOperations) * 1.5;
        
        // Reflection overhead
        $score += count($this->reflectionUsage) * 3;
        
        // Serialization overhead
        $score += count($this->serializationPoints) * 2;
        
        return $score;
    }

    /**
     * Get performance hotspots
     */
    public function getHotspots(): array
    {
        $hotspots = [];
        
        // Nested loops are hotspots
        foreach ($this->getNestedLoops() as $loop) {
            $hotspots[] = [
                'type' => 'nested_loop',
                'line' => $loop['line'],
                'severity' => $loop['nested_level'] >= 2 ? 'HIGH' : 'MEDIUM',
            ];
        }
        
        // Recursive functions
        foreach ($this->recursiveFunctions as $func) {
            $hotspots[] = [
                'type' => 'recursion',
                'function' => $func['name'] ?? 'anonymous',
                'line' => $func['line'] ?? 0,
                'severity' => 'HIGH',
            ];
        }
        
        // Heavy reflection usage
        if (count($this->reflectionUsage) > 5) {
            $hotspots[] = [
                'type' => 'excessive_reflection',
                'count' => count($this->reflectionUsage),
                'severity' => 'MEDIUM',
            ];
        }
        
        return $hotspots;
    }

    /**
     * Export context as array
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'loops' => $this->loops,
            'recursive_functions' => $this->recursiveFunctions,
            'database_operations' => $this->databaseOperations,
            'file_operations' => $this->fileOperations,
            'memory_operations' => $this->memoryOperations,
            'reflection_usage' => $this->reflectionUsage,
            'serialization_points' => $this->serializationPoints,
            'function_calls' => $this->functionCalls,
            'object_creations' => $this->objectCreations,
            'cache_usage' => $this->cacheUsage,
            'complexity_score' => $this->getComplexityScore(),
            'hotspots' => $this->getHotspots(),
        ];
    }
}