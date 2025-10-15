<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: WebSocket Transport Implementation for MCP
 */

declare(strict_types=1);

namespace YcPca\Mcp\Transport;

use YcPca\Mcp\McpTransportException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * WebSocket Transport Implementation
 * 
 * Implements MCP communication over WebSocket connections.
 * Useful for browser-based clients or when bidirectional communication is needed.
 * 
 * @package YcPca\Mcp\Transport
 * @author YC
 * @version 1.0.0
 */
class WebSocketTransport implements TransportInterface
{
    private LoggerInterface $logger;
    private array $config;
    private bool $connected = false;
    private array $stats = [
        'messages_sent' => 0,
        'messages_received' => 0,
        'bytes_sent' => 0,
        'bytes_received' => 0,
        'errors' => 0,
        'connected_at' => null,
        'reconnect_count' => 0
    ];
    
    /** @var resource|null */
    private $socket;
    private ?string $url = null;
    private array $headers = [];
    
    public function __construct(array $config = [], ?LoggerInterface $logger = null)
    {
        $this->config = array_merge([
            'url' => 'ws://localhost:8080',
            'timeout' => 30,
            'ping_interval' => 30,
            'max_frame_size' => 65536,
            'protocols' => [],
            'headers' => [],
            'auto_reconnect' => true,
            'max_reconnect_attempts' => 3
        ], $config);
        
        $this->logger = $logger ?? new NullLogger();
        $this->url = $this->config['url'];
        $this->headers = $this->config['headers'];
    }
    
