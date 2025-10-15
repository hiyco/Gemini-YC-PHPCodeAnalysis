<?php

namespace YC\PromptSystem;

/**
 * 上下文压缩器 - 优化传输效率
 */
class ContextCompressor
{
    private array $compressionRules;
    private array $preservePatterns;
    private int $maxContextSize;
    
    public function __construct(int $maxContextSize = 4096)
    {
        $this->maxContextSize = $maxContextSize;
        $this->initializeRules();
    }
    
    /**
     * 压缩上下文数据
     */
    public function compress(array $context): array
    {
        $compressed = [
            'version' => '1.0',
            'timestamp' => time(),
            'data' => []
        ];
        
        // 1. 识别关键信息
        $critical = $this->extractCriticalInfo($context);
        $compressed['critical'] = $critical;
        
        // 2. 压缩代码片段
        if (isset($context['code'])) {
            $compressed['data']['code'] = $this->compressCode($context['code']);
        }
        
        // 3. 压缩问题描述
        if (isset($context['issues'])) {
            $compressed['data']['issues'] = $this->compressIssues($context['issues']);
        }
        
        // 4. 压缩元数据
        if (isset($context['metadata'])) {
            $compressed['data']['meta'] = $this->compressMetadata($context['metadata']);
        }
        
        // 5. 应用智能压缩策略
        $compressed = $this->applySmartCompression($compressed);
        
        // 6. 确保不超过大小限制
        return $this->ensureSizeLimit($compressed);
    }
    
    /**
     * 解压上下文数据
     */
    public function decompress(array $compressed): array
    {
        $context = [];
        
        // 恢复关键信息
        if (isset($compressed['critical'])) {
            $context = array_merge($context, $this->expandCriticalInfo($compressed['critical']));
        }
        
        // 恢复数据
        if (isset($compressed['data'])) {
            if (isset($compressed['data']['code'])) {
                $context['code'] = $this->decompressCode($compressed['data']['code']);
            }
            
            if (isset($compressed['data']['issues'])) {
                $context['issues'] = $this->decompressIssues($compressed['data']['issues']);
            }
            
            if (isset($compressed['data']['meta'])) {
                $context['metadata'] = $this->decompressMetadata($compressed['data']['meta']);
            }
        }
        
        return $context;
    }
    
    /**
     * 计算压缩率
     */
    public function getCompressionRatio(array $original, array $compressed): float
    {
        $originalSize = strlen(json_encode($original));
        $compressedSize = strlen(json_encode($compressed));
        
        return 1 - ($compressedSize / $originalSize);
    }
    
    /**
     * 智能分块压缩
     */
    public function compressInChunks(array $largeContext, int $chunkSize = 1024): array
    {
        $chunks = [];
        $currentChunk = [];
        $currentSize = 0;
        
        foreach ($largeContext as $key => $value) {
            $itemSize = strlen(json_encode($value));
            
            if ($currentSize + $itemSize > $chunkSize && !empty($currentChunk)) {
                $chunks[] = $this->compress($currentChunk);
                $currentChunk = [];
                $currentSize = 0;
            }
            
            $currentChunk[$key] = $value;
            $currentSize += $itemSize;
        }
        
        if (!empty($currentChunk)) {
            $chunks[] = $this->compress($currentChunk);
        }
        
        return [
            'type' => 'chunked',
            'chunks' => $chunks,
            'total_chunks' => count($chunks)
        ];
    }
    
    /**
     * 基于相似性的去重压缩
     */
    private function applySmartCompression(array $data): array
    {
        // 查找重复模式
        $patterns = $this->findPatterns($data);
        
        // 创建引用字典
        $dictionary = [];
        $compressed = $data;
        
        foreach ($patterns as $pattern => $occurrences) {
            if ($occurrences > 2) { // 只压缩出现3次以上的模式
                $refKey = $this->generateRefKey($pattern);
                $dictionary[$refKey] = $pattern;
                $compressed = $this->replaceWithReferences($compressed, $pattern, $refKey);
            }
        }
        
        if (!empty($dictionary)) {
            $compressed['_dict'] = $dictionary;
        }
        
        return $compressed;
    }
    
    /**
     * 压缩代码片段
     */
    private function compressCode(string $code): array
    {
        $lines = explode("\n", $code);
        $compressed = [
            'type' => 'code',
            'lines' => []
        ];
        
        $lastLineWasEmpty = false;
        foreach ($lines as $lineNum => $line) {
            // 移除多余空白
            $trimmed = trim($line);
            
            // 跳过连续空行
            if (empty($trimmed)) {
                if (!$lastLineWasEmpty) {
                    $compressed['lines'][] = ['n' => $lineNum, 'c' => ''];
                    $lastLineWasEmpty = true;
                }
                continue;
            }
            
            $lastLineWasEmpty = false;
            
            // 压缩注释
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
                if ($this->isImportantComment($trimmed)) {
                    $compressed['lines'][] = ['n' => $lineNum, 'c' => $this->compressComment($trimmed)];
                }
                continue;
            }
            
            // 保留重要代码行
            $compressed['lines'][] = [
                'n' => $lineNum,
                'c' => $this->compressCodeLine($line),
                'i' => $this->getIndentLevel($line)
            ];
        }
        
