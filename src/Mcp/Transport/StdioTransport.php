<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: STDIO Transport Implementation for MCP
 */

declare(strict_types=1);

namespace YcPca\Mcp\Transport;

use YcPca\Mcp\McpTransportException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * STDIO Transport Implementation
 * 
 * Implements MCP communication over standard input/output streams.
 * This is the most common transport for MCP servers.
 * 
 * @package YcPca\Mcp\Transport
 * @author YC
 * @version 1.0.0
 */
class StdioTransport implements TransportInterface
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
        'connected_at' => null
    ];
    
    /** @var resource|null */
    private $stdin;
    
    /** @var resource|null */
    private $stdout;
    
    /** @var resource|null */
    private $stderr;
    
    public function __construct(array $config = [], ?LoggerInterface $logger = null)
    {
        $this->config = array_merge([
            'buffer_size' => 8192,
            'line_ending' => "\n",
            'encoding' => 'UTF-8',
            'error_stream' => 'stderr'
        ], $config);
        
        $this->logger = $logger ?? new NullLogger();
    }
    
    /**
     * {@inheritDoc}
     */
    public function connect(): bool
    {
        try {
            $this->stdin = STDIN;
            $this->stdout = STDOUT;
            $this->stderr = STDERR;
            
            // Set streams to non-blocking mode for better control
            if (!stream_set_blocking($this->stdin, false)) {
                throw new McpTransportException('Failed to set stdin to non-blocking mode');
            }
            
            $this->connected = true;
            $this->stats['connected_at'] = time();
            
            $this->logger->info('STDIO transport connected');
            
            return true;
        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new McpTransportException("Failed to connect STDIO transport: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function disconnect(): bool
    {
        $this->connected = false;
        $this->stats['connected_at'] = null;
        
        $this->logger->info('STDIO transport disconnected');
        
        return true;
    }
    
    /**
     * {@inheritDoc}
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }
    
    /**
     * {@inheritDoc}
     */
    public function send(string $message): bool
    {
        if (!$this->connected) {
            throw new McpTransportException('Transport not connected');
        }
        
        try {
            $messageWithNewline = $message . $this->config['line_ending'];
            $bytesToWrite = strlen($messageWithNewline);
            $bytesWritten = fwrite($this->stdout, $messageWithNewline);
            
            if ($bytesWritten === false) {
                throw new McpTransportException('Failed to write to stdout');
            }
            
            if ($bytesWritten !== $bytesToWrite) {
                throw new McpTransportException("Partial write: {$bytesWritten}/{$bytesToWrite} bytes");
            }
            
            fflush($this->stdout);
            
            $this->stats['messages_sent']++;
            $this->stats['bytes_sent'] += $bytesWritten;
            
            $this->logger->debug('Sent message via STDIO', [
                'size' => $bytesWritten,
                'message_preview' => substr($message, 0, 100)
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new McpTransportException("Failed to send message: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function receive(?int $timeout = null): ?string
    {
        if (!$this->connected) {
            throw new McpTransportException('Transport not connected');
        }
        
        try {
            $message = $this->readLine($timeout);
            
            if ($message === null) {
                return null; // Timeout
            }
            
            if ($message === false) {
                throw new McpTransportException('Failed to read from stdin');
            }
            
            $message = rtrim($message, "\r\n");
            
            if (empty($message)) {
                return $this->receive($timeout); // Skip empty lines
            }
            
            $this->stats['messages_received']++;
            $this->stats['bytes_received'] += strlen($message);
            
            $this->logger->debug('Received message via STDIO', [
                'size' => strlen($message),
                'message_preview' => substr($message, 0, 100)
            ]);
            
            return $message;
        } catch (\Exception $e) {
            $this->stats['errors']++;
            throw new McpTransportException("Failed to receive message: {$e->getMessage()}", 0, $e);
        }
    }
    
    /**
     * Read a line from stdin with optional timeout
     *
     * @param int|null $timeout Timeout in seconds
     * @return string|null|false Line read, null on timeout, false on error
     */
    private function readLine(?int $timeout = null): string|null|false
    {
        if ($timeout === null) {
            // Blocking read
            return fgets($this->stdin);
        }
        
        // Non-blocking read with timeout
        $endTime = time() + $timeout;
        $buffer = '';
        
        while (time() < $endTime) {
            $read = [$this->stdin];
            $write = [];
            $except = [];
            
            $ready = stream_select($read, $write, $except, 1, 0);
            
            if ($ready === false) {
                throw new McpTransportException('stream_select failed');
            }
            
            if ($ready > 0) {
                $chunk = fread($this->stdin, $this->config['buffer_size']);
                
                if ($chunk === false) {
                    return false;
                }
                
                $buffer .= $chunk;
                
                // Check if we have a complete line
                if (str_contains($buffer, "\n")) {
                    $lines = explode("\n", $buffer, 2);
                    return $lines[0] . "\n";
                }
            }
            
            usleep(10000); // 10ms sleep to prevent busy waiting
        }
        
        return null; // Timeout
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
     * Send error message to stderr
     *
     * @param string $message Error message
     */
    public function sendError(string $message): void
    {
        if ($this->stderr) {
            fwrite($this->stderr, "[ERROR] {$message}" . $this->config['line_ending']);
            fflush($this->stderr);
        }
        
        $this->logger->error('Sent error via STDIO', ['message' => $message]);
    }
    
    /**
     * Send log message to stderr (for debugging)
     *
     * @param string $level Log level
     * @param string $message Log message
     */
    public function sendLog(string $level, string $message): void
    {
        if ($this->stderr) {
            $timestamp = date('Y-m-d H:i:s');
            $logLine = "[{$timestamp}] [{$level}] {$message}" . $this->config['line_ending'];
            fwrite($this->stderr, $logLine);
            fflush($this->stderr);
        }
        
        $this->logger->debug('Sent log via STDIO', [
            'level' => $level,
            'message' => $message
        ]);
    }
}