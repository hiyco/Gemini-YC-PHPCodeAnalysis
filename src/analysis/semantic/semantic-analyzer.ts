/**
 * PHP语义分析器
 * 
 * 实现深度语义分析：
 * - 作用域分析和变量追踪
 * - 类型推断系统
 * - 调用图构建
 * - 依赖关系分析
 * - 引用计数和生命周期
 */

import { ASTNode, PhpASTParser } from '../ast/php-ast-parser';
import { ASTVisitorBase, VisitorContext } from '../ast/ast-visitor-base';
import { 
  SemanticAnalysisResult, 
  ClassInfo, 
  FunctionInfo, 
  VariableInfo, 
  DependencyInfo,
  ReferenceInfo,
  TypeInference,
  LocationInfo
} from '../php-analysis-engine';

export interface SymbolTable {
  variables: Map<string, VariableSymbol>;
  functions: Map<string, FunctionSymbol>;
  classes: Map<string, ClassSymbol>;
  constants: Map<string, ConstantSymbol>;
  parent?: SymbolTable;
}

export interface VariableSymbol {
  name: string;
  type?: string;
  inferredType?: string;
  scope: Scope;
  definitions: LocationInfo[];
  usages: LocationInfo[];
  isParameter: boolean;
  isGlobal: boolean;
  isStatic: boolean;
  isReference: boolean;
  firstAssignment?: LocationInfo;
  lastUsage?: LocationInfo;
}

export interface FunctionSymbol {
  name: string;
  namespace?: string;
  returnType?: string;
  inferredReturnType?: string;
  parameters: ParameterSymbol[];
  location: LocationInfo;
  calls: LocationInfo[];
  complexity: number;
  isMethod: boolean;
  visibility?: 'public' | 'protected' | 'private';
  isStatic: boolean;
  isAbstract: boolean;
  isFinal: boolean;
}

export interface ClassSymbol {
  name: string;
  namespace?: string;
  extends?: string;
  implements: string[];
  location: LocationInfo;
  methods: Map<string, FunctionSymbol>;
  properties: Map<string, PropertySymbol>;
  constants: Map<string, ConstantSymbol>;
  usages: LocationInfo[];
  isAbstract: boolean;
  isFinal: boolean;
  isInterface: boolean;
  isTrait: boolean;
  traits: string[];
}

export interface PropertySymbol {
  name: string;
  type?: string;
  inferredType?: string;
  visibility: 'public' | 'protected' | 'private';
  isStatic: boolean;
  isReadonly: boolean;
  hasDefaultValue: boolean;
  location: LocationInfo;
  usages: LocationInfo[];
}

export interface ParameterSymbol {
  name: string;
  type?: string;
  defaultValue?: any;
  byReference: boolean;
  variadic: boolean;
  position: number;
}

export interface ConstantSymbol {
  name: string;
  type: string;
  value: any;
  location: LocationInfo;
  usages: LocationInfo[];
  isGlobal: boolean;
}

export interface Scope {
  type: 'global' | 'function' | 'method' | 'class' | 'block';
  name?: string;
  node: ASTNode;
  parent?: Scope;
  children: Scope[];
  variables: Set<string>;
  level: number;
}

export interface CallGraph {
  nodes: Map<string, CallGraphNode>;
  edges: CallGraphEdge[];
}

export interface CallGraphNode {
  id: string;
  name: string;
  type: 'function' | 'method';
  location: LocationInfo;
  callCount: number;
  complexity: number;
}

export interface CallGraphEdge {
  from: string;
  to: string;
  count: number;
  locations: LocationInfo[];
}

/**
 * 语义分析器主类
 */
export class SemanticAnalyzer extends ASTVisitorBase {
  private symbolTable: SymbolTable;
  private currentScope: Scope;
  private scopeStack: Scope[];
  private callGraph: CallGraph;
  private typeInferences: TypeInference[];
  private currentClass?: ClassSymbol;
  private currentFunction?: FunctionSymbol;
  
  constructor() {
    super({
      trackContext: true,
      enableMetrics: true
    });
    
    this.initializeAnalyzer();
  }

