/**
 * 高性能缓存管理器
 * 
 * 特性：
 * - 多层级缓存架构
 * - 智能缓存策略
 * - 内存和持久化缓存
 * - 缓存压缩和序列化
 * - 增量更新支持
 * - 缓存统计和监控
 */

import { LRUCache } from 'lru-cache';
import { createHash } from 'crypto';
import { readFile, writeFile, mkdir, stat } from 'fs/promises';
import { existsSync } from 'fs';
import { join } from 'path';
import { gzip, gunzip } from 'zlib';
import { promisify } from 'util';

const gzipAsync = promisify(gzip);
const gunzipAsync = promisify(gunzip);

export interface CacheEntry<T = any> {
  key: string;
  value: T;
  timestamp: number;
  ttl: number;
  accessCount: number;
  lastAccess: number;
  size: number;
  compressed: boolean;
  checksum: string;
}

export interface CacheConfig {
  // 内存缓存配置
  memorySize: number; // MB
  memoryTTL: number; // ms
  
  // 持久化缓存配置
  persistentCache: boolean;
  cacheDirectory: string;
  maxDiskSize: number; // MB
  diskTTL: number; // ms
  
  // 压缩配置
  enableCompression: boolean;
  compressionThreshold: number; // bytes
  compressionLevel: number; // 1-9
  
  // 性能配置
  enableStatistics: boolean;
  cleanupInterval: number; // ms
  maxConcurrentOperations: number;
  
  // 增量更新
  enableIncrementalUpdate: boolean;
  dependencyTracking: boolean;
}

export interface CacheStatistics {
  memoryCache: CacheStats;
  diskCache: CacheStats;
  overall: OverallStats;
}

export interface CacheStats {
  hitCount: number;
  missCount: number;
  hitRate: number;
  entryCount: number;
  totalSize: number;
  averageSize: number;
  oldestEntry: number;
  newestEntry: number;
}

export interface OverallStats {
  totalRequests: number;
  totalHits: number;
  totalMisses: number;
  overallHitRate: number;
  compressionRatio: number;
  diskSpaceSaved: number;
  averageAccessTime: number;
}

export interface CacheDependency {
  key: string;
  dependencies: string[];
  timestamp: number;
}

/**
 * 高性能缓存管理器
 */
export class CacheManager {
  private memoryCache: LRUCache<string, CacheEntry>;
  private diskCache: Map<string, string> = new Map(); // key -> filepath
  private dependencies: Map<string, CacheDependency> = new Map();
  private statistics: CacheStatistics;
  private config: CacheConfig;
  private cleanupTimer?: NodeJS.Timeout;
  private concurrentOperations = 0;

  constructor(config: Partial<CacheConfig> = {}) {
    this.config = {
      memorySize: 128, // 128MB
      memoryTTL: 60 * 60 * 1000, // 1 hour
      persistentCache: true,
      cacheDirectory: './cache',
      maxDiskSize: 512, // 512MB
      diskTTL: 24 * 60 * 60 * 1000, // 24 hours
      enableCompression: true,
      compressionThreshold: 1024, // 1KB
      compressionLevel: 6,
      enableStatistics: true,
      cleanupInterval: 5 * 60 * 1000, // 5 minutes
      maxConcurrentOperations: 10,
      enableIncrementalUpdate: true,
      dependencyTracking: true,
      ...config
    };

    this.initializeMemoryCache();
    this.initializeStatistics();
    this.startCleanupTimer();
    
    if (this.config.persistentCache) {
      this.ensureCacheDirectory();
    }
  }