    /**
     * {@inheritDoc}
     */
    public function connect(): bool
    {
        try {
            $this->socket = $this->createWebSocketConnection();
            $this->connected = true;
            $this->stats['connected_at'] = time();
            
            $this->logger->info('WebSocket transport connected', [
                'url' => $this->url
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new McpTransportException("Failed to connect WebSocket transport: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function disconnect(): bool
    {
        if ($this->socket) {
            $this->sendCloseFrame();
            fclose($this->socket);
            $this->socket = null;
        }
        
        $this->connected = false;
        $this->stats['connected_at'] = null;
        
        $this->logger->info('WebSocket transport disconnected');
        
        return true;
    }
    
    /**
     * {@inheritDoc}
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->socket && !feof($this->socket);
    }
    
    /**
     * {@inheritDoc}
     */
    public function send(string $message): bool
    {
        if (!$this->isConnected()) {
            if ($this->config['auto_reconnect']) {
                $this->reconnect();
            } else {
                throw new McpTransportException('WebSocket not connected');
            }
        }
        
        try {
            $frame = $this->createWebSocketFrame($message);
            $bytesWritten = fwrite($this->socket, $frame);
            
            if ($bytesWritten === false || $bytesWritten !== strlen($frame)) {
                throw new McpTransportException('Failed to send WebSocket frame');
            }
            
            $this->stats['messages_sent']++;
            $this->stats['bytes_sent'] += strlen($message);
            
            $this->logger->debug('Sent message via WebSocket', [
                'size' => strlen($message),
                'frame_size' => strlen($frame)
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new McpTransportException("Failed to send WebSocket message: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function receive(?int $timeout = null): ?string
    {
        if (!$this->isConnected()) {
            throw new McpTransportException('WebSocket not connected');
        }
        
        try {
            $frame = $this->readWebSocketFrame($timeout);
            
            if ($frame === null) {
                return null; // Timeout
            }
            
            $this->stats['messages_received']++;
            $this->stats['bytes_received'] += strlen($frame);
            
            $this->logger->debug('Received message via WebSocket', [
                'size' => strlen($frame)
            ]);
            
            return $frame;
        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new McpTransportException("Failed to receive WebSocket message: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function getStats(): array
    {
        $stats = $this->stats;
        
        if ($stats['connected_at']) {
            $stats['uptime'] = time() - $stats['connected_at'];
        }
        
        return $stats;
    }
    
    /**
     * Create WebSocket connection
     *
     * @return resource Socket resource
     * @throws McpTransportException
     */
    private function createWebSocketConnection()
    {
        $urlParts = parse_url($this->url);
        
        if (!$urlParts || !isset($urlParts['host'])) {
            throw new McpTransportException("Invalid WebSocket URL: {$this->url}");
        }
        
        $host = $urlParts['host'];
        $port = $urlParts['port'] ?? ($urlParts['scheme'] === 'wss' ? 443 : 80);
        $path = ($urlParts['path'] ?? '/') . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');
        
        // Create socket connection
        $socket = fsockopen($host, $port, $errno, $errstr, $this->config['timeout']);
        
        if (!$socket) {
            throw new McpTransportException("Failed to connect to {$host}:{$port}: {$errstr} ({$errno})");
        }
        
        // Perform WebSocket handshake
        $this->performHandshake($socket, $host, $path);
        
        return $socket;
    }
    
    /**
     * Perform WebSocket handshake
     *
     * @param resource $socket Socket resource
     * @param string $host Host name
     * @param string $path Path
     * @throws McpTransportException
     */
    private function performHandshake($socket, string $host, string $path): void
    {
        $key = base64_encode(random_bytes(16));
        
        $request = "GET {$path} HTTP/1.1\r\n";
        $request .= "Host: {$host}\r\n";
        $request .= "Upgrade: websocket\r\n";
        $request .= "Connection: Upgrade\r\n";
        $request .= "Sec-WebSocket-Key: {$key}\r\n";
        $request .= "Sec-WebSocket-Version: 13\r\n";
        
        // Add custom headers
        foreach ($this->headers as $name => $value) {
            $request .= "{$name}: {$value}\r\n";
        }
        
        // Add protocols if specified
        if (!empty($this->config['protocols'])) {
            $request .= "Sec-WebSocket-Protocol: " . implode(', ', $this->config['protocols']) . "\r\n";
        }
        
        $request .= "\r\n";
        
        // Send handshake request
        fwrite($socket, $request);
        
        // Read response
        $response = '';
        while (($line = fgets($socket)) !== false) {
            $response .= $line;
            if (trim($line) === '') {
                break; // End of headers
            }
        }
        
        // Validate handshake response
        if (!str_contains($response, 'HTTP/1.1 101 Switching Protocols')) {
            throw new McpTransportException("WebSocket handshake failed: {$response}");
        }
        
        $expectedAccept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        if (!str_contains($response, "Sec-WebSocket-Accept: {$expectedAccept}")) {
            throw new McpTransportException('Invalid WebSocket handshake response');
        }
    }
    
    /**
     * Create WebSocket frame
     *
     * @param string $payload Payload data
     * @param int $opcode Opcode (default: text frame)
     * @return string WebSocket frame
     */
    private function createWebSocketFrame(string $payload, int $opcode = 0x1): string
    {
        $payloadLength = strlen($payload);
        $frame = '';
        
        // First byte: FIN (1) + RSV (000) + Opcode (4 bits)
        $frame .= chr(0x80 | $opcode);
        
        // Second byte: MASK (1) + Payload length (7 bits)
        if ($payloadLength < 126) {
            $frame .= chr(0x80 | $payloadLength);
        } elseif ($payloadLength < 65536) {
            $frame .= chr(0x80 | 126) . pack('n', $payloadLength);
        } else {
            $frame .= chr(0x80 | 127) . pack('J', $payloadLength);
        }
        
        // Masking key (4 bytes)
        $mask = random_bytes(4);
        $frame .= $mask;
        
        // Mask payload
        for ($i = 0; $i < $payloadLength; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }
        
        return $frame;
    }
    
    /**
     * Read WebSocket frame
     *
     * @param int|null $timeout Timeout in seconds
     * @return string|null Frame payload or null on timeout
     * @throws McpTransportException
     */
    private function readWebSocketFrame(?int $timeout = null): ?string
    {
        // Set timeout if specified
        if ($timeout !== null) {
            stream_set_timeout($this->socket, $timeout);
        }
        
        // Read first two bytes
        $header = fread($this->socket, 2);
        if (strlen($header) !== 2) {
            return null;
        }
        
        $firstByte = ord($header[0]);
        $secondByte = ord($header[1]);
        
        $fin = ($firstByte & 0x80) !== 0;
        $opcode = $firstByte & 0x0F;
        $masked = ($secondByte & 0x80) !== 0;
        $payloadLength = $secondByte & 0x7F;
        
        // Handle extended payload length
        if ($payloadLength === 126) {
            $extendedLength = fread($this->socket, 2);
            $payloadLength = unpack('n', $extendedLength)[1];
        } elseif ($payloadLength === 127) {
            $extendedLength = fread($this->socket, 8);
            $payloadLength = unpack('J', $extendedLength)[1];
        }
        
        // Read masking key if present
        $mask = null;
        if ($masked) {
            $mask = fread($this->socket, 4);
        }
        
        // Read payload
        $payload = '';
        if ($payloadLength > 0) {
            $payload = fread($this->socket, $payloadLength);
            
            // Unmask if needed
            if ($masked && $mask) {
                for ($i = 0; $i < $payloadLength; $i++) {
                    $payload[$i] = $payload[$i] ^ $mask[$i % 4];
                }
            }
        }
        
        // Handle different frame types
        switch ($opcode) {
            case 0x1: // Text frame
            case 0x2: // Binary frame
                return $payload;
            case 0x8: // Close frame
                $this->connected = false;
                return null;
            case 0x9: // Ping frame
                $this->sendPongFrame($payload);
                return $this->readWebSocketFrame($timeout);
            case 0xA: // Pong frame
                return $this->readWebSocketFrame($timeout);
            default:
                throw new McpTransportException("Unsupported WebSocket opcode: {$opcode}");
        }
    }
    
    /**
     * Send close frame
     */
    private function sendCloseFrame(): void
    {
        if ($this->socket) {
            $frame = $this->createWebSocketFrame('', 0x8);
            fwrite($this->socket, $frame);
        }
    }
    
    /**
     * Send pong frame
     *
     * @param string $payload Payload to echo back
     */
    private function sendPongFrame(string $payload): void
    {
        if ($this->socket) {
            $frame = $this->createWebSocketFrame($payload, 0xA);
            fwrite($this->socket, $frame);
        }
    }
    
    /**
     * Attempt to reconnect
     *
     * @throws McpTransportException
     */
    private function reconnect(): void
    {
        $maxAttempts = $this->config['max_reconnect_attempts'];
        
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $this->logger->info("Attempting WebSocket reconnection", [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts
                ]);
                
                $this->disconnect();
                $this->connect();
                
                $this->stats['reconnect_count']++;
                
                $this->logger->info('WebSocket reconnection successful');
                return;
            } catch (\Exception $e) {
                $this->logger->warning("WebSocket reconnection attempt {$attempt} failed", [
                    'error' => $e->getMessage()
                ]);
                
                if ($attempt < $maxAttempts) {
                    sleep($attempt); // Exponential backoff
                }
            }
        }
        
        throw new McpTransportException("Failed to reconnect after {$maxAttempts} attempts");
    }
}