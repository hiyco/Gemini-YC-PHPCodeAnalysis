<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: High-performance PHP AST Parser with caching and error recovery
 */

namespace YcPca\Ast;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use YcPca\Exception\ParseException;
use YcPca\Model\AnalysisResult;
use YcPca\Model\FileContext;

/**
 * Professional PHP AST Parser with advanced features
 * 
 * Features:
 * - High-performance parsing with caching
 * - Error recovery and partial parsing
 * - Memory management and optimization
 * - Visitor pattern support
 * - Detailed parsing metrics
 */
class PhpAstParser
{
    private Parser $parser;
    private NodeTraverser $traverser;
    private Standard $printer;
    private LoggerInterface $logger;
    
    /** @var array<string, mixed> LRU cache for parsed ASTs */
    private array $astCache = [];
    private int $maxCacheSize;
    private array $cacheAccess = [];
    
    /** @var NodeVisitor[] */
    private array $visitors = [];
    
    /** @var array<string, mixed> Parsing statistics */
    private array $stats = [
        'files_parsed' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'parse_errors' => 0,
        'total_parse_time' => 0.0,
        'memory_peak' => 0,
    ];

    public function __construct(
        ?LoggerInterface $logger = null,
        int $maxCacheSize = 1000
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->maxCacheSize = $maxCacheSize;
        
        // Initialize PHP parser with PHP 8.x support
        $this->parser = (new ParserFactory())->create(
            ParserFactory::PREFER_PHP7,
            new PhpLexer()
        );
        
        $this->traverser = new NodeTraverser();
        $this->printer = new Standard();
        
        $this->logger->info('PhpAstParser initialized', [
            'max_cache_size' => $maxCacheSize,
            'parser_kind' => 'PHP8_COMPATIBLE'
        ]);
    }

    /**
     * Parse PHP file and return AST with analysis context
     */
    public function parseFile(string $filePath): AnalysisResult
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        try {
            if (!file_exists($filePath)) {
                throw new ParseException("File not found: {$filePath}");
            }
            
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                throw new ParseException("Cannot read file: {$filePath}");
            }
            
            $fileHash = $this->calculateFileHash($fileContent);
            $cacheKey = "{$filePath}:{$fileHash}";
            
            // Check cache first
            if ($this->hasValidCache($cacheKey)) {
                $this->stats['cache_hits']++;
                $this->updateCacheAccess($cacheKey);
                
                $result = $this->astCache[$cacheKey];
                $this->logger->debug('AST cache hit', ['file' => $filePath]);
                
                return $result;
            }
            
            // Parse the file
            $ast = $this->parseCode($fileContent, $filePath);
            $context = $this->createFileContext($filePath, $fileContent, $ast);
            
            // Create analysis result
            $result = new AnalysisResult(
                filePath: $filePath,
                ast: $ast,
                context: $context,
                parseTime: microtime(true) - $startTime,
                memoryUsage: memory_get_usage(true) - $startMemory
            );
            
            // Cache the result
            $this->cacheResult($cacheKey, $result);
            
            $this->stats['cache_misses']++;
            $this->stats['files_parsed']++;
            $this->updateParsingStats($startTime, $startMemory);
            
            $this->logger->info('File parsed successfully', [
                'file' => $filePath,
                'nodes' => count($ast),
                'parse_time' => $result->getParseTime(),
                'memory_used' => $result->getMemoryUsage()
            ]);
            