  /**
   * 初始化分析器
   */
  private initializeAnalyzer(): void {
    this.symbolTable = {
      variables: new Map(),
      functions: new Map(),
      classes: new Map(),
      constants: new Map()
    };
    
    this.callGraph = {
      nodes: new Map(),
      edges: []
    };
    
    this.typeInferences = [];
    this.scopeStack = [];
    
    // 创建全局作用域
    this.currentScope = {
      type: 'global',
      node: null as any,
      children: [],
      variables: new Set(),
      level: 0
    };
    
    this.scopeStack.push(this.currentScope);
  }

  /**
   * 执行语义分析
   */
  public analyze(ast: ASTNode, filePath?: string): SemanticAnalysisResult {
    this.reset();
    this.visit(ast, filePath);
    
    return this.buildAnalysisResult();
  }

  /**
   * 进入节点处理
   */
  protected enterNode(node: ASTNode, context: VisitorContext): void | boolean {
    switch (node.nodeType) {
      case 'Stmt_Class':
        this.handleClassDeclaration(node, context);
        break;
      case 'Stmt_Interface':
        this.handleInterfaceDeclaration(node, context);
        break;
      case 'Stmt_Trait':
        this.handleTraitDeclaration(node, context);
        break;
      case 'Stmt_Function':
        this.handleFunctionDeclaration(node, context);
        break;
      case 'Stmt_ClassMethod':
        this.handleMethodDeclaration(node, context);
        break;
      case 'Expr_Variable':
        this.handleVariableUsage(node, context);
        break;
      case 'Expr_Assign':
        this.handleAssignment(node, context);
        break;
      case 'Expr_FuncCall':
        this.handleFunctionCall(node, context);
        break;
      case 'Expr_MethodCall':
        this.handleMethodCall(node, context);
        break;
      case 'Expr_StaticCall':
        this.handleStaticCall(node, context);
        break;
      case 'Expr_New':
        this.handleObjectInstantiation(node, context);
        break;
      case 'Stmt_Use':
        this.handleUseStatement(node, context);
        break;
      case 'Stmt_Namespace':
        this.handleNamespaceDeclaration(node, context);
        break;
    }
    
    return true;
  }

  /**
   * 离开节点处理
   */
  protected leaveNode(node: ASTNode, context: VisitorContext): void {
    switch (node.nodeType) {
      case 'Stmt_Class':
      case 'Stmt_Interface':
      case 'Stmt_Trait':
        this.currentClass = undefined;
        this.exitScope();
        break;
      case 'Stmt_Function':
      case 'Stmt_ClassMethod':
        this.currentFunction = undefined;
        this.exitScope();
        break;
    }
  }

  /**
   * 处理类声明
   */
  private handleClassDeclaration(node: ASTNode, context: VisitorContext): void {
    const className = this.getNodeName(node);
    const namespace = this.getCurrentNamespace();
    const fullName = namespace ? `${namespace}\\${className}` : className;
    
    const classSymbol: ClassSymbol = {
      name: className,
      namespace,
      extends: this.getExtendsClass(node),
      implements: this.getImplementedInterfaces(node),
      location: this.getLocation(node, context),
      methods: new Map(),
      properties: new Map(),
      constants: new Map(),
      usages: [],
      isAbstract: this.hasModifier(node, 'abstract'),
      isFinal: this.hasModifier(node, 'final'),
      isInterface: false,
      isTrait: false,
      traits: this.getUsedTraits(node)
    };
    
    this.symbolTable.classes.set(fullName, classSymbol);
    this.currentClass = classSymbol;
    this.enterScope('class', className, node);
  }

  /**
   * 处理接口声明
   */
  private handleInterfaceDeclaration(node: ASTNode, context: VisitorContext): void {
    const interfaceName = this.getNodeName(node);
    const namespace = this.getCurrentNamespace();
    const fullName = namespace ? `${namespace}\\${interfaceName}` : interfaceName;
    
    const interfaceSymbol: ClassSymbol = {
      name: interfaceName,
      namespace,
      extends: this.getExtendsInterface(node),
      implements: [],
      location: this.getLocation(node, context),
      methods: new Map(),
      properties: new Map(),
      constants: new Map(),
      usages: [],
      isAbstract: false,
      isFinal: false,
      isInterface: true,
      isTrait: false,
      traits: []
    };
    
    this.symbolTable.classes.set(fullName, interfaceSymbol);
    this.currentClass = interfaceSymbol;
    this.enterScope('class', interfaceName, node);
  }

