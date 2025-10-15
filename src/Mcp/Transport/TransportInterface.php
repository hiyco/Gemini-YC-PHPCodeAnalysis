<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: Transport Interface for MCP Communication
 */

declare(strict_types=1);

namespace YcPca\Mcp\Transport;

use YcPca\Mcp\McpTransportException;

/**
 * Transport Interface
 * 
 * Defines the contract for MCP transport implementations.
 * Supports various transport mechanisms like STDIO, WebSocket, HTTP, etc.
 * 
 * @package YcPca\Mcp\Transport
 * @author YC
 * @version 1.0.0
 */
interface TransportInterface
{
    /**
     * Send message
     *
     * @param string $message JSON-RPC message
     * @return bool Success status
     * @throws McpTransportException
     */
    public function send(string $message): bool;
    
    /**
     * Receive message
     *
     * @param int|null $timeout Timeout in seconds (null = blocking)
     * @return string|null Received message or null on timeout
     * @throws McpTransportException
     */
    public function receive(?int $timeout = null): ?string;
    
    /**
     * Connect/initialize transport
     *
     * @return bool Success status
     * @throws McpTransportException
     */
    public function connect(): bool;
    
    /**
     * Disconnect/cleanup transport
     *
     * @return bool Success status
     */
    public function disconnect(): bool;
    
    /**
     * Check if transport is connected
     *
     * @return bool Connection status
     */
    public function isConnected(): bool;
    
    /**
     * Get transport statistics
     *
     * @return array Transport statistics
     */
    public function getStats(): array;
}