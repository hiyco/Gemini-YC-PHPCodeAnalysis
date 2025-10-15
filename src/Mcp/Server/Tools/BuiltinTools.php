<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: Built-in MCP Tools
 */

declare(strict_types=1);

namespace YcPca\Mcp\Server\Tools;

use YcPca\Mcp\Server\McpServer;

/**
 * Built-in MCP Tools
 * 
 * Provides common tools for MCP servers.
 * 
 * @package YcPca\Mcp\Server\Tools
 * @author YC
 * @version 1.0.0
 */
class BuiltinTools
{
    /**
     * Register all built-in tools
     *
     * @param McpServer $server MCP server instance
     */
    public static function registerAll(McpServer $server): void
    {
        self::registerFileTools($server);
        self::registerSystemTools($server);
        self::registerUtilityTools($server);
    }
    
    /**
     * Register file operation tools
     *
     * @param McpServer $server MCP server instance
     */
    public static function registerFileTools(McpServer $server): void
    {
        // Read file tool
        $server->registerTool(
            'read_file',
            function (array $args): string {
                $filePath = $args['path'] ?? '';
                
                if (empty($filePath)) {
                    throw new \InvalidArgumentException('File path is required');
                }
                
                if (!file_exists($filePath)) {
                    throw new \RuntimeException("File not found: {$filePath}");
                }
                
                if (!is_readable($filePath)) {
                    throw new \RuntimeException("File not readable: {$filePath}");
                }
                
                $content = file_get_contents($filePath);
                if ($content === false) {
                    throw new \RuntimeException("Failed to read file: {$filePath}");
                }
                
                return $content;
            },
            [
                'type' => 'object',
                'properties' => [
                    'path' => [
                        'type' => 'string',
                        'description' => 'Path to the file to read'
                    ]
                ],
                'required' => ['path']
            ],
            'Read the contents of a file'
        );
        
        // Write file tool
        $server->registerTool(
            'write_file',
            function (array $args): string {
                $filePath = $args['path'] ?? '';
                $content = $args['content'] ?? '';
                $append = $args['append'] ?? false;
                
                if (empty($filePath)) {
                    throw new \InvalidArgumentException('File path is required');
                }
                
                $directory = dirname($filePath);
                if (!is_dir($directory)) {
                    if (!mkdir($directory, 0755, true)) {
                        throw new \RuntimeException("Failed to create directory: {$directory}");
                    }
                }
                
                $flags = $append ? FILE_APPEND | LOCK_EX : LOCK_EX;
                $result = file_put_contents($filePath, $content, $flags);
                
                if ($result === false) {
                    throw new \RuntimeException("Failed to write file: {$filePath}");
                }
                
                return "Successfully " . ($append ? 'appended to' : 'wrote') . " file: {$filePath} ({$result} bytes)";
            },
            [
                'type' => 'object',
                'properties' => [
                    'path' => [
                        'type' => 'string',
                        'description' => 'Path to the file to write'
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Content to write to the file'
                    ],
                    'append' => [
                        'type' => 'boolean',
                        'description' => 'Whether to append to the file (default: false)',
                        'default' => false
                    ]
                ],
                'required' => ['path', 'content']
            ],
            'Write content to a file'
        );
        
        // List directory tool
        $server->registerTool(
            'list_directory',
            function (array $args): array {
                $dirPath = $args['path'] ?? '.';
                $includeHidden = $args['include_hidden'] ?? false;
                
                if (!is_dir($dirPath)) {
                    throw new \RuntimeException("Directory not found: {$dirPath}");
                }
                
                $items = [];
                $iterator = new \DirectoryIterator($dirPath);
                
                foreach ($iterator as $item) {
                    if ($item->isDot()) {
                        continue;
                    }
                    
                    if (!$includeHidden && str_starts_with($item->getFilename(), '.')) {
                        continue;
                    }
                    
                    $items[] = [
                        'name' => $item->getFilename(),
                        'type' => $item->isDir() ? 'directory' : 'file',
                        'size' => $item->isFile() ? $item->getSize() : null,
                        'modified' => $item->getMTime(),
                        'permissions' => substr(sprintf('%o', $item->getPerms()), -4)
                    ];
                }
                
                // Sort by type (directories first) then by name
                usort($items, function ($a, $b) {
                    if ($a['type'] !== $b['type']) {
                        return $a['type'] === 'directory' ? -1 : 1;
                    }
                    return strcasecmp($a['name'], $b['name']);
                });
                
                return $items;
            },
            [
                'type' => 'object',
                'properties' => [
                    'path' => [
                        'type' => 'string',
                        'description' => 'Path to the directory to list (default: current directory)',
                        'default' => '.'
                    ],
                    'include_hidden' => [
                        'type' => 'boolean',
                        'description' => 'Whether to include hidden files (default: false)',
                        'default' => false
                    ]
                ]
            ],
            'List the contents of a directory'
        );
    }
    