  /**
   * 处理trait声明
   */
  private handleTraitDeclaration(node: ASTNode, context: VisitorContext): void {
    const traitName = this.getNodeName(node);
    const namespace = this.getCurrentNamespace();
    const fullName = namespace ? `${namespace}\\${traitName}` : traitName;
    
    const traitSymbol: ClassSymbol = {
      name: traitName,
      namespace,
      extends: undefined,
      implements: [],
      location: this.getLocation(node, context),
      methods: new Map(),
      properties: new Map(),
      constants: new Map(),
      usages: [],
      isAbstract: false,
      isFinal: false,
      isInterface: false,
      isTrait: true,
      traits: []
    };
    
    this.symbolTable.classes.set(fullName, traitSymbol);
    this.currentClass = traitSymbol;
    this.enterScope('class', traitName, node);
  }

  /**
   * 处理函数声明
   */
  private handleFunctionDeclaration(node: ASTNode, context: VisitorContext): void {
    const functionName = this.getNodeName(node);
    const namespace = this.getCurrentNamespace();
    const fullName = namespace ? `${namespace}\\${functionName}` : functionName;
    
    const functionSymbol: FunctionSymbol = {
      name: functionName,
      namespace,
      returnType: this.getReturnType(node),
      parameters: this.getParameters(node),
      location: this.getLocation(node, context),
      calls: [],
      complexity: 1, // 基础复杂度
      isMethod: false,
      isStatic: false,
      isAbstract: false,
      isFinal: false
    };
    
    this.symbolTable.functions.set(fullName, functionSymbol);
    this.currentFunction = functionSymbol;
    
    // 添加到调用图
    this.callGraph.nodes.set(fullName, {
      id: fullName,
      name: functionName,
      type: 'function',
      location: functionSymbol.location,
      callCount: 0,
      complexity: functionSymbol.complexity
    });
    
    this.enterScope('function', functionName, node);
    
    // 处理参数
    for (const param of functionSymbol.parameters) {
      this.addVariableToScope(param.name, {
        name: param.name,
        type: param.type,
        scope: this.currentScope,
        definitions: [functionSymbol.location],
        usages: [],
        isParameter: true,
        isGlobal: false,
        isStatic: false,
        isReference: param.byReference
      });
    }
  }

  /**
   * 处理方法声明
   */
  private handleMethodDeclaration(node: ASTNode, context: VisitorContext): void {
    if (!this.currentClass) return;
    
    const methodName = this.getNodeName(node);
    const visibility = this.getVisibility(node);
    
    const methodSymbol: FunctionSymbol = {
      name: methodName,
      returnType: this.getReturnType(node),
      parameters: this.getParameters(node),
      location: this.getLocation(node, context),
      calls: [],
      complexity: 1,
      isMethod: true,
      visibility,
      isStatic: this.hasModifier(node, 'static'),
      isAbstract: this.hasModifier(node, 'abstract'),
      isFinal: this.hasModifier(node, 'final')
    };
    
    this.currentClass.methods.set(methodName, methodSymbol);
    this.currentFunction = methodSymbol;
    
    // 添加到调用图
    const fullName = `${this.currentClass.name}::${methodName}`;
    this.callGraph.nodes.set(fullName, {
      id: fullName,
      name: methodName,
      type: 'method',
      location: methodSymbol.location,
      callCount: 0,
      complexity: methodSymbol.complexity
    });
    
    this.enterScope('method', methodName, node);
    
    // 处理参数
    for (const param of methodSymbol.parameters) {
      this.addVariableToScope(param.name, {
        name: param.name,
        type: param.type,
        scope: this.currentScope,
        definitions: [methodSymbol.location],
        usages: [],
        isParameter: true,
        isGlobal: false,
        isStatic: false,
        isReference: param.byReference
      });
    }
  }

  /**
   * 处理变量使用
   */
  private handleVariableUsage(node: ASTNode, context: VisitorContext): void {
    const varName = this.getVariableName(node);
    const location = this.getLocation(node, context);
    
    // 查找变量符号
    let variable = this.findVariable(varName);
    
    if (!variable) {
      // 创建新变量（可能是隐式声明）
      variable = {
        name: varName,
        scope: this.currentScope,
        definitions: [],
        usages: [location],
        isParameter: false,
        isGlobal: this.currentScope.type === 'global',
        isStatic: false,
        isReference: false
      };
      
      this.addVariableToScope(varName, variable);
    } else {
      variable.usages.push(location);
      variable.lastUsage = location;
    }
    
    // 尝试类型推断
    this.performTypeInference(variable, node, context);
  }

