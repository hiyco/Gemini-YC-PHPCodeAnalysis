/**
 * 高性能工作线程池
 * 
 * 特性：
 * - 动态工作线程管理
 * - 负载均衡和任务调度
 * - 错误恢复和重试机制
 * - 性能监控和统计
 * - 内存和CPU使用优化
 */

import { Worker, isMainThread, parentPort, workerData } from 'worker_threads';
import { EventEmitter } from 'events';
import { cpus } from 'os';
import { performance } from 'perf_hooks';

export interface WorkerTask<T = any, R = any> {
  id: string;
  type: string;
  data: T;
  priority: number;
  timeout: number;
  retryCount: number;
  maxRetries: number;
  createdAt: number;
  startedAt?: number;
  completedAt?: number;
}

export interface WorkerResult<R = any> {
  taskId: string;
  success: boolean;
  result?: R;
  error?: Error;
  duration: number;
  memoryUsed: number;
  workerId: number;
}

export interface WorkerConfig {
  minWorkers: number;
  maxWorkers: number;
  idleTimeout: number; // ms
  taskTimeout: number; // ms
  maxRetries: number;
  queueSize: number;
  memoryThreshold: number; // MB
  cpuThreshold: number; // percentage
  enableMonitoring: boolean;
  workerScript: string;
}

export interface WorkerStats {
  workerId: number;
  tasksCompleted: number;
  tasksFailure: number;
  averageTaskTime: number;
  memoryUsage: number;
  cpuUsage: number;
  isIdle: boolean;
  lastTaskAt: number;
  createdAt: number;
  restartCount: number;
}

export interface PoolStats {
  activeWorkers: number;
  idleWorkers: number;
  queuedTasks: number;
  completedTasks: number;
  failedTasks: number;
  averageWaitTime: number;
  averageExecutionTime: number;
  totalMemoryUsage: number;
  averageCpuUsage: number;
  throughput: number; // tasks per second
}

/**
 * 工作线程包装器
 */
class WorkerWrapper extends EventEmitter {
  public readonly id: number;
  public readonly worker: Worker;
  public readonly stats: WorkerStats;
  private currentTask?: WorkerTask;
  private lastHeartbeat: number;
  private destroyed = false;

  constructor(id: number, workerScript: string) {
    super();
    
    this.id = id;
    this.worker = new Worker(workerScript, {
      workerData: { workerId: id }
    });
    
    this.stats = {
      workerId: id,
      tasksCompleted: 0,
      tasksFailure: 0,
      averageTaskTime: 0,
      memoryUsage: 0,
      cpuUsage: 0,
      isIdle: true,
      lastTaskAt: 0,
      createdAt: Date.now(),
      restartCount: 0
    };
    
    this.lastHeartbeat = Date.now();
    this.setupWorkerHandlers();
  }

  public async executeTask<T, R>(task: WorkerTask<T, R>): Promise<WorkerResult<R>> {
    if (this.destroyed) {
      throw new Error(`Worker ${this.id} is destroyed`);
    }

    return new Promise((resolve, reject) => {
      this.currentTask = task;
      this.stats.isIdle = false;
      this.stats.lastTaskAt = Date.now();
      
      const timeout = setTimeout(() => {
        reject(new Error(`Task ${task.id} timed out after ${task.timeout}ms`));
        this.handleTaskTimeout(task);
      }, task.timeout);

      const onMessage = (message: any) => {
        clearTimeout(timeout);
        this.worker.off('message', onMessage);
        this.worker.off('error', onError);
        
        this.handleTaskComplete(task, message);
        resolve(message);
      };

      const onError = (error: Error) => {
        clearTimeout(timeout);
        this.worker.off('message', onMessage);
        this.worker.off('error', onError);
        
        this.handleTaskError(task, error);
        reject(error);
      };

      this.worker.on('message', onMessage);
      this.worker.on('error', onError);

      // 发送任务给工作线程
      this.worker.postMessage({
        type: 'task',
        task: {
          ...task,
          startedAt: Date.now()
        }
      });
    });
  }

  public isAvailable(): boolean {
    return !this.destroyed && this.stats.isIdle && !this.currentTask;
  }

  public async terminate(): Promise<void> {
    if (this.destroyed) return;
    
    this.destroyed = true;
    
    try {
      await this.worker.terminate();
    } catch (error) {
      console.error(`Failed to terminate worker ${this.id}:`, error);
    }
    
    this.emit('terminated', this.id);
  }

  public getMemoryUsage(): number {
    return this.stats.memoryUsage;
  }

  public getCpuUsage(): number {
    return this.stats.cpuUsage;
  }