            return $result;
            
        } catch (Error $e) {
            $this->stats['parse_errors']++;
            $this->logger->error('Parse error', [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'line' => $e->getStartLine()
            ]);
            
            // Try partial parsing for error recovery
            return $this->attemptPartialParsing($filePath, $fileContent, $e);
            
        } catch (\Throwable $e) {
            $this->stats['parse_errors']++;
            $this->logger->error('Unexpected parsing error', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            
            throw new ParseException(
                "Failed to parse file {$filePath}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Parse PHP code string directly
     */
    public function parseCode(string $code, string $context = 'inline'): array
    {
        try {
            $ast = $this->parser->parse($code);
            return $ast ?? [];
        } catch (Error $e) {
            $this->logger->warning('Code parsing failed, attempting recovery', [
                'context' => $context,
                'error' => $e->getMessage()
            ]);
            
            // Try to parse with error recovery
            return $this->attemptErrorRecovery($code, $e);
        }
    }

    /**
     * Add visitor for AST traversal
     */
    public function addVisitor(NodeVisitor $visitor): self
    {
        $this->visitors[] = $visitor;
        $this->traverser->addVisitor($visitor);
        
        $this->logger->debug('Visitor added', [
            'visitor_class' => get_class($visitor)
        ]);
        
        return $this;
    }

    /**
     * Remove all visitors
     */
    public function clearVisitors(): self
    {
        $this->visitors = [];
        $this->traverser = new NodeTraverser();
        
        $this->logger->debug('All visitors cleared');
        
        return $this;
    }

    /**
     * Traverse AST with registered visitors
     */
    public function traverse(array $ast): array
    {
        if (empty($this->visitors)) {
            $this->logger->warning('No visitors registered for AST traversal');
            return $ast;
        }
        
        return $this->traverser->traverse($ast);
    }

    /**
     * Get parsing statistics
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'cache_hit_rate' => $this->calculateCacheHitRate(),
            'avg_parse_time' => $this->calculateAverageParseTime(),
            'cache_size' => count($this->astCache),
            'memory_current' => memory_get_usage(true),
        ]);
    }

    /**
     * Clear all caches and reset statistics
     */
    public function reset(): self
    {
        $this->astCache = [];
        $this->cacheAccess = [];
        $this->stats = array_fill_keys(array_keys($this->stats), 0);
        
        $this->logger->info('Parser reset - caches cleared and stats reset');
        
        return $this;
    }

    /**
     * Calculate file hash for caching
     */
    private function calculateFileHash(string $content): string
    {
        return md5($content);
    }

    /**
     * Check if cache entry is valid
     */
    private function hasValidCache(string $key): bool
    {
        return isset($this->astCache[$key]);
    }

    /**
     * Update cache access tracking for LRU
     */
    private function updateCacheAccess(string $key): void
    {
        $this->cacheAccess[$key] = time();
    }

    /**
     * Cache parsing result with LRU eviction
     */
    private function cacheResult(string $key, AnalysisResult $result): void
    {
        // Implement LRU cache eviction
        if (count($this->astCache) >= $this->maxCacheSize) {
            $this->evictLeastRecentlyUsed();
        }
        
        $this->astCache[$key] = $result;
        $this->updateCacheAccess($key);
    }

    /**
     * Evict least recently used cache entries
     */
    private function evictLeastRecentlyUsed(): void
    {
        if (empty($this->cacheAccess)) {
            return;
        }
        
        // Sort by access time and remove oldest entries
        asort($this->cacheAccess);
        $keysToRemove = array_slice(array_keys($this->cacheAccess), 0, 100);
        
        foreach ($keysToRemove as $key) {
            unset($this->astCache[$key], $this->cacheAccess[$key]);
        }
        
        $this->logger->debug('Cache eviction completed', [
            'evicted_count' => count($keysToRemove)
        ]);
    }

    /**
     * Create file context from parsed content
     */
    private function createFileContext(string $filePath, string $content, array $ast): FileContext
    {
        return new FileContext(
            filePath: $filePath,
            content: $content,
            lines: explode("\n", $content),
            size: strlen($content),
            nodeCount: count($ast, COUNT_RECURSIVE),
            encoding: mb_detect_encoding($content, 'UTF-8,ISO-8859-1', true) ?: 'UTF-8'
        );
    }

    /**
     * Attempt partial parsing for error recovery
     */
    private function attemptPartialParsing(string $filePath, string $content, Error $error): AnalysisResult
    {
        $this->logger->info('Attempting partial parsing for error recovery', [
            'file' => $filePath,
            'original_error' => $error->getMessage()
        ]);
        
        // Try to parse as much as possible before the error
        $lines = explode("\n", $content);
        $errorLine = $error->getStartLine();
        
        if ($errorLine > 1) {
            $partialContent = implode("\n", array_slice($lines, 0, $errorLine - 1));
            $partialContent .= "\n<?php // Parsing stopped due to syntax error";
            
            try {
                $partialAst = $this->parseCode($partialContent, "partial:{$filePath}");
                $context = $this->createFileContext($filePath, $content, $partialAst);
                
                return new AnalysisResult(
                    filePath: $filePath,
                    ast: $partialAst,
                    context: $context,
                    parseTime: 0,
                    memoryUsage: 0,
                    hasErrors: true,
                    errors: [$error->getMessage()]
                );
            } catch (\Throwable $e) {
                $this->logger->error('Partial parsing also failed', [
                    'file' => $filePath,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Return minimal result with error information
        $context = $this->createFileContext($filePath, $content, []);
        
        return new AnalysisResult(
            filePath: $filePath,
            ast: [],
            context: $context,
            parseTime: 0,
            memoryUsage: 0,
            hasErrors: true,
            errors: [$error->getMessage()]
        );
    }

    /**
     * Attempt error recovery during parsing
     */
    private function attemptErrorRecovery(string $code, Error $error): array
    {
        // This is a simplified error recovery
        // In production, you might want more sophisticated recovery strategies
        $this->logger->debug('Basic error recovery attempted', [
            'error' => $error->getMessage()
        ]);
        
        return [];
    }

    /**
     * Update parsing statistics
     */
    private function updateParsingStats(float $startTime, int $startMemory): void
    {
        $parseTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;
        $memoryPeak = memory_get_peak_usage(true);
        
        $this->stats['total_parse_time'] += $parseTime;
        $this->stats['memory_peak'] = max($this->stats['memory_peak'], $memoryPeak);
    }

    /**
     * Calculate cache hit rate percentage
     */
    private function calculateCacheHitRate(): float
    {
        $total = $this->stats['cache_hits'] + $this->stats['cache_misses'];
        
        return $total > 0 ? ($this->stats['cache_hits'] / $total) * 100 : 0;
    }

    /**
     * Calculate average parse time
     */
    private function calculateAverageParseTime(): float
    {
        return $this->stats['files_parsed'] > 0 
            ? $this->stats['total_parse_time'] / $this->stats['files_parsed']
            : 0;
    }
}