  /**
   * 处理赋值表达式
   */
  private handleAssignment(node: ASTNode, context: VisitorContext): void {
    const leftNode = this.getAssignmentLeft(node);
    const rightNode = this.getAssignmentRight(node);
    
    if (leftNode && leftNode.nodeType === 'Expr_Variable') {
      const varName = this.getVariableName(leftNode);
      const location = this.getLocation(node, context);
      
      let variable = this.findVariable(varName);
      
      if (!variable) {
        variable = {
          name: varName,
          scope: this.currentScope,
          definitions: [location],
          usages: [],
          isParameter: false,
          isGlobal: this.currentScope.type === 'global',
          isStatic: false,
          isReference: false,
          firstAssignment: location
        };
        
        this.addVariableToScope(varName, variable);
      } else {
        variable.definitions.push(location);
        if (!variable.firstAssignment) {
          variable.firstAssignment = location;
        }
      }
      
      // 从右侧表达式推断类型
      if (rightNode) {
        const inferredType = this.inferTypeFromExpression(rightNode);
        if (inferredType) {
          variable.inferredType = inferredType;
          this.typeInferences.push({
            variable: varName,
            inferredType,
            confidence: 0.8,
            line: location.line
          });
        }
      }
    }
  }

  /**
   * 处理函数调用
   */
  private handleFunctionCall(node: ASTNode, context: VisitorContext): void {
    const functionName = this.getFunctionCallName(node);
    const location = this.getLocation(node, context);
    
    // 记录调用
    if (this.currentFunction) {
      this.currentFunction.calls.push(location);
      
      // 添加调用边
      const fromId = this.getCurrentFunctionId();
      const toId = functionName;
      
      this.addCallGraphEdge(fromId, toId, location);
    }
    
    // 更新被调用函数的统计
    const calledFunction = this.symbolTable.functions.get(functionName);
    if (calledFunction) {
      const callGraphNode = this.callGraph.nodes.get(functionName);
      if (callGraphNode) {
        callGraphNode.callCount++;
      }
    }
  }

  /**
   * 处理方法调用
   */
  private handleMethodCall(node: ASTNode, context: VisitorContext): void {
    const methodName = this.getMethodCallName(node);
    const objectNode = this.getMethodCallObject(node);
    const location = this.getLocation(node, context);
    
    if (this.currentFunction) {
      this.currentFunction.calls.push(location);
    }
    
    // 尝试解析对象类型
    const objectType = this.inferTypeFromExpression(objectNode);
    if (objectType) {
      const fullMethodName = `${objectType}::${methodName}`;
      
      if (this.currentFunction) {
        const fromId = this.getCurrentFunctionId();
        this.addCallGraphEdge(fromId, fullMethodName, location);
      }
      
      const callGraphNode = this.callGraph.nodes.get(fullMethodName);
      if (callGraphNode) {
        callGraphNode.callCount++;
      }
    }
  }

  /**
   * 类型推断
   */
  private performTypeInference(variable: VariableSymbol, node: ASTNode, context: VisitorContext): void {
    // 基于上下文进行类型推断
    const inferredType = this.inferTypeFromContext(variable, node, context);
    
    if (inferredType && inferredType !== variable.inferredType) {
      variable.inferredType = inferredType;
      
      this.typeInferences.push({
        variable: variable.name,
        inferredType,
        confidence: 0.6, // 基于上下文的推断置信度较低
        line: this.getLocation(node, context).line
      });
    }
  }

  /**
   * 从表达式推断类型
   */
  private inferTypeFromExpression(node: ASTNode): string | undefined {
    switch (node.nodeType) {
      case 'Scalar_String':
        return 'string';
      case 'Scalar_LNumber':
        return 'int';
      case 'Scalar_DNumber':
        return 'float';
      case 'Expr_Array':
        return 'array';
      case 'Expr_New':
        return this.getClassName(node);
      case 'Expr_FuncCall':
        return this.inferReturnTypeFromFunction(node);
      case 'Expr_MethodCall':
        return this.inferReturnTypeFromMethod(node);
      default:
        return undefined;
    }
  }

