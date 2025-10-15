<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: McpProtocol Test
 */

declare(strict_types=1);

namespace YcPca\Tests\Mcp\Protocol;

use PHPUnit\Framework\TestCase;
use YcPca\Mcp\Protocol\McpProtocol;
use YcPca\Mcp\McpException;

class McpProtocolTest extends TestCase
{
    private McpProtocol $protocol;
    
    protected function setUp(): void
    {
        $this->protocol = new McpProtocol();
    }
    
    public function testCreateRequest(): void
    {
        $request = $this->protocol->createRequest('test/method', ['param' => 'value'], 123);
        
        $this->assertIsString($request);
        $decoded = json_decode($request, true);
        
        $this->assertEquals('2.0', $decoded['jsonrpc']);
        $this->assertEquals('test/method', $decoded['method']);
        $this->assertEquals(['param' => 'value'], $decoded['params']);
        $this->assertEquals(123, $decoded['id']);
    }
    
    public function testCreateRequestWithoutParams(): void
    {
        $request = $this->protocol->createRequest('test/method', [], 123);
        
        $decoded = json_decode($request, true);
        $this->assertArrayNotHasKey('params', $decoded);
    }
    
    public function testCreateResponse(): void
    {
        $response = $this->protocol->createResponse(123, ['result' => 'success']);
        
        $this->assertIsString($response);
        $decoded = json_decode($response, true);
        
        $this->assertEquals('2.0', $decoded['jsonrpc']);
        $this->assertEquals(123, $decoded['id']);
        $this->assertEquals(['result' => 'success'], $decoded['result']);
    }
    
    public function testCreateErrorResponse(): void
    {
        $response = $this->protocol->createErrorResponse(123, -32600, 'Invalid Request', ['detail' => 'test']);
        
        $decoded = json_decode($response, true);
        
        $this->assertEquals('2.0', $decoded['jsonrpc']);
        $this->assertEquals(123, $decoded['id']);
        $this->assertEquals(-32600, $decoded['error']['code']);
        $this->assertEquals('Invalid Request', $decoded['error']['message']);
        $this->assertEquals(['detail' => 'test'], $decoded['error']['data']);
    }
    
    public function testCreateErrorResponseWithoutData(): void
    {
        $response = $this->protocol->createErrorResponse(123, -32600, 'Invalid Request');
        
        $decoded = json_decode($response, true);
        $this->assertArrayNotHasKey('data', $decoded['error']);
    }
    
    public function testCreateNotification(): void
    {
        $notification = $this->protocol->createNotification('test/notification', ['param' => 'value']);
        
        $decoded = json_decode($notification, true);
        
        $this->assertEquals('2.0', $decoded['jsonrpc']);
        $this->assertEquals('test/notification', $decoded['method']);
        $this->assertEquals(['param' => 'value'], $decoded['params']);
        $this->assertArrayNotHasKey('id', $decoded);
    }
    
