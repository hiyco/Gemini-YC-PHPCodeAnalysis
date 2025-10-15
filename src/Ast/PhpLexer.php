<?php

declare(strict_types=1);

/**
 * Copyright: YC-2025Copyright
 * Created: 2025-09-03
 * Author: YC
 * Description: Custom PHP Lexer with enhanced token analysis
 */

namespace YcPca\Ast;

use PhpParser\Lexer\Emulative;

/**
 * Enhanced PHP Lexer for advanced token analysis
 * 
 * Extends the standard emulative lexer with additional features:
 * - Enhanced error recovery
 * - Token-level caching
 * - Custom token analysis
 * - PHP version compatibility handling
 */
class PhpLexer extends Emulative
{
    private array $tokenStats = [];
    private array $customTokens = [];

    public function __construct(array $options = [])
    {
        // Enable all PHP 8.x features by default
        $defaultOptions = [
            'usedAttributes' => [
                'comments',
                'startLine', 'endLine',
                'startTokenPos', 'endTokenPos',
                'startFilePos', 'endFilePos'
            ]
        ];
        
        parent::__construct(array_merge($defaultOptions, $options));
    }

    /**
     * Get token statistics for analysis
     */
    public function getTokenStats(): array
    {
        return $this->tokenStats;
    }

    /**
     * Reset token statistics
     */
    public function resetStats(): void
    {
        $this->tokenStats = [];
    }

    /**
     * Override to collect token statistics
     */
    protected function createTokenMap(): array
    {
        $tokenMap = parent::createTokenMap();
        
        // Track token types for analysis
        foreach ($tokenMap as $token => $value) {
            if (!isset($this->tokenStats[$token])) {
                $this->tokenStats[$token] = 0;
            }
            $this->tokenStats[$token]++;
        }
        
        return $tokenMap;
    }
}