<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Base AST Visitor with context management and performance tracking
 */

namespace YcPca\Ast;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Enhanced base visitor for AST traversal with advanced features
 * 
 * Features:
 * - Context stack management
 * - Performance tracking
 * - Node statistics
 * - Error handling
 * - Extensible visitor pattern
 */
abstract class BaseVisitor extends NodeVisitorAbstract
{
    protected LoggerInterface $logger;
    protected array $contextStack = [];
    protected array $visitedNodes = [];
    protected array $nodeStats = [];
    protected float $startTime;
    protected int $visitCount = 0;
    
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->startTime = microtime(true);
        
        $this->logger->debug('Base visitor initialized', [
            'visitor_class' => static::class
        ]);
    }

    /**
     * Called when traversal starts
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->resetState();
        
        $this->logger->info('AST traversal started', [
            'visitor' => static::class,
            'node_count' => count($nodes)
        ]);
        
        return $this->onBeforeTraverse($nodes);
    }

    /**
     * Called when traversal ends
     */
    public function afterTraverse(array $nodes): ?array
    {
        $duration = microtime(true) - $this->startTime;
        
        $this->logger->info('AST traversal completed', [
            'visitor' => static::class,
            'duration' => $duration,
            'nodes_visited' => $this->visitCount,
            'unique_types' => count($this->nodeStats)
        ]);
        
        return $this->onAfterTraverse($nodes);
    }

    /**
     * Called when entering a node
     */
    public function enterNode(Node $node): ?Node
    {
        $this->visitCount++;
        $nodeType = $node->getType();
        
        // Update statistics
        $this->nodeStats[$nodeType] = ($this->nodeStats[$nodeType] ?? 0) + 1;
        $this->visitedNodes[] = $node;
        
        // Push node context
        $this->pushContext($node);
        
        $this->logger->debug('Entering node', [
            'type' => $nodeType,
            'line' => $node->getStartLine(),
            'context_depth' => count($this->contextStack)
        ]);
        
        return $this->onEnterNode($node);
    }

    /**
     * Called when leaving a node
     */
    public function leaveNode(Node $node): ?Node
    {
        $nodeType = $node->getType();
        
        $this->logger->debug('Leaving node', [
            'type' => $nodeType,
            'line' => $node->getStartLine()
        ]);
        
        $result = $this->onLeaveNode($node);
        
        // Pop node context
        $this->popContext();
        
        return $result;
    }

    /**
     * Get current context information
     */
    protected function getCurrentContext(): array
    {
        return end($this->contextStack) ?: [];
    }

    /**
     * Get parent node from context stack
     */
    protected function getParentNode(): ?Node
    {
        $stackSize = count($this->contextStack);
        return $stackSize > 1 ? $this->contextStack[$stackSize - 2]['node'] : null;
    }

    /**
     * Get ancestor nodes up to specified depth
     */
    protected function getAncestors(int $depth = -1): array
    {
        $ancestors = [];
        $stack = array_reverse($this->contextStack);
        
        foreach ($stack as $i => $context) {
            if ($depth >= 0 && $i >= $depth) {
                break;
            }
            $ancestors[] = $context['node'];
        }
        
        return $ancestors;
    }

    /**
     * Check if we're inside a specific node type
     */
    protected function isInsideNodeType(string $nodeType): bool
    {
        foreach ($this->contextStack as $context) {
            if ($context['node']->getType() === $nodeType) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get visitor statistics
     */
    public function getStats(): array
    {
        return [
            'visitor_class' => static::class,
            'nodes_visited' => $this->visitCount,
            'traversal_time' => microtime(true) - $this->startTime,
            'node_types' => $this->nodeStats,
            'context_depth' => count($this->contextStack),
            'memory_usage' => memory_get_usage(true)
        ];
    }

    /**
     * Reset visitor state for new traversal
     */
    protected function resetState(): void
    {
        $this->contextStack = [];
        $this->visitedNodes = [];
        $this->nodeStats = [];
        $this->visitCount = 0;
        $this->startTime = microtime(true);
    }

    /**
     * Push node context onto stack
     */
    private function pushContext(Node $node): void
    {
        $this->contextStack[] = [
            'node' => $node,
            'type' => $node->getType(),
            'line' => $node->getStartLine(),
            'depth' => count($this->contextStack),
            'entered_at' => microtime(true)
        ];
    }

    /**
     * Pop node context from stack
     */
    private function popContext(): void
    {
        if (!empty($this->contextStack)) {
            array_pop($this->contextStack);
        }
    }

    // Abstract methods for subclasses to implement

    /**
     * Override to handle traversal start
     */
    protected function onBeforeTraverse(array $nodes): ?array
    {
        return null;
    }

    /**
     * Override to handle traversal end
     */
    protected function onAfterTraverse(array $nodes): ?array
    {
        return null;
    }

    /**
     * Override to handle node entry
     */
    protected function onEnterNode(Node $node): ?Node
    {
        return null;
    }

    /**
     * Override to handle node exit
     */
    protected function onLeaveNode(Node $node): ?Node
    {
        return null;
    }
}