  /**
   * 获取缓存项
   */
  public async get<T = any>(key: string): Promise<T | undefined> {
    const startTime = process.hrtime.bigint();
    
    try {
      // 首先检查内存缓存
      const memoryEntry = this.memoryCache.get(key);
      if (memoryEntry && this.isEntryValid(memoryEntry)) {
        this.updateAccessStats(memoryEntry);
        this.recordHit('memory', startTime);
        return memoryEntry.value;
      }

      // 检查磁盘缓存
      if (this.config.persistentCache) {
        const diskEntry = await this.getDiskEntry<T>(key);
        if (diskEntry && this.isEntryValid(diskEntry)) {
          // 将热数据提升到内存缓存
          this.memoryCache.set(key, diskEntry);
          this.updateAccessStats(diskEntry);
          this.recordHit('disk', startTime);
          return diskEntry.value;
        }
      }

      this.recordMiss(startTime);
      return undefined;
    } catch (error) {
      console.error(`Cache get error for key ${key}:`, error);
      this.recordMiss(startTime);
      return undefined;
    }
  }

  /**
   * 设置缓存项
   */
  public async set<T = any>(
    key: string, 
    value: T, 
    options: {
      ttl?: number;
      dependencies?: string[];
      forceCompression?: boolean;
    } = {}
  ): Promise<void> {
    if (this.concurrentOperations >= this.config.maxConcurrentOperations) {
      throw new Error('Too many concurrent cache operations');
    }

    this.concurrentOperations++;
    
    try {
      const entry = await this.createCacheEntry(key, value, options);
      
      // 设置内存缓存
      this.memoryCache.set(key, entry);
      
      // 设置磁盘缓存
      if (this.config.persistentCache) {
        await this.setDiskEntry(key, entry);
      }
      
      // 记录依赖关系
      if (this.config.dependencyTracking && options.dependencies) {
        this.dependencies.set(key, {
          key,
          dependencies: options.dependencies,
          timestamp: Date.now()
        });
      }
      
      // 更新统计
      if (this.config.enableStatistics) {
        this.updateSetStats(entry);
      }
    } finally {
      this.concurrentOperations--;
    }
  }

  /**
   * 删除缓存项
   */
  public async delete(key: string): Promise<boolean> {
    let deleted = false;
    
    // 从内存缓存删除
    if (this.memoryCache.has(key)) {
      this.memoryCache.delete(key);
      deleted = true;
    }
    
    // 从磁盘缓存删除
    if (this.config.persistentCache && this.diskCache.has(key)) {
      try {
        const filePath = this.diskCache.get(key)!;
        await this.deleteDiskFile(filePath);
        this.diskCache.delete(key);
        deleted = true;
      } catch (error) {
        console.error(`Failed to delete disk cache file for key ${key}:`, error);
      }
    }
    
    // 删除依赖关系
    this.dependencies.delete(key);
    
    // 删除依赖此项的其他缓存项
    if (this.config.dependencyTracking) {
      await this.invalidateDependents(key);
    }
    
    return deleted;
  }

  /**
   * 清空所有缓存
   */
  public async clear(): Promise<void> {
    // 清空内存缓存
    this.memoryCache.clear();
    
    // 清空磁盘缓存
    if (this.config.persistentCache) {
      try {
        const cacheFiles = Array.from(this.diskCache.values());
        await Promise.all(cacheFiles.map(file => this.deleteDiskFile(file)));
        this.diskCache.clear();
      } catch (error) {
        console.error('Failed to clear disk cache:', error);
      }
    }
    
    // 清空依赖关系
    this.dependencies.clear();
    
    // 重置统计
    this.initializeStatistics();
  }

  /**
   * 检查缓存项是否存在
   */
  public async has(key: string): Promise<boolean> {
    return (await this.get(key)) !== undefined;
  }

  /**
   * 获取缓存统计
   */
  public getStatistics(): CacheStatistics {
    if (!this.config.enableStatistics) {
      throw new Error('Statistics are disabled');
    }
    
    return {
      ...this.statistics,
      memoryCache: this.getMemoryCacheStats(),
      diskCache: this.getDiskCacheStats()
    };
  }

  /**
   * 获取缓存键列表
   */
  public getKeys(): string[] {
    const memoryKeys = [...this.memoryCache.keys()];
    const diskKeys = [...this.diskCache.keys()];
    return [...new Set([...memoryKeys, ...diskKeys])];
  }

