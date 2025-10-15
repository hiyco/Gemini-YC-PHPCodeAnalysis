<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: ModelProviderFactory Test
 */

declare(strict_types=1);

namespace YcPca\Tests\Mcp\Model;

use PHPUnit\Framework\TestCase;
use YcPca\Mcp\Model\ModelProviderFactory;
use YcPca\Mcp\Model\Providers\QwenProvider;
use YcPca\Mcp\Model\Providers\DeepSeekProvider;
use YcPca\Mcp\Model\Providers\DoubaoProvider;
use YcPca\Mcp\Model\Providers\ErnieProvider;
use YcPca\Mcp\Model\Providers\OpenAIProvider;
use YcPca\Mcp\Model\Providers\ClaudeProvider;
use YcPca\Mcp\McpModelException;

class ModelProviderFactoryTest extends TestCase
{
    public function testGetSupportedProviders(): void
    {
        $providers = ModelProviderFactory::getSupportedProviders();
        
        $this->assertIsArray($providers);
        $this->assertContains('qwen', $providers);
        $this->assertContains('deepseek', $providers);
        $this->assertContains('doubao', $providers);
        $this->assertContains('ernie', $providers);
        $this->assertContains('openai', $providers);
        $this->assertContains('claude', $providers);
    }
    
    public function testIsSupported(): void
    {
        $this->assertTrue(ModelProviderFactory::isSupported('qwen'));
        $this->assertTrue(ModelProviderFactory::isSupported('deepseek'));
        $this->assertFalse(ModelProviderFactory::isSupported('unknown'));
    }
    
    public function testCreateQwenProvider(): void
    {
        $provider = ModelProviderFactory::create('qwen', [
            'api_key' => 'test-key'
        ]);
        
        $this->assertInstanceOf(QwenProvider::class, $provider);
        $this->assertEquals('qwen', $provider->getName());
    }
    
    public function testCreateDeepSeekProvider(): void
    {
        $provider = ModelProviderFactory::create('deepseek', [
            'api_key' => 'test-key'
        ]);
        
        $this->assertInstanceOf(DeepSeekProvider::class, $provider);
        $this->assertEquals('deepseek', $provider->getName());
    }
    
    public function testCreateDoubaoProvider(): void
    {
        $provider = ModelProviderFactory::create('doubao', [
            'api_key' => 'test-key'
        ]);
        
        $this->assertInstanceOf(DoubaoProvider::class, $provider);
        $this->assertEquals('doubao', $provider->getName());
    }
    
    public function testCreateErnieProvider(): void
    {
        $provider = ModelProviderFactory::create('ernie', [
            'api_key' => 'test-key',
            'secret_key' => 'test-secret'
        ]);
        
        $this->assertInstanceOf(ErnieProvider::class, $provider);
        $this->assertEquals('ernie', $provider->getName());
    }
    
    public function testCreateOpenAIProvider(): void
    {
        $provider = ModelProviderFactory::create('openai', [
            'api_key' => 'test-key'
        ]);
        
        $this->assertInstanceOf(OpenAIProvider::class, $provider);
        $this->assertEquals('openai', $provider->getName());
    }
    
    public function testCreateClaudeProvider(): void
    {
        $provider = ModelProviderFactory::create('claude', [
            'api_key' => 'test-key'
        ]);
        
        $this->assertInstanceOf(ClaudeProvider::class, $provider);
        $this->assertEquals('claude', $provider->getName());
    }
    
    public function testCreateUnsupportedProvider(): void
    {
        $this->expectException(McpModelException::class);
        $this->expectExceptionMessage('Unsupported model provider: unknown');
        
        ModelProviderFactory::create('unknown');
    }
    
    public function testGetProviderInfo(): void
    {
        $info = ModelProviderFactory::getProviderInfo('qwen');
        
        $this->assertIsArray($info);
        $this->assertEquals('Alibaba QWEN', $info['name']);
        $this->assertEquals('api_key', $info['auth_type']);
        $this->assertArrayHasKey('models', $info);
    }
    
    public function testGetProviderInfoUnsupported(): void
    {
        $this->expectException(McpModelException::class);
        $this->expectExceptionMessage('Unsupported model provider: unknown');
        
        ModelProviderFactory::getProviderInfo('unknown');
    }
    
    public function testGetAllProvidersInfo(): void
    {
        $allInfo = ModelProviderFactory::getAllProvidersInfo();
        
        $this->assertIsArray($allInfo);
        $this->assertArrayHasKey('qwen', $allInfo);
        $this->assertArrayHasKey('deepseek', $allInfo);
        $this->assertArrayHasKey('doubao', $allInfo);
        $this->assertArrayHasKey('ernie', $allInfo);
        $this->assertArrayHasKey('openai', $allInfo);
        $this->assertArrayHasKey('claude', $allInfo);
        
        // Ensure class key is removed
        foreach ($allInfo as $info) {
            $this->assertArrayNotHasKey('class', $info);
        }
    }
    
    public function testGetProviderModels(): void
    {
        $models = ModelProviderFactory::getProviderModels('qwen');
        
        $this->assertIsArray($models);
        $this->assertArrayHasKey('qwen-turbo', $models);
        $this->assertArrayHasKey('qwen-plus', $models);
        $this->assertArrayHasKey('context', $models['qwen-turbo']);
        $this->assertArrayHasKey('max_tokens', $models['qwen-turbo']);
    }
    
    public function testFindProviderByModel(): void
    {
        $provider = ModelProviderFactory::findProviderByModel('qwen-turbo');
        $this->assertEquals('qwen', $provider);
        
        $provider = ModelProviderFactory::findProviderByModel('deepseek-chat');
        $this->assertEquals('deepseek', $provider);
        
        $provider = ModelProviderFactory::findProviderByModel('unknown-model');
        $this->assertNull($provider);
    }
    
    public function testGetModelInfo(): void
    {
        $info = ModelProviderFactory::getModelInfo('qwen-turbo');
        
        $this->assertIsArray($info);
        $this->assertEquals('qwen', $info['provider']);
        $this->assertEquals(8192, $info['context']);
        $this->assertEquals(1500, $info['max_tokens']);
        
        $info = ModelProviderFactory::getModelInfo('unknown-model');
        $this->assertNull($info);
    }
    
    public function testValidateConfig(): void
    {
        // Valid config for API key provider
        $errors = ModelProviderFactory::validateConfig('qwen', [
            'api_key' => 'test-key',
            'model' => 'qwen-turbo'
        ]);
        $this->assertEmpty($errors);
        
        // Missing API key
        $errors = ModelProviderFactory::validateConfig('qwen', []);
        $this->assertContains('API key is required', $errors);
        
        // Invalid model
        $errors = ModelProviderFactory::validateConfig('qwen', [
            'api_key' => 'test-key',
            'model' => 'invalid-model'
        ]);
        $this->assertCount(1, $errors);
        $this->assertStringContains('Unsupported model', $errors[0]);
        
        // Valid config for ERNIE (requires both API key and secret)
        $errors = ModelProviderFactory::validateConfig('ernie', [
            'api_key' => 'test-key',
            'secret_key' => 'test-secret'
        ]);
        $this->assertEmpty($errors);
        
        // Missing secret key for ERNIE
        $errors = ModelProviderFactory::validateConfig('ernie', [
            'api_key' => 'test-key'
        ]);
        $this->assertContains('Secret key is required', $errors);
        
        // Unsupported provider
        $errors = ModelProviderFactory::validateConfig('unknown', []);
        $this->assertContains('Unsupported provider: unknown', $errors);
    }
}