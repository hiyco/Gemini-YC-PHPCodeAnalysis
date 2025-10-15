<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: MCP Protocol Implementation
 */

declare(strict_types=1);

namespace YcPca\Mcp\Protocol;

use YcPca\Mcp\McpException;
use YcPca\Mcp\McpProtocolException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * MCP (Model Context Protocol) Implementation
 * 
 * Implements the core MCP protocol as defined in the specification.
 * Handles message serialization, deserialization, and validation.
 * 
 * @package YcPca\Mcp\Protocol
 * @author YC
 * @version 1.0.0
 */
class McpProtocol
{
    /**
     * Protocol version
     */
    public const VERSION = '2024-11-05';
    
    /**
     * Message types
     */
    public const MSG_REQUEST = 'request';
    public const MSG_RESPONSE = 'response';
    public const MSG_NOTIFICATION = 'notification';
    
    /**
     * Standard methods
     */
    public const METHOD_INITIALIZE = 'initialize';
    public const METHOD_INITIALIZED = 'initialized';
    public const METHOD_TOOLS_LIST = 'tools/list';
    public const METHOD_TOOLS_CALL = 'tools/call';
    public const METHOD_RESOURCES_LIST = 'resources/list';
    public const METHOD_RESOURCES_READ = 'resources/read';
    public const METHOD_PROMPTS_LIST = 'prompts/list';
    public const METHOD_PROMPTS_GET = 'prompts/get';
    public const METHOD_COMPLETION = 'completion/complete';
    public const METHOD_LOGGING = 'notifications/message';
    