  /**
   * 从上下文推断类型
   */
  private inferTypeFromContext(variable: VariableSymbol, node: ASTNode, context: VisitorContext): string | undefined {
    // 检查是否在类型注释中
    const typeHint = this.findTypeHintForVariable(variable.name, context);
    if (typeHint) {
      return typeHint;
    }
    
    // 检查是否在特定的上下文中（如foreach循环）
    if (this.isInContext(['Stmt_Foreach'])) {
      const foreachNode = this.findAncestorByType('Stmt_Foreach');
      if (foreachNode) {
        return this.inferForeachVariableType(foreachNode, variable.name);
      }
    }
    
    return undefined;
  }

  // 辅助方法

  private enterScope(type: Scope['type'], name: string | undefined, node: ASTNode): void {
    const newScope: Scope = {
      type,
      name,
      node,
      parent: this.currentScope,
      children: [],
      variables: new Set(),
      level: this.currentScope.level + 1
    };
    
    this.currentScope.children.push(newScope);
    this.scopeStack.push(newScope);
    this.currentScope = newScope;
  }

  private exitScope(): void {
    if (this.scopeStack.length > 1) {
      this.scopeStack.pop();
      this.currentScope = this.scopeStack[this.scopeStack.length - 1];
    }
  }

  private findVariable(name: string): VariableSymbol | undefined {
    // 在当前作用域链中查找变量
    for (let i = this.scopeStack.length - 1; i >= 0; i--) {
      const scope = this.scopeStack[i];
      if (scope.variables.has(name)) {
        return this.symbolTable.variables.get(`${scope.type}:${scope.name}:${name}`) ||
               this.symbolTable.variables.get(name);
      }
    }
    
    return this.symbolTable.variables.get(name);
  }

  private addVariableToScope(name: string, variable: VariableSymbol): void {
    const key = this.currentScope.type === 'global' ? name : 
                `${this.currentScope.type}:${this.currentScope.name}:${name}`;
    
    this.symbolTable.variables.set(key, variable);
    this.currentScope.variables.add(name);
  }

  private getCurrentFunctionId(): string {
    if (this.currentFunction?.isMethod && this.currentClass) {
      return `${this.currentClass.name}::${this.currentFunction.name}`;
    } else if (this.currentFunction) {
      const namespace = this.currentFunction.namespace;
      return namespace ? `${namespace}\\${this.currentFunction.name}` : this.currentFunction.name;
    }
    return 'global';
  }

  private addCallGraphEdge(fromId: string, toId: string, location: LocationInfo): void {
    const existingEdge = this.callGraph.edges.find(edge => edge.from === fromId && edge.to === toId);
    
    if (existingEdge) {
      existingEdge.count++;
      existingEdge.locations.push(location);
    } else {
      this.callGraph.edges.push({
        from: fromId,
        to: toId,
        count: 1,
        locations: [location]
      });
    }
  }

  private getLocation(node: ASTNode, context: VisitorContext): LocationInfo {
    return {
      line: node.startLine,
      column: node.startColumn,
      filePath: context.filePath || 'unknown'
    };
  }