  private setupWorkerHandlers(): void {
    this.worker.on('error', (error) => {
      console.error(`Worker ${this.id} error:`, error);
      this.emit('error', error);
    });

    this.worker.on('exit', (code) => {
      if (code !== 0) {
        console.error(`Worker ${this.id} exited with code ${code}`);
      }
      this.emit('exit', code);
    });

    // 心跳监听
    this.worker.on('message', (message) => {
      if (message.type === 'heartbeat') {
        this.lastHeartbeat = Date.now();
        this.updateStats(message.stats);
      }
    });
  }

  private handleTaskComplete<T, R>(task: WorkerTask<T, R>, result: WorkerResult<R>): void {
    this.currentTask = undefined;
    this.stats.isIdle = true;
    this.stats.tasksCompleted++;
    
    const taskTime = result.duration;
    this.stats.averageTaskTime = 
      (this.stats.averageTaskTime * (this.stats.tasksCompleted - 1) + taskTime) / 
      this.stats.tasksCompleted;
    
    this.emit('taskComplete', { task, result });
  }

  private handleTaskError<T>(task: WorkerTask<T>, error: Error): void {
    this.currentTask = undefined;
    this.stats.isIdle = true;
    this.stats.tasksFailure++;
    
    this.emit('taskError', { task, error });
  }

  private handleTaskTimeout<T>(task: WorkerTask<T>): void {
    this.currentTask = undefined;
    this.stats.isIdle = true;
    this.stats.tasksFailure++;
    
    this.emit('taskTimeout', { task });
    
    // 可能需要重启工作线程
    this.emit('needRestart', this.id);
  }

  private updateStats(stats: any): void {
    if (stats) {
      this.stats.memoryUsage = stats.memoryUsage || 0;
      this.stats.cpuUsage = stats.cpuUsage || 0;
    }
  }

  public isHealthy(): boolean {
    const now = Date.now();
    const timeSinceHeartbeat = now - this.lastHeartbeat;
    
    // 检查心跳超时（30秒）
    return timeSinceHeartbeat < 30000;
  }
}

/**
 * 工作线程池
 */
export class WorkerPool extends EventEmitter {
  private workers: Map<number, WorkerWrapper> = new Map();
  private taskQueue: WorkerTask[] = [];
  private pendingTasks: Map<string, WorkerTask> = new Map();
  private config: WorkerConfig;
  private nextWorkerId = 1;
  private nextTaskId = 1;
  private stats: PoolStats;
  private monitorTimer?: NodeJS.Timeout;
  private balanceTimer?: NodeJS.Timeout;

  constructor(config: Partial<WorkerConfig> = {}) {
    super();
    
    this.config = {
      minWorkers: Math.max(1, Math.floor(cpus().length / 2)),
      maxWorkers: cpus().length,
      idleTimeout: 30000, // 30秒
      taskTimeout: 60000, // 60秒
      maxRetries: 3,
      queueSize: 1000,
      memoryThreshold: 512, // 512MB
      cpuThreshold: 80, // 80%
      enableMonitoring: true,
      workerScript: __dirname + '/php-analysis-worker.js',
      ...config
    };

    this.stats = {
      activeWorkers: 0,
      idleWorkers: 0,
      queuedTasks: 0,
      completedTasks: 0,
      failedTasks: 0,
      averageWaitTime: 0,
      averageExecutionTime: 0,
      totalMemoryUsage: 0,
      averageCpuUsage: 0,
      throughput: 0
    };

    this.initialize();
  }

  /**
   * 添加任务到队列
   */
  public async addTask<T, R>(
    type: string,
    data: T,
    options: {
      priority?: number;
      timeout?: number;
      maxRetries?: number;
    } = {}
  ): Promise<R> {
    const task: WorkerTask<T, R> = {
      id: `task_${this.nextTaskId++}`,
      type,
      data,
      priority: options.priority || 0,
      timeout: options.timeout || this.config.taskTimeout,
      retryCount: 0,
      maxRetries: options.maxRetries || this.config.maxRetries,
      createdAt: Date.now()
    };

    if (this.taskQueue.length >= this.config.queueSize) {
      throw new Error('Task queue is full');
    }

    return new Promise((resolve, reject) => {
      // 将任务添加到队列
      this.taskQueue.push(task);
      this.taskQueue.sort((a, b) => b.priority - a.priority);
      
      this.pendingTasks.set(task.id, task);
      this.stats.queuedTasks = this.taskQueue.length;

      // 设置任务完成回调
      const onTaskComplete = (result: WorkerResult<R>) => {
        if (result.taskId === task.id) {
          this.off('taskComplete', onTaskComplete);
          this.off('taskError', onTaskError);
          
          this.pendingTasks.delete(task.id);
          this.updateCompletionStats(result);
          
          if (result.success) {
            resolve(result.result!);
          } else {
            reject(result.error);
          }
        }
      };

      const onTaskError = (error: { taskId: string; error: Error }) => {
        if (error.taskId === task.id) {
          this.off('taskComplete', onTaskComplete);
          this.off('taskError', onTaskError);
          
          this.pendingTasks.delete(task.id);
          this.stats.failedTasks++;
          
          reject(error.error);
        }
      };

      this.on('taskComplete', onTaskComplete);
      this.on('taskError', onTaskError);

      // 尝试立即分配任务
      this.processTasks();
    });
  }

