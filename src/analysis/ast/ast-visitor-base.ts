/**
 * AST访问者模式基类
 * 
 * 提供高性能的AST遍历和分析能力：
 * - 类型安全的节点访问
 * - 可配置的遍历策略
 * - 内存优化的深度优先遍历
 * - 上下文栈管理
 */

import { ASTNode } from './php-ast-parser';

export interface VisitorContext {
  parent?: ASTNode;
  ancestors: ASTNode[];
  depth: number;
  index: number;
  siblings: ASTNode[];
  filePath?: string;
  sourceCode?: string;
}

export interface VisitorOptions {
  maxDepth?: number;
  skipTypes?: string[];
  onlyTypes?: string[];
  trackContext?: boolean;
  enableMetrics?: boolean;
}

export interface VisitorMetrics {
  nodesVisited: number;
  visitTime: number;
  maxDepth: number;
  typeDistribution: Map<string, number>;
}

/**
 * AST访问者基类
 */
export abstract class ASTVisitorBase {
  protected context: VisitorContext;
  protected options: VisitorOptions;
  protected metrics: VisitorMetrics;
  private startTime: bigint;

  constructor(options: VisitorOptions = {}) {
    this.options = {
      maxDepth: 100,
      trackContext: true,
      enableMetrics: true,
      ...options
    };

    this.context = {
      ancestors: [],
      depth: 0,
      index: 0,
      siblings: []
    };

    this.metrics = {
      nodesVisited: 0,
      visitTime: 0,
      maxDepth: 0,
      typeDistribution: new Map()
    };

    this.startTime = process.hrtime.bigint();
  }

  /**
   * 开始遍历AST
   */
  public visit(ast: ASTNode, filePath?: string, sourceCode?: string): void {
    this.context.filePath = filePath;
    this.context.sourceCode = sourceCode;
    this.startTime = process.hrtime.bigint();
    
    this.traverse(ast, [], 0);
    
    if (this.options.enableMetrics) {
      const endTime = process.hrtime.bigint();
      this.metrics.visitTime = Number(endTime - this.startTime) / 1000000; // ms
    }
  }

  /**
   * 递归遍历AST节点
   */
  private traverse(node: ASTNode, ancestors: ASTNode[], depth: number): void {
    // 检查深度限制
    if (this.options.maxDepth && depth > this.options.maxDepth) {
      return;
    }

    // 检查类型过滤
    if (this.shouldSkipNode(node)) {
      return;
    }

    // 更新上下文
    if (this.options.trackContext) {
      this.updateContext(node, ancestors, depth);
    }

    // 更新指标
    if (this.options.enableMetrics) {
      this.updateMetrics(node, depth);
    }

    // 调用进入节点处理
    const shouldContinue = this.enterNode(node, this.context);
    
    if (shouldContinue !== false) {
      // 遍历子节点
      const children = node.children || [];
      const newAncestors = this.options.trackContext ? [...ancestors, node] : [];
      
      for (let i = 0; i < children.length; i++) {
        const child = children[i];
        
        if (this.options.trackContext) {
          this.context.index = i;
          this.context.siblings = children;
        }
        
        this.traverse(child, newAncestors, depth + 1);
      }
    }

    // 调用离开节点处理
    this.leaveNode(node, this.context);

    // 恢复上下文
    if (this.options.trackContext && ancestors.length > 0) {
      this.context.parent = ancestors[ancestors.length - 1];
      this.context.ancestors = [...ancestors];
      this.context.depth = depth - 1;
    }
  }

  /**
   * 更新访问上下文
   */
  private updateContext(node: ASTNode, ancestors: ASTNode[], depth: number): void {
    this.context.parent = ancestors.length > 0 ? ancestors[ancestors.length - 1] : undefined;
    this.context.ancestors = [...ancestors];
    this.context.depth = depth;
  }

  /**
   * 更新访问指标
   */
  private updateMetrics(node: ASTNode, depth: number): void {
    this.metrics.nodesVisited++;
    this.metrics.maxDepth = Math.max(this.metrics.maxDepth, depth);
    
    const count = this.metrics.typeDistribution.get(node.nodeType) || 0;
    this.metrics.typeDistribution.set(node.nodeType, count + 1);
  }

  /**
   * 检查是否应该跳过节点
   */
  private shouldSkipNode(node: ASTNode): boolean {
    if (this.options.skipTypes?.includes(node.nodeType)) {
      return true;
    }
    
    if (this.options.onlyTypes && !this.options.onlyTypes.includes(node.nodeType)) {
      return true;
    }
    
    return false;
  }

  // 抽象方法，需要子类实现

  /**
   * 进入节点时调用
   * @param node 当前节点
   * @param context 访问上下文
   * @returns false表示跳过子节点遍历
   */
  protected abstract enterNode(node: ASTNode, context: VisitorContext): void | boolean;

  /**
   * 离开节点时调用
   * @param node 当前节点
   * @param context 访问上下文
   */
  protected abstract leaveNode(node: ASTNode, context: VisitorContext): void;

  // 工具方法

  /**
   * 获取当前节点的完整路径
   */
  protected getNodePath(): string {
    return this.context.ancestors
      .map(node => node.nodeType)
      .concat([this.getCurrentNodeType()])
      .join(' > ');
  }

