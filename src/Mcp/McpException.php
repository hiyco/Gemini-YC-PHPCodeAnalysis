<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: MCP Exception Classes
 */

declare(strict_types=1);

namespace YcPca\Mcp;

use Exception;
use Throwable;

/**
 * Base MCP Exception
 */
class McpException extends Exception
{
    protected array $context = [];
    
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
}

/**
 * Protocol-related exceptions
 */
class McpProtocolException extends McpException
{
}

/**
 * Transport-related exceptions
 */
class McpTransportException extends McpException
{
}

/**
 * Model provider exceptions
 */
class McpModelException extends McpException
{
}

/**
 * Configuration exceptions
 */
class McpConfigException extends McpException
{
}

/**
 * Server exceptions
 */
class McpServerException extends McpException
{
}

/**
 * Client exceptions
 */
class McpClientException extends McpException
{
}