  /**
   * 获取池状态
   */
  public getStats(): PoolStats {
    this.updateStats();
    return { ...this.stats };
  }

  /**
   * 获取工作线程状态
   */
  public getWorkerStats(): WorkerStats[] {
    return Array.from(this.workers.values()).map(worker => ({ ...worker.stats }));
  }

  /**
   * 关闭线程池
   */
  public async destroy(): Promise<void> {
    if (this.monitorTimer) {
      clearInterval(this.monitorTimer);
    }
    
    if (this.balanceTimer) {
      clearInterval(this.balanceTimer);
    }

    // 等待所有任务完成或超时
    const timeoutPromise = new Promise<void>(resolve => {
      setTimeout(resolve, 10000); // 10秒超时
    });
    
    const completionPromise = this.waitForTasksCompletion();
    
    await Promise.race([completionPromise, timeoutPromise]);

    // 终止所有工作线程
    const terminationPromises = Array.from(this.workers.values())
      .map(worker => worker.terminate());
    
    await Promise.all(terminationPromises);
    
    this.workers.clear();
    this.taskQueue = [];
    this.pendingTasks.clear();
    
    this.emit('destroyed');
  }

  /**
   * 动态调整池大小
   */
  public async resize(newSize: number): Promise<void> {
    const currentSize = this.workers.size;
    const targetSize = Math.max(this.config.minWorkers, Math.min(this.config.maxWorkers, newSize));
    
    if (targetSize > currentSize) {
      // 增加工作线程
      for (let i = 0; i < targetSize - currentSize; i++) {
        await this.createWorker();
      }
    } else if (targetSize < currentSize) {
      // 减少工作线程
      const workersToRemove = currentSize - targetSize;
      const idleWorkers = Array.from(this.workers.values())
        .filter(worker => worker.isAvailable())
        .slice(0, workersToRemove);
      
      for (const worker of idleWorkers) {
        await this.removeWorker(worker.id);
      }
    }
  }

  // 私有方法

  private async initialize(): Promise<void> {
    // 创建最小数量的工作线程
    for (let i = 0; i < this.config.minWorkers; i++) {
      await this.createWorker();
    }

    // 启动监控
    if (this.config.enableMonitoring) {
      this.startMonitoring();
    }

    // 启动负载均衡
    this.startLoadBalancing();
    
    this.emit('initialized');
  }

  private async createWorker(): Promise<WorkerWrapper> {
    const worker = new WorkerWrapper(this.nextWorkerId++, this.config.workerScript);
    
    worker.on('taskComplete', ({ task, result }) => {
      this.emit('taskComplete', { ...result, taskId: task.id });
    });

    worker.on('taskError', ({ task, error }) => {
      this.handleTaskError(task, error);
    });

    worker.on('taskTimeout', ({ task }) => {
      this.handleTaskTimeout(task);
    });

    worker.on('needRestart', (workerId) => {
      this.restartWorker(workerId);
    });

    worker.on('error', (error) => {
      console.error(`Worker ${worker.id} error:`, error);
    });

    worker.on('exit', (code) => {
      if (code !== 0) {
        console.warn(`Worker ${worker.id} exited unexpectedly, restarting...`);
        this.restartWorker(worker.id);
      }
    });

    this.workers.set(worker.id, worker);
    this.emit('workerCreated', worker.id);
    
    return worker;
  }

  private async removeWorker(workerId: number): Promise<void> {
    const worker = this.workers.get(workerId);
    if (!worker) return;

    this.workers.delete(workerId);
    await worker.terminate();
    
    this.emit('workerRemoved', workerId);
  }

  private async restartWorker(workerId: number): Promise<void> {
    const oldWorker = this.workers.get(workerId);
    if (!oldWorker) return;

    // 移除旧工作线程
    await this.removeWorker(workerId);
    
    // 创建新工作线程
    const newWorker = await this.createWorker();
    newWorker.stats.restartCount = (oldWorker.stats.restartCount || 0) + 1;
    
    this.emit('workerRestarted', { oldId: workerId, newId: newWorker.id });
  }