        return $compressed;
    }
    
    /**
     * 压缩问题列表
     */
    private function compressIssues(array $issues): array
    {
        $compressed = [];
        
        // 按类型和严重性分组
        $grouped = [];
        foreach ($issues as $issue) {
            $key = $issue['type'] . '_' . $issue['severity'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $issue;
        }
        
        // 压缩每组
        foreach ($grouped as $groupKey => $groupIssues) {
            [$type, $severity] = explode('_', $groupKey);
            
            $compressed[] = [
                't' => $this->abbreviate($type),
                's' => $this->abbreviateSeverity($severity),
                'c' => count($groupIssues),
                'l' => array_map(fn($i) => $i['line'] ?? 0, $groupIssues),
                'm' => $this->compressMessages($groupIssues)
            ];
        }
        
        return $compressed;
    }
    
    /**
     * 压缩元数据
     */
    private function compressMetadata(array $metadata): array
    {
        $essential = [
            'file' => $metadata['file'] ?? '',
            'size' => $metadata['size'] ?? 0,
            'modified' => $metadata['modified'] ?? 0
        ];
        
        // 只保留非默认值
        return array_filter($essential, fn($v) => !empty($v));
    }
    
    /**
     * 确保不超过大小限制
     */
    private function ensureSizeLimit(array $compressed): array
    {
        $size = strlen(json_encode($compressed));
        
        if ($size <= $this->maxContextSize) {
            return $compressed;
        }
        
        // 逐步减少内容直到满足限制
        $priorities = ['meta', 'issues', 'code', 'critical'];
        
        foreach ($priorities as $priority) {
            if (isset($compressed['data'][$priority])) {
                $compressed['data'][$priority] = $this->reduceContent(
                    $compressed['data'][$priority],
                    0.7 // 保留70%内容
                );
                
                if (strlen(json_encode($compressed)) <= $this->maxContextSize) {
                    break;
                }
            }
        }
        
        return $compressed;
    }
    
    /**
     * 提取关键信息
     */
    private function extractCriticalInfo(array $context): array
    {
        $critical = [];
        
        // 安全问题总是关键的
        if (isset($context['security_issues'])) {
            $critical['sec'] = count($context['security_issues']);
        }
        
        // 性能瓶颈
        if (isset($context['bottlenecks'])) {
            $critical['perf'] = array_slice($context['bottlenecks'], 0, 3);
        }
        
        // 错误
        if (isset($context['errors'])) {
            $critical['err'] = count($context['errors']);
        }
        
        return $critical;
    }
    
    /**
     * 查找重复模式
     */
    private function findPatterns(array $data, string $prefix = ''): array
    {
        $patterns = [];
        $content = json_encode($data);
        
        // 使用滑动窗口查找重复字符串
        $minLength = 20;
        $maxLength = 100;
        
        for ($length = $maxLength; $length >= $minLength; $length--) {
            for ($i = 0; $i <= strlen($content) - $length; $i++) {
                $substring = substr($content, $i, $length);
                
                // 跳过已经处理的模式
                if (isset($patterns[$substring])) {
                    continue;
                }
                
                // 计算出现次数
                $count = substr_count($content, $substring);
                if ($count > 1) {
                    $patterns[$substring] = $count;
                }
            }
        }
        
        // 按节省空间排序
        arsort($patterns);
        
        return array_slice($patterns, 0, 10); // 只保留前10个最有价值的模式
    }
    
    private function generateRefKey(string $pattern): string
    {
        return '_ref_' . substr(md5($pattern), 0, 8);
    }
    
    private function replaceWithReferences(array $data, string $pattern, string $ref): array
    {
        $json = json_encode($data);
        $json = str_replace($pattern, $ref, $json);
        return json_decode($json, true);
    }
    
    private function abbreviate(string $type): string
    {
        $abbreviations = [
            'sql_injection' => 'sqli',
            'cross_site_scripting' => 'xss',
            'performance' => 'perf',
            'security' => 'sec',
            'complexity' => 'cplx'
        ];
        
        return $abbreviations[$type] ?? substr($type, 0, 4);
    }
    
    private function abbreviateSeverity(string $severity): string
    {
        return match($severity) {
            'critical' => 'C',
            'high' => 'H',
            'medium' => 'M',
            'low' => 'L',
            default => 'U'
        };
    }
    
    private function compressMessages(array $issues): array
    {
        $messages = [];
        foreach ($issues as $issue) {
            if (isset($issue['message'])) {
                // 提取关键词
                $keywords = $this->extractKeywords($issue['message']);
                $messages[] = implode(' ', $keywords);
            }
        }
        return array_unique($messages);
    }
    
    private function extractKeywords(string $text): array
    {
        // 移除常见词
        $stopWords = ['the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'but'];
        $words = str_word_count(strtolower($text), 1);
        return array_diff($words, $stopWords);
    }
    
    private function isImportantComment(string $comment): bool
    {
        $important = ['todo', 'fixme', 'hack', 'bug', 'security', 'performance'];
        $lower = strtolower($comment);
        
        foreach ($important as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function compressComment(string $comment): string
    {
        // 移除注释符号并压缩空格
        $comment = preg_replace('/^[\/\#\*\s]+/', '', $comment);
        return preg_replace('/\s+/', ' ', trim($comment));
    }
    
    private function compressCodeLine(string $line): string
    {
        // 保留缩进信息但压缩空格
        return rtrim($line);
    }
    
    private function getIndentLevel(string $line): int
    {
        $spaces = strlen($line) - strlen(ltrim($line));
        return intval($spaces / 4); // 假设4个空格为一个缩进级别
    }
    
    private function reduceContent($content, float $ratio): mixed
    {
        if (is_array($content)) {
            $targetCount = intval(count($content) * $ratio);
            return array_slice($content, 0, $targetCount);
        }
        
        if (is_string($content)) {
            $targetLength = intval(strlen($content) * $ratio);
            return substr($content, 0, $targetLength) . '...';
        }
        
        return $content;
    }
    
    private function initializeRules(): void
    {
        $this->compressionRules = [
            'remove_whitespace' => true,
            'abbreviate_types' => true,
            'group_similar' => true,
            'use_references' => true
        ];
        
        $this->preservePatterns = [
            'security_*',
            'critical_*',
            'error_*'
        ];
    }
}