  /**
   * 压缩缓存
   */
  public async compress(): Promise<void> {
    const entries = [...this.memoryCache.entries()];
    
    for (const [key, entry] of entries) {
      if (!entry.compressed && this.shouldCompress(entry)) {
        const compressedValue = await this.compressValue(entry.value);
        const newEntry: CacheEntry = {
          ...entry,
          value: compressedValue,
          compressed: true,
          size: JSON.stringify(compressedValue).length
        };
        
        this.memoryCache.set(key, newEntry);
      }
    }
  }

  /**
   * 优化缓存
   */
  public async optimize(): Promise<void> {
    // 清理过期条目
    await this.cleanup();
    
    // 压缩大体积条目
    await this.compress();
    
    // 合并磁盘缓存文件
    if (this.config.persistentCache) {
      await this.consolidateDiskCache();
    }
    
    // 重建依赖关系图
    if (this.config.dependencyTracking) {
      await this.rebuildDependencyGraph();
    }
  }

  /**
   * 增量更新缓存
   */
  public async incrementalUpdate(changes: Map<string, any>): Promise<void> {
    if (!this.config.enableIncrementalUpdate) {
      throw new Error('Incremental update is disabled');
    }
    
    for (const [key, value] of changes) {
      // 检查是否有依赖此项的缓存
      const dependents = this.findDependents(key);
      
      // 更新缓存项
      await this.set(key, value);
      
      // 失效依赖项
      for (const dependent of dependents) {
        await this.delete(dependent);
      }
    }
  }

  /**
   * 销毁缓存管理器
   */
  public async destroy(): Promise<void> {
    if (this.cleanupTimer) {
      clearInterval(this.cleanupTimer);
    }
    
    // 等待并发操作完成
    while (this.concurrentOperations > 0) {
      await new Promise(resolve => setTimeout(resolve, 10));
    }
    
    // 清理资源
    this.memoryCache.clear();
    this.diskCache.clear();
    this.dependencies.clear();
  }

  // 私有方法

  private initializeMemoryCache(): void {
    const maxSize = Math.floor((this.config.memorySize * 1024 * 1024) / 1024); // 假设平均条目大小1KB
    
    this.memoryCache = new LRUCache({
      max: maxSize,
      ttl: this.config.memoryTTL,
      updateAgeOnGet: true,
      allowStale: false,
      dispose: (value, key) => {
        this.onEntryEvicted(key, value);
      }
    });
  }

  private initializeStatistics(): void {
    this.statistics = {
      memoryCache: {
        hitCount: 0,
        missCount: 0,
        hitRate: 0,
        entryCount: 0,
        totalSize: 0,
        averageSize: 0,
        oldestEntry: 0,
        newestEntry: 0
      },
      diskCache: {
        hitCount: 0,
        missCount: 0,
        hitRate: 0,
        entryCount: 0,
        totalSize: 0,
        averageSize: 0,
        oldestEntry: 0,
        newestEntry: 0
      },
      overall: {
        totalRequests: 0,
        totalHits: 0,
        totalMisses: 0,
        overallHitRate: 0,
        compressionRatio: 0,
        diskSpaceSaved: 0,
        averageAccessTime: 0
      }
    };
  }

  private async ensureCacheDirectory(): Promise<void> {
    if (!existsSync(this.config.cacheDirectory)) {
      await mkdir(this.config.cacheDirectory, { recursive: true });
    }
  }

  private async createCacheEntry<T>(
    key: string, 
    value: T, 
    options: any
  ): Promise<CacheEntry<T>> {
    const serializedValue = JSON.stringify(value);
    let finalValue = value;
    let compressed = false;
    let size = serializedValue.length;

    // 压缩处理
    if (this.shouldCompress({ size } as any, options.forceCompression)) {
      try {
        const compressedBuffer = await gzipAsync(Buffer.from(serializedValue));
        finalValue = compressedBuffer as any;
        compressed = true;
        size = compressedBuffer.length;
      } catch (error) {
        console.warn(`Failed to compress cache entry for key ${key}:`, error);
      }
    }

    return {
      key,
      value: finalValue,
      timestamp: Date.now(),
      ttl: options.ttl || this.config.memoryTTL,
      accessCount: 0,
      lastAccess: Date.now(),
      size,
      compressed,
      checksum: this.generateChecksum(serializedValue)
    };
  }