    private LoggerInterface $logger;
    private array $capabilities = [];
    private bool $initialized = false;
    
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }
    
    /**
     * Create MCP request message
     *
     * @param string $method Method name
     * @param array $params Parameters
     * @param int|string|null $id Request ID
     * @return array MCP message
     */
    public function createRequest(string $method, array $params = [], int|string|null $id = null): array
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params
        ];
        
        if ($id !== null) {
            $message['id'] = $id;
        }
        
        $this->logger->debug('Created MCP request', [
            'method' => $method,
            'id' => $id,
            'params_count' => count($params)
        ]);
        
        return $message;
    }
    
    /**
     * Create MCP response message
     *
     * @param int|string $id Request ID
     * @param mixed $result Result data
     * @return array MCP message
     */
    public function createResponse(int|string $id, mixed $result): array
    {
        $message = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        ];
        
        $this->logger->debug('Created MCP response', ['id' => $id]);
        
        return $message;
    }
    
    /**
     * Create MCP error response
     *
     * @param int|string $id Request ID
     * @param int $code Error code
     * @param string $message Error message
     * @param mixed $data Additional error data
     * @return array MCP error message
     */
    public function createError(int|string $id, int $code, string $message, mixed $data = null): array
    {
        $error = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
        
        if ($data !== null) {
            $error['error']['data'] = $data;
        }
        
        $this->logger->warning('Created MCP error response', [
            'id' => $id,
            'code' => $code,
            'message' => $message
        ]);
        
        return $error;
    }
    
    /**
     * Create MCP notification message
     *
     * @param string $method Method name
     * @param array $params Parameters
     * @return array MCP message
     */
    public function createNotification(string $method, array $params = []): array
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params
        ];
        
        $this->logger->debug('Created MCP notification', [
            'method' => $method,
            'params_count' => count($params)
        ]);
        
        return $message;
    }
    
    /**
     * Parse and validate MCP message
     *
     * @param string $json JSON message
     * @return array Parsed message
     * @throws McpProtocolException
     */
    public function parseMessage(string $json): array
    {
        try {
            $message = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new McpProtocolException("Invalid JSON: {$e->getMessage()}", 0, $e);
        }
        
        $this->validateMessage($message);
        
        $this->logger->debug('Parsed MCP message', [
            'type' => $this->getMessageType($message),
            'method' => $message['method'] ?? 'N/A',
            'id' => $message['id'] ?? 'N/A'
        ]);
        
        return $message;
    }
    
    /**
     * Serialize message to JSON
     *
     * @param array $message Message array
     * @return string JSON string
     * @throws McpProtocolException
     */
    public function serializeMessage(array $message): string
    {
        try {
            $json = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            
            $this->logger->debug('Serialized MCP message', [
                'size' => strlen($json),
                'type' => $this->getMessageType($message)
            ]);
            
            return $json;
        } catch (\JsonException $e) {
            throw new McpProtocolException("JSON serialization error: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * Validate MCP message structure
     *
     * @param array $message Message to validate
     * @throws McpProtocolException
     */
    public function validateMessage(array $message): void
    {
        // Check required jsonrpc field
        if (!isset($message['jsonrpc']) || $message['jsonrpc'] !== '2.0') {
            throw new McpProtocolException('Missing or invalid jsonrpc field');
        }
        
        // Determine message type and validate accordingly
        if (isset($message['method'])) {
            // Request or notification
            $this->validateMethodMessage($message);
        } elseif (isset($message['result']) || isset($message['error'])) {
            // Response
            $this->validateResponseMessage($message);
        } else {
            throw new McpProtocolException('Invalid message structure');
        }
    }
    
    /**
     * Validate method message (request/notification)
     *
     * @param array $message Message to validate
     * @throws McpProtocolException
     */
    private function validateMethodMessage(array $message): void
    {
        if (!is_string($message['method']) || empty($message['method'])) {
            throw new McpProtocolException('Invalid method field');
        }
        
        // Params field is optional but must be array if present
        if (isset($message['params']) && !is_array($message['params'])) {
            throw new McpProtocolException('Params field must be array');
        }
        
        // Request must have id, notification must not
        $hasId = isset($message['id']);
        $isNotification = str_starts_with($message['method'], 'notifications/');
        
        if ($isNotification && $hasId) {
            throw new McpProtocolException('Notification cannot have id field');
        }
        
        if (!$isNotification && !$hasId) {
            throw new McpProtocolException('Request must have id field');
        }
    }
    
    /**
     * Validate response message
     *
     * @param array $message Message to validate
     * @throws McpProtocolException
     */
    private function validateResponseMessage(array $message): void
    {
        if (!isset($message['id'])) {
            throw new McpProtocolException('Response must have id field');
        }
        
        $hasResult = isset($message['result']);
        $hasError = isset($message['error']);
        
        if ($hasResult && $hasError) {
            throw new McpProtocolException('Response cannot have both result and error');
        }
        
        if (!$hasResult && !$hasError) {
            throw new McpProtocolException('Response must have either result or error');
        }
        
        if ($hasError) {
            $this->validateErrorObject($message['error']);
        }
    }
    
    /**
     * Validate error object
     *
     * @param mixed $error Error object to validate
     * @throws McpProtocolException
     */
    private function validateErrorObject(mixed $error): void
    {
        if (!is_array($error)) {
            throw new McpProtocolException('Error must be object');
        }
        
        if (!isset($error['code']) || !is_int($error['code'])) {
            throw new McpProtocolException('Error must have integer code');
        }
        
        if (!isset($error['message']) || !is_string($error['message'])) {
            throw new McpProtocolException('Error must have string message');
        }
    }
    
    /**
     * Get message type
     *
     * @param array $message Message
     * @return string Message type
     */
    public function getMessageType(array $message): string
    {
        if (isset($message['method'])) {
            return isset($message['id']) ? self::MSG_REQUEST : self::MSG_NOTIFICATION;
        }
        
        return self::MSG_RESPONSE;
    }
    
    /**
     * Check if message is request
     *
     * @param array $message Message
     * @return bool True if request
     */
    public function isRequest(array $message): bool
    {
        return $this->getMessageType($message) === self::MSG_REQUEST;
    }
    
    /**
     * Check if message is response
     *
     * @param array $message Message
     * @return bool True if response
     */
    public function isResponse(array $message): bool
    {
        return $this->getMessageType($message) === self::MSG_RESPONSE;
    }
    
    /**
     * Check if message is notification
     *
     * @param array $message Message
     * @return bool True if notification
     */
    public function isNotification(array $message): bool
    {
        return $this->getMessageType($message) === self::MSG_NOTIFICATION;
    }
    
    /**
     * Set capabilities
     *
     * @param array $capabilities Capabilities
     */
    public function setCapabilities(array $capabilities): void
    {
        $this->capabilities = $capabilities;
        
        $this->logger->info('MCP capabilities set', [
            'capabilities' => array_keys($capabilities)
        ]);
    }
    
    /**
     * Get capabilities
     *
     * @return array Capabilities
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }
    
    /**
     * Mark as initialized
     */
    public function markInitialized(): void
    {
        $this->initialized = true;
        $this->logger->info('MCP protocol initialized');
    }
    
    /**
     * Check if initialized
     *
     * @return bool True if initialized
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }
    
    /**
     * Create initialize request
     *
     * @param array $clientInfo Client information
     * @param array $capabilities Client capabilities
     * @return array Initialize request
     */
    public function createInitializeRequest(array $clientInfo, array $capabilities): array
    {
        return $this->createRequest(self::METHOD_INITIALIZE, [
            'protocolVersion' => self::VERSION,
            'clientInfo' => $clientInfo,
            'capabilities' => $capabilities
        ]);
    }
    
    /**
     * Create initialize response
     *
     * @param array $serverInfo Server information
     * @param array $capabilities Server capabilities
     * @return array Initialize response
     */
    public function createInitializeResponse(int|string $id, array $serverInfo, array $capabilities): array
    {
        return $this->createResponse($id, [
            'protocolVersion' => self::VERSION,
            'serverInfo' => $serverInfo,
            'capabilities' => $capabilities
        ]);
    }
}