  private processTasks(): void {
    while (this.taskQueue.length > 0) {
      const availableWorker = this.findAvailableWorker();
      if (!availableWorker) break;

      const task = this.taskQueue.shift()!;
      this.stats.queuedTasks = this.taskQueue.length;
      
      this.executeTaskOnWorker(task, availableWorker);
    }
  }

  private findAvailableWorker(): WorkerWrapper | undefined {
    // 优先选择空闲时间最长的工作线程
    return Array.from(this.workers.values())
      .filter(worker => worker.isAvailable())
      .sort((a, b) => a.stats.lastTaskAt - b.stats.lastTaskAt)[0];
  }

  private async executeTaskOnWorker(task: WorkerTask, worker: WorkerWrapper): Promise<void> {
    try {
      task.startedAt = Date.now();
      const result = await worker.executeTask(task);
      this.emit('taskComplete', result);
    } catch (error) {
      this.handleTaskError(task, error as Error);
    }
  }

  private handleTaskError(task: WorkerTask, error: Error): void {
    if (task.retryCount < task.maxRetries) {
      // 重试任务
      task.retryCount++;
      this.taskQueue.unshift(task); // 高优先级重试
      this.stats.queuedTasks = this.taskQueue.length;
      
      setTimeout(() => this.processTasks(), 1000 * task.retryCount); // 延迟重试
    } else {
      // 任务失败
      this.emit('taskError', { taskId: task.id, error });
    }
  }

  private handleTaskTimeout(task: WorkerTask): void {
    const timeoutError = new Error(`Task ${task.id} timed out`);
    this.handleTaskError(task, timeoutError);
  }

  private updateStats(): void {
    const workers = Array.from(this.workers.values());
    
    this.stats.activeWorkers = workers.filter(w => !w.stats.isIdle).length;
    this.stats.idleWorkers = workers.filter(w => w.stats.isIdle).length;
    this.stats.queuedTasks = this.taskQueue.length;
    
    this.stats.totalMemoryUsage = workers.reduce((sum, w) => sum + w.getMemoryUsage(), 0);
    this.stats.averageCpuUsage = workers.length > 0 
      ? workers.reduce((sum, w) => sum + w.getCpuUsage(), 0) / workers.length 
      : 0;
  }

  private updateCompletionStats(result: WorkerResult): void {
    this.stats.completedTasks++;
    
    // 更新平均执行时间
    this.stats.averageExecutionTime = 
      (this.stats.averageExecutionTime * (this.stats.completedTasks - 1) + result.duration) / 
      this.stats.completedTasks;
    
    // 计算吞吐量（最近1分钟）
    const now = Date.now();
    const recentTasks = this.stats.completedTasks; // 简化版本
    this.stats.throughput = recentTasks / 60; // tasks per second
  }

  private startMonitoring(): void {
    this.monitorTimer = setInterval(() => {
      this.monitorWorkerHealth();
      this.updateStats();
    }, 5000); // 每5秒监控一次
  }

  private monitorWorkerHealth(): void {
    for (const worker of this.workers.values()) {
      if (!worker.isHealthy()) {
        console.warn(`Worker ${worker.id} appears unhealthy, restarting...`);
        this.restartWorker(worker.id);
      }
    }
  }

  private startLoadBalancing(): void {
    this.balanceTimer = setInterval(() => {
      this.balanceLoad();
      this.processTasks(); // 继续处理队列中的任务
    }, 1000); // 每秒进行负载均衡
  }

  private async balanceLoad(): Promise<void> {
    const queueLength = this.taskQueue.length;
    const activeWorkers = this.workers.size;
    
    // 如果队列过长且未达到最大工作线程数，则添加工作线程
    if (queueLength > activeWorkers * 2 && activeWorkers < this.config.maxWorkers) {
      await this.createWorker();
    }
    
    // 如果队列为空且有多余的工作线程，则移除空闲工作线程
    if (queueLength === 0 && activeWorkers > this.config.minWorkers) {
      const idleWorkers = Array.from(this.workers.values())
        .filter(worker => worker.isAvailable() && 
                Date.now() - worker.stats.lastTaskAt > this.config.idleTimeout);
      
      if (idleWorkers.length > 0) {
        const workerToRemove = idleWorkers[0];
        await this.removeWorker(workerToRemove.id);
      }
    }
  }

  private async waitForTasksCompletion(): Promise<void> {
    while (this.taskQueue.length > 0 || this.pendingTasks.size > 0) {
      await new Promise(resolve => setTimeout(resolve, 100));
    }
  }
}