  private async getDiskEntry<T>(key: string): Promise<CacheEntry<T> | undefined> {
    if (!this.diskCache.has(key)) {
      return undefined;
    }

    try {
      const filePath = this.diskCache.get(key)!;
      const data = await readFile(filePath, 'utf-8');
      const entry: CacheEntry = JSON.parse(data);

      // 验证校验和
      let valueToVerify: string;
      if (entry.compressed) {
        const decompressedBuffer = await gunzipAsync(Buffer.from(entry.value as any));
        valueToVerify = decompressedBuffer.toString();
        entry.value = JSON.parse(valueToVerify);
      } else {
        valueToVerify = JSON.stringify(entry.value);
      }

      if (entry.checksum !== this.generateChecksum(valueToVerify)) {
        console.warn(`Checksum mismatch for cache key ${key}`);
        return undefined;
      }

      return entry;
    } catch (error) {
      console.error(`Failed to read disk cache for key ${key}:`, error);
      return undefined;
    }
  }

  private async setDiskEntry<T>(key: string, entry: CacheEntry<T>): Promise<void> {
    try {
      const filePath = join(this.config.cacheDirectory, `${this.hashKey(key)}.json`);
      const data = JSON.stringify(entry);
      await writeFile(filePath, data, 'utf-8');
      this.diskCache.set(key, filePath);
    } catch (error) {
      console.error(`Failed to write disk cache for key ${key}:`, error);
    }
  }

  private async deleteDiskFile(filePath: string): Promise<void> {
    try {
      const { unlink } = await import('fs/promises');
      await unlink(filePath);
    } catch (error) {
      // 忽略文件不存在的错误
      if ((error as any).code !== 'ENOENT') {
        throw error;
      }
    }
  }

  private isEntryValid(entry: CacheEntry): boolean {
    const now = Date.now();
    return (now - entry.timestamp) < entry.ttl;
  }

  private shouldCompress(entry: CacheEntry, force = false): boolean {
    return force || 
           (this.config.enableCompression && 
            entry.size >= this.config.compressionThreshold);
  }

  private async compressValue(value: any): Promise<Buffer> {
    const serialized = JSON.stringify(value);
    return gzipAsync(Buffer.from(serialized));
  }

  private generateChecksum(data: string): string {
    return createHash('md5').update(data).digest('hex');
  }

  private hashKey(key: string): string {
    return createHash('sha256').update(key).digest('hex');
  }

  private updateAccessStats(entry: CacheEntry): void {
    entry.accessCount++;
    entry.lastAccess = Date.now();
  }

  private recordHit(cacheType: 'memory' | 'disk', startTime: bigint): void {
    if (!this.config.enableStatistics) return;

    const accessTime = Number(process.hrtime.bigint() - startTime) / 1000000; // ms
    
    this.statistics.overall.totalRequests++;
    this.statistics.overall.totalHits++;
    this.statistics.overall.averageAccessTime = 
      (this.statistics.overall.averageAccessTime + accessTime) / 2;
    
    this.statistics[cacheType === 'memory' ? 'memoryCache' : 'diskCache'].hitCount++;
    this.updateHitRates();
  }

  private recordMiss(startTime: bigint): void {
    if (!this.config.enableStatistics) return;

    const accessTime = Number(process.hrtime.bigint() - startTime) / 1000000; // ms
    
    this.statistics.overall.totalRequests++;
    this.statistics.overall.totalMisses++;
    this.statistics.overall.averageAccessTime = 
      (this.statistics.overall.averageAccessTime + accessTime) / 2;
    
    this.updateHitRates();
  }