  /**
   * 构建分析结果
   */
  private buildAnalysisResult(): SemanticAnalysisResult {
    const classes: ClassInfo[] = Array.from(this.symbolTable.classes.values()).map(cls => ({
      name: cls.name,
      namespace: cls.namespace,
      extends: cls.extends,
      implements: cls.implements,
      abstract: cls.isAbstract,
      final: cls.isFinal,
      methods: Array.from(cls.methods.values()).map(method => ({
        name: method.name,
        visibility: method.visibility || 'public',
        static: method.isStatic,
        abstract: method.isAbstract,
        final: method.isFinal,
        returnType: method.returnType,
        parameters: method.parameters.map(param => ({
          name: param.name,
          type: param.type,
          defaultValue: param.defaultValue,
          byReference: param.byReference,
          variadic: param.variadic
        })),
        startLine: method.location.line,
        endLine: method.location.line, // TODO: 计算实际结束行
        complexity: method.complexity
      })),
      properties: Array.from(cls.properties.values()).map(prop => ({
        name: prop.name,
        visibility: prop.visibility,
        static: prop.isStatic,
        type: prop.type,
        startLine: prop.location.line
      })),
      constants: Array.from(cls.constants.values()).map(const_ => ({
        name: const_.name,
        value: const_.value,
        startLine: const_.location.line
      })),
      traits: cls.traits,
      startLine: cls.location.line,
      endLine: cls.location.line // TODO: 计算实际结束行
    }));

    const functions: FunctionInfo[] = Array.from(this.symbolTable.functions.values())
      .filter(func => !func.isMethod)
      .map(func => ({
        name: func.name,
        namespace: func.namespace,
        returnType: func.returnType,
        parameters: func.parameters.map(param => ({
          name: param.name,
          type: param.type,
          defaultValue: param.defaultValue,
          byReference: param.byReference,
          variadic: param.variadic
        })),
        startLine: func.location.line,
        endLine: func.location.line, // TODO: 计算实际结束行
        complexity: func.complexity
      }));

    const variables: VariableInfo[] = Array.from(this.symbolTable.variables.values()).map(variable => ({
      name: variable.name,
      type: variable.inferredType || variable.type,
      scope: variable.scope.type,
      firstAssignment: variable.firstAssignment?.line || 0,
      lastUsage: variable.lastUsage?.line || 0,
      usageCount: variable.usages.length
    }));

    // 构建依赖信息
    const dependencies: DependencyInfo[] = [];
    // TODO: 实现依赖关系分析

    // 构建引用信息
    const references: ReferenceInfo[] = [];
    // TODO: 实现引用关系分析

    return {
      classes,
      functions,
      variables,
      dependencies,
      references,
      typeInferences: this.typeInferences
    };
  }

  // 节点属性提取方法（需要根据实际AST结构实现）
  private getNodeName(node: ASTNode): string {
    return node.attributes.name || 'unknown';
  }

  private getCurrentNamespace(): string | undefined {
    // TODO: 实现命名空间追踪
    return undefined;
  }

  private getExtendsClass(node: ASTNode): string | undefined {
    // TODO: 实现extends类名提取
    return undefined;
  }

  private getImplementedInterfaces(node: ASTNode): string[] {
    // TODO: 实现implements接口提取
    return [];
  }

  private hasModifier(node: ASTNode, modifier: string): boolean {
    // TODO: 实现修饰符检查
    return false;
  }

  private getUsedTraits(node: ASTNode): string[] {
    // TODO: 实现trait使用提取
    return [];
  }

  private getExtendsInterface(node: ASTNode): string | undefined {
    // TODO: 实现接口继承提取
    return undefined;
  }

  private getReturnType(node: ASTNode): string | undefined {
    // TODO: 实现返回类型提取
    return undefined;
  }

  private getParameters(node: ASTNode): ParameterSymbol[] {
    // TODO: 实现参数列表提取
    return [];
  }

  private getVisibility(node: ASTNode): 'public' | 'protected' | 'private' {
    // TODO: 实现可见性提取
    return 'public';
  }

  private getVariableName(node: ASTNode): string {
    return node.attributes.name || '$unknown';
  }

  private getAssignmentLeft(node: ASTNode): ASTNode | undefined {
    // TODO: 实现赋值左侧提取
    return undefined;
  }

  private getAssignmentRight(node: ASTNode): ASTNode | undefined {
    // TODO: 实现赋值右侧提取
    return undefined;
  }

  private getFunctionCallName(node: ASTNode): string {
    // TODO: 实现函数调用名称提取
    return 'unknown';
  }

  private getMethodCallName(node: ASTNode): string {
    // TODO: 实现方法调用名称提取
    return 'unknown';
  }

  private getMethodCallObject(node: ASTNode): ASTNode {
    // TODO: 实现方法调用对象提取
    return node;
  }

  private getClassName(node: ASTNode): string | undefined {
    // TODO: 实现类名提取
    return undefined;
  }

  private inferReturnTypeFromFunction(node: ASTNode): string | undefined {
    // TODO: 实现函数返回类型推断
    return undefined;
  }

  private inferReturnTypeFromMethod(node: ASTNode): string | undefined {
    // TODO: 实现方法返回类型推断
    return undefined;
  }

  private findTypeHintForVariable(varName: string, context: VisitorContext): string | undefined {
    // TODO: 实现类型提示查找
    return undefined;
  }

  private inferForeachVariableType(foreachNode: ASTNode, varName: string): string | undefined {
    // TODO: 实现foreach变量类型推断
    return undefined;
  }
}