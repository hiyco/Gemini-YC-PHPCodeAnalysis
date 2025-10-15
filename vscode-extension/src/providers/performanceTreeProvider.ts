import * as vscode from 'vscode';

export class PerformanceTreeProvider implements vscode.TreeDataProvider<PerformanceItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<PerformanceItem | undefined | null | void> = new vscode.EventEmitter<PerformanceItem | undefined | null | void>();
    readonly onDidChangeTreeData: vscode.Event<PerformanceItem | undefined | null | void> = this._onDidChangeTreeData.event;

    private performanceIssues: PerformanceIssue[] = [];

    constructor() {
        this.loadPerformanceIssues();
    }

    refresh(): void {
        this.loadPerformanceIssues();
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: PerformanceItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: PerformanceItem): Thenable<PerformanceItem[]> {
        if (!element) {
            // Root level - show categories
            return Promise.resolve(this.getPerformanceCategories());
        } else if (element.contextValue === 'performanceCategory') {
            // Category level - show issues in this category
            return Promise.resolve(this.getIssuesForCategory(element.label as string));
        }

        return Promise.resolve([]);
    }

    private getPerformanceCategories(): PerformanceItem[] {
        const categories = new Map<string, { count: number, maxImpact: string }>();
        
        this.performanceIssues.forEach(issue => {
            const category = issue.category;
            if (!categories.has(category)) {
                categories.set(category, { count: 0, maxImpact: issue.impact });
            }
            
            const current = categories.get(category)!;
            current.count++;
            
            if (this.compareImpact(issue.impact, current.maxImpact) > 0) {
                current.maxImpact = issue.impact;
            }
        });

        const items: PerformanceItem[] = [];
        categories.forEach((stats, category) => {
            const iconPath = this.getImpactIcon(stats.maxImpact);
            const item = new PerformanceItem(
                `${category} (${stats.count})`,
                vscode.TreeItemCollapsibleState.Collapsed,
                'performanceCategory',
                iconPath
            );
            item.tooltip = `${stats.count} performance issues found`;
            items.push(item);
        });

        return items.sort((a, b) => {
            const aCount = parseInt((a.label as string).match(/\((\d+)\)$/)?.[1] || '0');
            const bCount = parseInt((b.label as string).match(/\((\d+)\)$/)?.[1] || '0');
            return bCount - aCount;
        });
    }

    private getIssuesForCategory(category: string): PerformanceItem[] {
        const categoryName = category.split(' (')[0];
        const filteredIssues = this.performanceIssues.filter(issue => 
            issue.category === categoryName
        );

        return filteredIssues.map(issue => {
            const iconPath = this.getImpactIcon(issue.impact);
            const item = new PerformanceItem(
                issue.title,
                vscode.TreeItemCollapsibleState.None,
                'performanceIssue',
                iconPath
            );
            
            item.tooltip = `${issue.impact.toUpperCase()}: ${issue.description}`;
            item.description = `Line ${issue.line} | ${issue.metric}`;
            
            // Make item clickable to go to file location
            item.command = {
                command: 'vscode.open',
                title: 'Go to issue',
                arguments: [
                    vscode.Uri.file(issue.filePath),
                    {
                        selection: new vscode.Range(
                            issue.line - 1, issue.column - 1,
                            issue.line - 1, issue.column - 1
                        )
                    }
                ]
            };

            return item;
        }).sort((a, b) => {
            // Sort by impact (critical > high > medium > low)
            const aIssue = filteredIssues.find(i => i.title === a.label)!;
            const bIssue = filteredIssues.find(i => i.title === b.label)!;
            return this.compareImpact(bIssue.impact, aIssue.impact);
        });
    }

    private loadPerformanceIssues(): void {
        // Clear existing issues
        this.performanceIssues = [];
        
        // Get performance issues from VS Code diagnostics
        const diagnostics = vscode.languages.getDiagnostics();
        diagnostics.forEach(([uri, fileDiagnostics]) => {
            fileDiagnostics.forEach(diag => {
                if (diag.source === 'YC-PCA' && diag.code && 
                    typeof diag.code === 'string' && diag.code.startsWith('PERF')) {
                    
                    this.performanceIssues.push({
                        filePath: uri.fsPath,
                        line: diag.range.start.line + 1,
                        column: diag.range.start.character + 1,
                        title: diag.message,
                        description: diag.message,
                        impact: this.mapVSCodeSeverityToImpact(diag.severity),
                        category: this.extractPerformanceCategory(diag.code),
                        metric: this.extractMetric(diag.code),
                        ruleId: diag.code
                    });
                }
            });
        });
    }

    private extractPerformanceCategory(ruleId: string): string {
        // Extract category from rule ID like "PERF_ALGORITHM_INEFFICIENT_LOOP"
        if (ruleId.includes('ALGORITHM')) return 'Algorithm Complexity';
        if (ruleId.includes('MEMORY')) return 'Memory Usage';
        if (ruleId.includes('DATABASE') || ruleId.includes('QUERY')) return 'Database Performance';
        if (ruleId.includes('NETWORK') || ruleId.includes('HTTP')) return 'Network Operations';
        if (ruleId.includes('FILE') || ruleId.includes('IO')) return 'File I/O';
        if (ruleId.includes('CACHE')) return 'Caching Issues';
        return 'General Performance';
    }

    private extractMetric(ruleId: string): string {
        // Extract performance metric info from rule ID
        if (ruleId.includes('O_N_SQUARED')) return 'O(n²) complexity';
        if (ruleId.includes('MEMORY_LEAK')) return 'Memory leak';
        if (ruleId.includes('N_PLUS_ONE')) return 'N+1 queries';
        if (ruleId.includes('BLOCKING')) return 'Blocking operation';
        if (ruleId.includes('INEFFICIENT')) return 'Inefficient operation';
        return 'Performance impact';
    }

    private mapVSCodeSeverityToImpact(severity: vscode.DiagnosticSeverity): string {
        switch (severity) {
            case vscode.DiagnosticSeverity.Error:
                return 'critical';
            case vscode.DiagnosticSeverity.Warning:
                return 'high';
            case vscode.DiagnosticSeverity.Information:
                return 'medium';
            case vscode.DiagnosticSeverity.Hint:
                return 'low';
            default:
                return 'low';
        }
    }

    private compareImpact(a: string, b: string): number {
        const impactOrder = ['critical', 'high', 'medium', 'low'];
        return impactOrder.indexOf(a) - impactOrder.indexOf(b);
    }

    private getImpactIcon(impact: string): vscode.ThemeIcon {
        switch (impact.toLowerCase()) {
            case 'critical':
                return new vscode.ThemeIcon('flame', new vscode.ThemeColor('errorForeground'));
            case 'high':
                return new vscode.ThemeIcon('warning', new vscode.ThemeColor('editorWarning.foreground'));
            case 'medium':
                return new vscode.ThemeIcon('clock', new vscode.ThemeColor('editorInfo.foreground'));
            case 'low':
                return new vscode.ThemeIcon('lightbulb', new vscode.ThemeColor('editorHint.foreground'));
            default:
                return new vscode.ThemeIcon('pulse');
        }
    }

    public getPerformanceStats(): { [category: string]: number } {
        const stats: { [category: string]: number } = {};
        
        this.performanceIssues.forEach(issue => {
            stats[issue.category] = (stats[issue.category] || 0) + 1;
        });

        return stats;
    }

    public getCriticalIssues(): PerformanceIssue[] {
        return this.performanceIssues.filter(issue => issue.impact === 'critical');
    }
}

class PerformanceItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly contextValue: string,
        public readonly iconPath?: vscode.ThemeIcon
    ) {
        super(label, collapsibleState);
    }
}

interface PerformanceIssue {
    filePath: string;
    line: number;
    column: number;
    title: string;
    description: string;
    impact: string;
    category: string;
    metric: string;
    ruleId: string;
}