    /**
     * Register system operation tools
     *
     * @param McpServer $server MCP server instance
     */
    public static function registerSystemTools(McpServer $server): void
    {
        // Execute command tool
        $server->registerTool(
            'execute_command',
            function (array $args): array {
                $command = $args['command'] ?? '';
                $workingDir = $args['working_dir'] ?? null;
                $timeout = $args['timeout'] ?? 30;
                
                if (empty($command)) {
                    throw new \InvalidArgumentException('Command is required');
                }
                
                $descriptorSpec = [
                    0 => ['pipe', 'r'], // stdin
                    1 => ['pipe', 'w'], // stdout
                    2 => ['pipe', 'w']  // stderr
                ];
                
                $process = proc_open($command, $descriptorSpec, $pipes, $workingDir);
                
                if (!is_resource($process)) {
                    throw new \RuntimeException("Failed to execute command: {$command}");
                }
                
                // Close stdin
                fclose($pipes[0]);
                
                // Set timeout for reading
                $startTime = time();
                $stdout = '';
                $stderr = '';
                
                while (time() - $startTime < $timeout) {
                    $status = proc_get_status($process);
                    
                    if (!$status['running']) {
                        break;
                    }
                    
                    usleep(100000); // 0.1 seconds
                }
                
                // Read output
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                
                fclose($pipes[1]);
                fclose($pipes[2]);
                
                $exitCode = proc_close($process);
                
                return [
                    'command' => $command,
                    'exit_code' => $exitCode,
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'success' => $exitCode === 0
                ];
            },
            [
                'type' => 'object',
                'properties' => [
                    'command' => [
                        'type' => 'string',
                        'description' => 'Command to execute'
                    ],
                    'working_dir' => [
                        'type' => 'string',
                        'description' => 'Working directory for the command'
                    ],
                    'timeout' => [
                        'type' => 'integer',
                        'description' => 'Command timeout in seconds (default: 30)',
                        'default' => 30,
                        'minimum' => 1,
                        'maximum' => 300
                    ]
                ],
                'required' => ['command']
            ],
            'Execute a system command'
        );
        
        // Get system info tool
        $server->registerTool(
            'system_info',
            function (array $args): array {
                return [
                    'php_version' => PHP_VERSION,
                    'php_sapi' => PHP_SAPI,
                    'operating_system' => PHP_OS,
                    'architecture' => php_uname('m'),
                    'hostname' => gethostname(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                    'current_user' => get_current_user(),
                    'current_directory' => getcwd(),
                    'temp_directory' => sys_get_temp_dir(),
                    'timezone' => date_default_timezone_get(),
                    'timestamp' => time(),
                    'date' => date('Y-m-d H:i:s')
                ];
            },
            [
                'type' => 'object',
                'properties' => []
            ],
            'Get system information'
        );
    }
    
    /**
     * Register utility tools
     *
     * @param McpServer $server MCP server instance
     */
    public static function registerUtilityTools(McpServer $server): void
    {
        // JSON format tool
        $server->registerTool(
            'format_json',
            function (array $args): string {
                $json = $args['json'] ?? '';
                $pretty = $args['pretty'] ?? true;
                
                if (empty($json)) {
                    throw new \InvalidArgumentException('JSON string is required');
                }
                
                $data = json_decode($json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
                }
                
                $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
                if ($pretty) {
                    $flags |= JSON_PRETTY_PRINT;
                }
                
                return json_encode($data, $flags);
            },
            [
                'type' => 'object',
                'properties' => [
                    'json' => [
                        'type' => 'string',
                        'description' => 'JSON string to format'
                    ],
                    'pretty' => [
                        'type' => 'boolean',
                        'description' => 'Whether to pretty print the JSON (default: true)',
                        'default' => true
                    ]
                ],
                'required' => ['json']
            ],
            'Format and validate JSON'
        );
        
        // Base64 encode/decode tool
        $server->registerTool(
            'base64',
            function (array $args): string {
                $data = $args['data'] ?? '';
                $operation = $args['operation'] ?? 'encode';
                
                if (empty($data)) {
                    throw new \InvalidArgumentException('Data is required');
                }
                
                return match ($operation) {
                    'encode' => base64_encode($data),
                    'decode' => base64_decode($data, true) ?: throw new \InvalidArgumentException('Invalid base64 data'),
                    default => throw new \InvalidArgumentException("Invalid operation: {$operation}")
                };
            },
            [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'string',
                        'description' => 'Data to encode/decode'
                    ],
                    'operation' => [
                        'type' => 'string',
                        'enum' => ['encode', 'decode'],
                        'description' => 'Operation to perform (default: encode)',
                        'default' => 'encode'
                    ]
                ],
                'required' => ['data']
            ],
            'Base64 encode or decode data'
        );
        
        // Hash tool
        $server->registerTool(
            'hash',
            function (array $args): string {
                $data = $args['data'] ?? '';
                $algorithm = $args['algorithm'] ?? 'sha256';
                
                if (empty($data)) {
                    throw new \InvalidArgumentException('Data is required');
                }
                
                if (!in_array($algorithm, hash_algos(), true)) {
                    throw new \InvalidArgumentException("Unsupported hash algorithm: {$algorithm}");
                }
                
                return hash($algorithm, $data);
            },
            [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'string',
                        'description' => 'Data to hash'
                    ],
                    'algorithm' => [
                        'type' => 'string',
                        'description' => 'Hash algorithm (default: sha256)',
                        'default' => 'sha256',
                        'enum' => ['md5', 'sha1', 'sha256', 'sha512']
                    ]
                ],
                'required' => ['data']
            ],
            'Generate hash of data using various algorithms'
        );
    }
}