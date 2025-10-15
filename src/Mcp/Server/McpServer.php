<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: MCP Server Implementation
 */

declare(strict_types=1);

namespace YcPca\Mcp\Server;

use YcPca\Mcp\Protocol\McpProtocol;
use YcPca\Mcp\Transport\TransportInterface;
use YcPca\Mcp\Transport\StdioTransport;
use YcPca\Mcp\McpException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * MCP Server
 * 
 * Implements a Model Context Protocol (MCP) server that can expose
 * PHP application functionality as Tools, Resources, and Prompts.
 * 
 * @package YcPca\Mcp\Server
 * @author YC
 * @version 1.0.0
 */
class McpServer
{
    private McpProtocol $protocol;
    private TransportInterface $transport;
    private LoggerInterface $logger;
    private array $config;
    private bool $running = false;
    
    private array $tools = [];
    private array $resources = [];
    private array $prompts = [];
    private array $capabilities = [];
    
    public function __construct(
        ?TransportInterface $transport = null,
        array $config = [],
        ?LoggerInterface $logger = null
    ) {
        $this->transport = $transport ?? new StdioTransport();
        $this->protocol = new McpProtocol();
        $this->logger = $logger ?? new NullLogger();
        
        $this->config = array_merge([
            'name' => 'PHP-MCP-Server',
            'version' => '1.0.0',
            'protocol_version' => '2024-11-05',
            'timeout' => 30,
            'max_request_size' => 1048576, // 1MB
            'enable_notifications' => true
        ], $config);
        
        $this->initializeCapabilities();
    }
    
    /**
     * Initialize server capabilities
     */
    private function initializeCapabilities(): void
    {
        $this->capabilities = [
            'tools' => true,
            'resources' => true,
            'prompts' => true,
            'logging' => true,
            'experimental' => [
                'sampling' => false
            ]
        ];
    }
    
    /**
     * Register a tool
     *
     * @param string $name Tool name
     * @param callable $handler Tool handler function
     * @param array $schema Tool schema definition
     * @param string $description Tool description
     * @return self
     */
    public function registerTool(
        string $name, 
        callable $handler, 
        array $schema = [], 
        string $description = ''
    ): self {
        $this->tools[$name] = [
            'name' => $name,
            'description' => $description ?: "Tool: {$name}",
            'inputSchema' => array_merge([
                'type' => 'object',
                'properties' => [],
                'required' => []
            ], $schema),
            'handler' => $handler
        ];
        
        $this->logger->info("Registered tool: {$name}");
        return $this;
    }
    
    /**
     * Register a resource
     *
     * @param string $uri Resource URI
     * @param callable $handler Resource handler function
     * @param string $name Resource name
     * @param string $description Resource description
     * @param string $mimeType Resource MIME type
     * @return self
     */
    public function registerResource(
        string $uri,
        callable $handler,
        string $name = '',
        string $description = '',
        string $mimeType = 'text/plain'
    ): self {
        $this->resources[$uri] = [
            'uri' => $uri,
            'name' => $name ?: basename($uri),
            'description' => $description ?: "Resource: {$uri}",
            'mimeType' => $mimeType,
            'handler' => $handler
        ];
        
        $this->logger->info("Registered resource: {$uri}");
        return $this;
    }
    
    /**
     * Register a prompt
     *
     * @param string $name Prompt name
     * @param callable $handler Prompt handler function
     * @param array $arguments Prompt arguments schema
     * @param string $description Prompt description
     * @return self
     */
    public function registerPrompt(
        string $name,
        callable $handler,
        array $arguments = [],
        string $description = ''
    ): self {
        $this->prompts[$name] = [
            'name' => $name,
            'description' => $description ?: "Prompt: {$name}",
            'arguments' => $arguments,
            'handler' => $handler
        ];
        
        $this->logger->info("Registered prompt: {$name}");
        return $this;
    }
    