  private updateSetStats(entry: CacheEntry): void {
    if (!this.config.enableStatistics) return;
    
    // 更新压缩比率
    if (entry.compressed) {
      const originalSize = JSON.stringify(entry.value).length;
      const compressionRatio = entry.size / originalSize;
      this.statistics.overall.compressionRatio = 
        (this.statistics.overall.compressionRatio + compressionRatio) / 2;
      this.statistics.overall.diskSpaceSaved += (originalSize - entry.size);
    }
  }

  private updateHitRates(): void {
    const { memoryCache, diskCache, overall } = this.statistics;
    
    memoryCache.hitRate = memoryCache.hitCount / 
      (memoryCache.hitCount + memoryCache.missCount) || 0;
    diskCache.hitRate = diskCache.hitCount / 
      (diskCache.hitCount + diskCache.missCount) || 0;
    overall.overallHitRate = overall.totalHits / overall.totalRequests || 0;
  }

  private getMemoryCacheStats(): CacheStats {
    const entries = [...this.memoryCache.values()];
    const totalSize = entries.reduce((sum, entry) => sum + entry.size, 0);
    
    return {
      hitCount: this.statistics.memoryCache.hitCount,
      missCount: this.statistics.memoryCache.missCount,
      hitRate: this.statistics.memoryCache.hitRate,
      entryCount: this.memoryCache.size,
      totalSize,
      averageSize: totalSize / this.memoryCache.size || 0,
      oldestEntry: Math.min(...entries.map(e => e.timestamp)) || 0,
      newestEntry: Math.max(...entries.map(e => e.timestamp)) || 0
    };
  }

  private getDiskCacheStats(): CacheStats {
    return {
      hitCount: this.statistics.diskCache.hitCount,
      missCount: this.statistics.diskCache.missCount,
      hitRate: this.statistics.diskCache.hitRate,
      entryCount: this.diskCache.size,
      totalSize: 0, // TODO: 计算磁盘缓存总大小
      averageSize: 0,
      oldestEntry: 0,
      newestEntry: 0
    };
  }

  private onEntryEvicted(key: string, entry: CacheEntry): void {
    // 条目被从内存缓存中驱逐时的处理
    if (this.config.enableStatistics) {
      // 更新统计信息
    }
  }

  private startCleanupTimer(): void {
    if (this.config.cleanupInterval > 0) {
      this.cleanupTimer = setInterval(() => {
        this.cleanup().catch(error => {
          console.error('Cache cleanup error:', error);
        });
      }, this.config.cleanupInterval);
    }
  }

  private async cleanup(): Promise<void> {
    const now = Date.now();
    const expiredKeys: string[] = [];
    
    // 清理内存缓存中的过期条目
    for (const [key, entry] of this.memoryCache.entries()) {
      if (!this.isEntryValid(entry)) {
        expiredKeys.push(key);
      }
    }
    
    for (const key of expiredKeys) {
      this.memoryCache.delete(key);
    }
    
    // 清理磁盘缓存中的过期文件
    if (this.config.persistentCache) {
      const diskExpiredKeys: string[] = [];
      
      for (const [key, filePath] of this.diskCache) {
        try {
          const stats = await stat(filePath);
          const age = now - stats.mtime.getTime();
          
          if (age > this.config.diskTTL) {
            diskExpiredKeys.push(key);
          }
        } catch (error) {
          // 文件不存在，标记为过期
          diskExpiredKeys.push(key);
        }
      }
      
      for (const key of diskExpiredKeys) {
        await this.delete(key);
      }
    }
  }

  private async invalidateDependents(key: string): Promise<void> {
    const dependents = this.findDependents(key);
    
    for (const dependent of dependents) {
      await this.delete(dependent);
    }
  }

  private findDependents(key: string): string[] {
    const dependents: string[] = [];
    
    for (const [depKey, dependency] of this.dependencies) {
      if (dependency.dependencies.includes(key)) {
        dependents.push(depKey);
      }
    }
    
    return dependents;
  }

  private async consolidateDiskCache(): Promise<void> {
    // TODO: 实现磁盘缓存文件合并
  }

  private async rebuildDependencyGraph(): Promise<void> {
    // TODO: 重建依赖关系图
  }
}