  /**
   * 获取当前节点类型
   */
  protected getCurrentNodeType(): string {
    return this.context.ancestors.length > 0 
      ? this.context.ancestors[this.context.ancestors.length - 1].nodeType 
      : 'Root';
  }

  /**
   * 检查是否在特定上下文中
   */
  protected isInContext(nodeTypes: string[]): boolean {
    return this.context.ancestors.some(ancestor => nodeTypes.includes(ancestor.nodeType));
  }

  /**
   * 查找最近的祖先节点
   */
  protected findAncestor(predicate: (node: ASTNode) => boolean): ASTNode | undefined {
    for (let i = this.context.ancestors.length - 1; i >= 0; i--) {
      if (predicate(this.context.ancestors[i])) {
        return this.context.ancestors[i];
      }
    }
    return undefined;
  }

  /**
   * 查找最近的特定类型祖先
   */
  protected findAncestorByType(nodeType: string): ASTNode | undefined {
    return this.findAncestor(node => node.nodeType === nodeType);
  }

  /**
   * 获取访问指标
   */
  public getMetrics(): VisitorMetrics {
    return { ...this.metrics };
  }

  /**
   * 重置访问器状态
   */
  public reset(): void {
    this.context = {
      ancestors: [],
      depth: 0,
      index: 0,
      siblings: []
    };

    this.metrics = {
      nodesVisited: 0,
      visitTime: 0,
      maxDepth: 0,
      typeDistribution: new Map()
    };
  }
}

/**
 * 组合访问者 - 允许同时运行多个访问者
 */
export class CompositeVisitor extends ASTVisitorBase {
  private visitors: ASTVisitorBase[];

  constructor(visitors: ASTVisitorBase[], options?: VisitorOptions) {
    super(options);
    this.visitors = visitors;
  }

  protected enterNode(node: ASTNode, context: VisitorContext): void | boolean {
    let shouldContinue = true;
    
    for (const visitor of this.visitors) {
      // 设置相同的上下文
      (visitor as any).context = { ...context };
      const result = (visitor as any).enterNode(node, context);
      if (result === false) {
        shouldContinue = false;
      }
    }
    
    return shouldContinue;
  }

  protected leaveNode(node: ASTNode, context: VisitorContext): void {
    for (const visitor of this.visitors) {
      (visitor as any).context = { ...context };
      (visitor as any).leaveNode(node, context);
    }
  }

  /**
   * 获取所有访问者的指标
   */
  public getAllMetrics(): Map<string, VisitorMetrics> {
    const metrics = new Map<string, VisitorMetrics>();
    
    for (const visitor of this.visitors) {
      const visitorName = visitor.constructor.name;
      metrics.set(visitorName, visitor.getMetrics());
    }
    
    return metrics;
  }
}

/**
 * 过滤访问者 - 只访问满足条件的节点
 */
export class FilteringVisitor extends ASTVisitorBase {
  constructor(
    private filter: (node: ASTNode, context: VisitorContext) => boolean,
    private delegate: ASTVisitorBase,
    options?: VisitorOptions
  ) {
    super(options);
  }

  protected enterNode(node: ASTNode, context: VisitorContext): void | boolean {
    if (this.filter(node, context)) {
      return (this.delegate as any).enterNode(node, context);
    }
    return true; // 继续遍历子节点
  }

  protected leaveNode(node: ASTNode, context: VisitorContext): void {
    if (this.filter(node, context)) {
      (this.delegate as any).leaveNode(node, context);
    }
  }
}

/**
 * 收集器访问者 - 收集满足条件的节点
 */
export class CollectingVisitor extends ASTVisitorBase {
  private collected: ASTNode[] = [];

  constructor(
    private collector: (node: ASTNode, context: VisitorContext) => boolean,
    options?: VisitorOptions
  ) {
    super(options);
  }

  protected enterNode(node: ASTNode, context: VisitorContext): void | boolean {
    if (this.collector(node, context)) {
      this.collected.push(node);
    }
    return true;
  }

  protected leaveNode(node: ASTNode, context: VisitorContext): void {
    // 不需要处理
  }

  /**
   * 获取收集的节点
   */
  public getCollected(): ASTNode[] {
    return [...this.collected];
  }

  /**
   * 清空收集的节点
   */
  public clearCollected(): void {
    this.collected = [];
  }
}

/**
 * 查找访问者 - 查找第一个满足条件的节点
 */
export class FindingVisitor extends ASTVisitorBase {
  private found: ASTNode | null = null;

  constructor(
    private finder: (node: ASTNode, context: VisitorContext) => boolean,
    options?: VisitorOptions
  ) {
    super(options);
  }

  protected enterNode(node: ASTNode, context: VisitorContext): void | boolean {
    if (this.finder(node, context)) {
      this.found = node;
      return false; // 找到后停止遍历
    }
    return true;
  }

  protected leaveNode(node: ASTNode, context: VisitorContext): void {
    // 不需要处理
  }

  /**
   * 获取找到的节点
   */
  public getFound(): ASTNode | null {
    return this.found;
  }

  /**
   * 重置查找结果
   */
  public resetFound(): void {
    this.found = null;
  }
}