    public function testParseValidRequest(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'test/method',
            'params' => ['param' => 'value'],
            'id' => 123
        ]);
        
        $parsed = $this->protocol->parseMessage($json);
        
        $this->assertEquals('2.0', $parsed['jsonrpc']);
        $this->assertEquals('test/method', $parsed['method']);
        $this->assertEquals(['param' => 'value'], $parsed['params']);
        $this->assertEquals(123, $parsed['id']);
    }
    
    public function testParseValidResponse(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'result' => ['success' => true],
            'id' => 123
        ]);
        
        $parsed = $this->protocol->parseMessage($json);
        
        $this->assertEquals('2.0', $parsed['jsonrpc']);
        $this->assertEquals(['success' => true], $parsed['result']);
        $this->assertEquals(123, $parsed['id']);
    }
    
    public function testParseValidNotification(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'test/notification',
            'params' => ['param' => 'value']
        ]);
        
        $parsed = $this->protocol->parseMessage($json);
        
        $this->assertEquals('2.0', $parsed['jsonrpc']);
        $this->assertEquals('test/notification', $parsed['method']);
        $this->assertEquals(['param' => 'value'], $parsed['params']);
        $this->assertArrayNotHasKey('id', $parsed);
    }
    
    public function testParseInvalidJson(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Invalid JSON');
        
        $this->protocol->parseMessage('invalid json');
    }
    
    public function testParseMissingJsonrpc(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Missing jsonrpc field');
        
        $json = json_encode(['method' => 'test']);
        $this->protocol->parseMessage($json);
    }
    
    public function testParseInvalidJsonrpcVersion(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Invalid jsonrpc version');
        
        $json = json_encode([
            'jsonrpc' => '1.0',
            'method' => 'test'
        ]);
        $this->protocol->parseMessage($json);
    }
    
    public function testValidateValidRequest(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test/method',
            'id' => 123
        ];
        
        $this->assertTrue($this->protocol->validateMessage($message));
    }
    
    public function testValidateValidNotification(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test/notification'
        ];
        
        $this->assertTrue($this->protocol->validateMessage($message));
    }
    
    public function testValidateValidResponse(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'result' => ['success' => true],
            'id' => 123
        ];
        
        $this->assertTrue($this->protocol->validateMessage($message));
    }
    
    public function testValidateValidErrorResponse(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request'
            ],
            'id' => 123
        ];
        
        $this->assertTrue($this->protocol->validateMessage($message));
    }
    
    public function testValidateInvalidMessage(): void
    {
        // Missing jsonrpc
        $message = ['method' => 'test'];
        $this->assertFalse($this->protocol->validateMessage($message));
        
        // Invalid jsonrpc version
        $message = ['jsonrpc' => '1.0', 'method' => 'test'];
        $this->assertFalse($this->protocol->validateMessage($message));
        
        // Request missing method
        $message = ['jsonrpc' => '2.0', 'id' => 123];
        $this->assertFalse($this->protocol->validateMessage($message));
        
        // Response missing result and error
        $message = ['jsonrpc' => '2.0', 'id' => 123];
        $this->assertFalse($this->protocol->validateMessage($message));
        
        // Error response with invalid error format
        $message = [
            'jsonrpc' => '2.0',
            'error' => 'string error',
            'id' => 123
        ];
        $this->assertFalse($this->protocol->validateMessage($message));
    }
    
    public function testIsRequest(): void
    {
        $request = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => 123];
        $this->assertTrue($this->protocol->isRequest($request));
        
        $notification = ['jsonrpc' => '2.0', 'method' => 'test'];
        $this->assertFalse($this->protocol->isRequest($notification));
        
        $response = ['jsonrpc' => '2.0', 'result' => [], 'id' => 123];
        $this->assertFalse($this->protocol->isRequest($response));
    }
    
    public function testIsNotification(): void
    {
        $notification = ['jsonrpc' => '2.0', 'method' => 'test'];
        $this->assertTrue($this->protocol->isNotification($notification));
        
        $request = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => 123];
        $this->assertFalse($this->protocol->isNotification($request));
        
        $response = ['jsonrpc' => '2.0', 'result' => [], 'id' => 123];
        $this->assertFalse($this->protocol->isNotification($response));
    }
    
    public function testIsResponse(): void
    {
        $response = ['jsonrpc' => '2.0', 'result' => [], 'id' => 123];
        $this->assertTrue($this->protocol->isResponse($response));
        
        $errorResponse = ['jsonrpc' => '2.0', 'error' => ['code' => -1, 'message' => 'error'], 'id' => 123];
        $this->assertTrue($this->protocol->isResponse($errorResponse));
        
        $request = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => 123];
        $this->assertFalse($this->protocol->isResponse($request));
        
        $notification = ['jsonrpc' => '2.0', 'method' => 'test'];
        $this->assertFalse($this->protocol->isResponse($notification));
    }
    
    public function testIsErrorResponse(): void
    {
        $errorResponse = ['jsonrpc' => '2.0', 'error' => ['code' => -1, 'message' => 'error'], 'id' => 123];
        $this->assertTrue($this->protocol->isErrorResponse($errorResponse));
        
        $response = ['jsonrpc' => '2.0', 'result' => [], 'id' => 123];
        $this->assertFalse($this->protocol->isErrorResponse($response));
        
        $request = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => 123];
        $this->assertFalse($this->protocol->isErrorResponse($request));
    }
}