    /**
     * Start the server
     *
     * @throws McpException
     */
    public function start(): void
    {
        if ($this->running) {
            throw new McpException('Server is already running');
        }
        
        $this->logger->info('Starting MCP server', [
            'name' => $this->config['name'],
            'version' => $this->config['version']
        ]);
        
        try {
            $this->transport->connect();
            $this->running = true;
            
            $this->sendInitialization();
            $this->messageLoop();
            
        } catch (\Exception $e) {
            $this->logger->error('Server error', ['error' => $e->getMessage()]);
            throw new McpException('Failed to start server: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Stop the server
     */
    public function stop(): void
    {
        if (!$this->running) {
            return;
        }
        
        $this->running = false;
        $this->transport->disconnect();
        
        $this->logger->info('MCP server stopped');
    }
    
    /**
     * Send server initialization
     */
    private function sendInitialization(): void
    {
        // Wait for initialize request
        while ($this->running) {
            $message = $this->transport->receive();
            if (!$message) {
                continue;
            }
            
            try {
                $request = $this->protocol->parseMessage($message);
                
                if ($request['method'] === 'initialize') {
                    $response = $this->handleInitialize($request);
                    $this->transport->send($this->protocol->createResponse(
                        $request['id'],
                        $response
                    ));
                    break;
                }
            } catch (\Exception $e) {
                $this->logger->error('Initialization error', ['error' => $e->getMessage()]);
            }
        }
    }
    
    /**
     * Main message processing loop
     */
    private function messageLoop(): void
    {
        while ($this->running) {
            try {
                $message = $this->transport->receive();
                if (!$message) {
                    continue;
                }
                
                $this->processMessage($message);
                
            } catch (\Exception $e) {
                $this->logger->error('Message processing error', ['error' => $e->getMessage()]);
                
                // Send error response if possible
                try {
                    $errorResponse = $this->protocol->createErrorResponse(
                        null,
                        -32603, // Internal error
                        'Internal server error'
                    );
                    $this->transport->send($errorResponse);
                } catch (\Exception $sendError) {
                    $this->logger->error('Failed to send error response', [
                        'error' => $sendError->getMessage()
                    ]);
                }
            }
        }
    }
    
    /**
     * Process incoming message
     *
     * @param string $message Raw message
     */
    private function processMessage(string $message): void
    {
        $request = $this->protocol->parseMessage($message);
        
        // Skip notifications (no response needed)
        if (!isset($request['id'])) {
            $this->handleNotification($request);
            return;
        }
        
        $response = null;
        
        try {
            $response = match ($request['method']) {
                'tools/list' => $this->handleToolsList($request),
                'tools/call' => $this->handleToolsCall($request),
                'resources/list' => $this->handleResourcesList($request),
                'resources/read' => $this->handleResourcesRead($request),
                'prompts/list' => $this->handlePromptsList($request),
                'prompts/get' => $this->handlePromptsGet($request),
                'ping' => ['pong' => true],
                default => throw new McpException("Unknown method: {$request['method']}")
            };
            
            $responseMessage = $this->protocol->createResponse($request['id'], $response);
            
        } catch (McpException $e) {
            $this->logger->warning('Request error', [
                'method' => $request['method'],
                'error' => $e->getMessage()
            ]);
            
            $responseMessage = $this->protocol->createErrorResponse(
                $request['id'],
                $e->getCode() ?: -32603,
                $e->getMessage()
            );
            
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error', [
                'method' => $request['method'],
                'error' => $e->getMessage()
            ]);
            
            $responseMessage = $this->protocol->createErrorResponse(
                $request['id'],
                -32603,
                'Internal server error'
            );
        }
        
        $this->transport->send($responseMessage);
    }
    
    /**
     * Handle initialization request
     *
     * @param array $request Request data
     * @return array Response data
     */
    private function handleInitialize(array $request): array
    {
        $clientInfo = $request['params'] ?? [];
        
        $this->logger->info('Client initialized', [
            'client' => $clientInfo['clientInfo']['name'] ?? 'Unknown',
            'version' => $clientInfo['clientInfo']['version'] ?? 'Unknown'
        ]);
        
        return [
            'protocolVersion' => $this->config['protocol_version'],
            'capabilities' => $this->capabilities,
            'serverInfo' => [
                'name' => $this->config['name'],
                'version' => $this->config['version']
            ]
        ];
    }
    
    /**
     * Handle notification (no response)
     *
     * @param array $notification Notification data
     */
    private function handleNotification(array $notification): void
    {
        match ($notification['method']) {
            'notifications/initialized' => $this->logger->info('Client ready'),
            'notifications/cancelled' => $this->handleCancellation($notification['params'] ?? []),
            default => $this->logger->debug('Unknown notification', ['method' => $notification['method']])
        };
    }
    
    /**
     * Handle request cancellation
     *
     * @param array $params Cancellation parameters
     */
    private function handleCancellation(array $params): void
    {
        $requestId = $params['requestId'] ?? null;
        $reason = $params['reason'] ?? 'Unknown';
        
        $this->logger->info('Request cancelled', [
            'requestId' => $requestId,
            'reason' => $reason
        ]);
    }
    
    /**
     * Handle tools/list request
     *
     * @param array $request Request data
     * @return array Response data
     */
    private function handleToolsList(array $request): array
    {
        $tools = [];
        
        foreach ($this->tools as $tool) {
            $tools[] = [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema']
            ];
        }
        
        return ['tools' => $tools];
    }
    
    /**
     * Handle tools/call request
     *
     * @param array $request Request data
     * @return array Response data
     */
    private function handleToolsCall(array $request): array
    {
        $params = $request['params'] ?? [];
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];
        
        if (!isset($this->tools[$toolName])) {
            throw new McpException("Unknown tool: {$toolName}", -32602);
        }
        
        $tool = $this->tools[$toolName];
        
        try {
            $result = ($tool['handler'])($arguments);
            
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT)
                    ]
                ],
                'isError' => false
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Tool execution error', [
                'tool' => $toolName,
                'error' => $e->getMessage()
            ]);
            
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: {$e->getMessage()}"
                    ]
                ],
                'isError' => true
            ];
        }
    }
    
    /**
     * Handle resources/list request
     *
     * @param array $request Request data
     * @return array Response data
     */
    private function handleResourcesList(array $request): array
    {
        $resources = [];
        
        foreach ($this->resources as $resource) {
            $resources[] = [
                'uri' => $resource['uri'],
                'name' => $resource['name'],
                'description' => $resource['description'],
                'mimeType' => $resource['mimeType']
            ];
        }
        
        return ['resources' => $resources];
    }
    
    /**
     * Handle resources/read request
     *
     * @param array $request Request data
     * @return array Response data
     */
    private function handleResourcesRead(array $request): array
    {
        $params = $request['params'] ?? [];
        $uri = $params['uri'] ?? '';
        
        if (!isset($this->resources[$uri])) {
            throw new McpException("Unknown resource: {$uri}", -32602);
        }
        
        $resource = $this->resources[$uri];
        
        try {
            $content = ($resource['handler'])($uri);
            
            return [
                'contents' => [
                    [
                        'uri' => $uri,
                        'mimeType' => $resource['mimeType'],
                        'text' => is_string($content) ? $content : json_encode($content, JSON_PRETTY_PRINT)
                    ]
                ]
            ];
            
        } catch (\Exception $e) {
            throw new McpException("Failed to read resource {$uri}: {$e->getMessage()}", -32603);
        }
    }
    
    /**
     * Handle prompts/list request
     *
     * @param array $request Request data
     * @return array Response data
     */
    private function handlePromptsList(array $request): array
    {
        $prompts = [];
        
        foreach ($this->prompts as $prompt) {
            $prompts[] = [
                'name' => $prompt['name'],
                'description' => $prompt['description'],
                'arguments' => $prompt['arguments']
            ];
        }
        
        return ['prompts' => $prompts];
    }
    
    /**
     * Handle prompts/get request
     *
     * @param array $request Request data
     * @return array Response data
     */
    private function handlePromptsGet(array $request): array
    {
        $params = $request['params'] ?? [];
        $promptName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];
        
        if (!isset($this->prompts[$promptName])) {
            throw new McpException("Unknown prompt: {$promptName}", -32602);
        }
        
        $prompt = $this->prompts[$promptName];
        
        try {
            $result = ($prompt['handler'])($arguments);
            
            return [
                'description' => $prompt['description'],
                'messages' => is_array($result) ? $result : [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT)
                            ]
                        ]
                    ]
                ]
            ];
            
        } catch (\Exception $e) {
            throw new McpException("Failed to execute prompt {$promptName}: {$e->getMessage()}", -32603);
        }
    }
    
    /**
     * Send notification to client
     *
     * @param string $method Notification method
     * @param array $params Notification parameters
     */
    public function sendNotification(string $method, array $params = []): void
    {
        if (!$this->running) {
            return;
        }
        
        try {
            $notification = $this->protocol->createNotification($method, $params);
            $this->transport->send($notification);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send notification', [
                'method' => $method,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Log message to client
     *
     * @param string $level Log level
     * @param string $data Log data
     * @param ?string $logger Logger name
     */
    public function log(string $level, string $data, ?string $logger = null): void
    {
        $this->sendNotification('notifications/message', [
            'level' => $level,
            'logger' => $logger ?? $this->config['name'],
            'data' => $data
        ]);
    }
    
    /**
     * Get server statistics
     *
     * @return array Statistics data
     */
    public function getStats(): array
    {
        return [
            'running' => $this->running,
            'tools_count' => count($this->tools),
            'resources_count' => count($this->resources),
            'prompts_count' => count($this->prompts),
            'config' => [
                'name' => $this->config['name'],
                'version' => $this->config['version'],
                'protocol_version' => $this->config['protocol_version']
            ]
        ];
    }
}