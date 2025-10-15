import * as vscode from 'vscode';

export class SecurityTreeProvider implements vscode.TreeDataProvider<SecurityItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<SecurityItem | undefined | null | void> = new vscode.EventEmitter<SecurityItem | undefined | null | void>();
    readonly onDidChangeTreeData: vscode.Event<SecurityItem | undefined | null | void> = this._onDidChangeTreeData.event;

    private securityIssues: SecurityIssue[] = [];

    constructor() {
        this.loadSecurityIssues();
    }

    refresh(): void {
        this.loadSecurityIssues();
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: SecurityItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: SecurityItem): Thenable<SecurityItem[]> {
        if (!element) {
            // Root level - show categories
            return Promise.resolve(this.getSecurityCategories());
        } else if (element.contextValue === 'securityCategory') {
            // Category level - show issues in this category
            return Promise.resolve(this.getIssuesForCategory(element.label as string));
        }

        return Promise.resolve([]);
    }

    private getSecurityCategories(): SecurityItem[] {
        const categories = new Map<string, { count: number, maxSeverity: string }>();
        
        this.securityIssues.forEach(issue => {
            const category = this.mapOwaspCategory(issue.owaspCategory);
            if (!categories.has(category)) {
                categories.set(category, { count: 0, maxSeverity: issue.severity });
            }
            
            const current = categories.get(category)!;
            current.count++;
            
            if (this.compareSeverity(issue.severity, current.maxSeverity) > 0) {
                current.maxSeverity = issue.severity;
            }
        });

        const items: SecurityItem[] = [];
        categories.forEach((stats, category) => {
            const iconPath = this.getSeverityIcon(stats.maxSeverity);
            const item = new SecurityItem(
                `${category} (${stats.count})`,
                vscode.TreeItemCollapsibleState.Collapsed,
                'securityCategory',
                iconPath
            );
            item.tooltip = `${stats.count} security issues found`;
            items.push(item);
        });

        return items.sort((a, b) => {
            const aCount = parseInt((a.label as string).match(/\((\d+)\)$/)?.[1] || '0');
            const bCount = parseInt((b.label as string).match(/\((\d+)\)$/)?.[1] || '0');
            return bCount - aCount;
        });
    }

    private getIssuesForCategory(category: string): SecurityItem[] {
        const categoryName = category.split(' (')[0];
        const filteredIssues = this.securityIssues.filter(issue => 
            this.mapOwaspCategory(issue.owaspCategory) === categoryName
        );

        return filteredIssues.map(issue => {
            const iconPath = this.getSeverityIcon(issue.severity);
            const item = new SecurityItem(
                issue.title,
                vscode.TreeItemCollapsibleState.None,
                'securityIssue',
                iconPath
            );
            
            item.tooltip = `${issue.severity.toUpperCase()}: ${issue.description}`;
            item.description = `Line ${issue.line}`;
            
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
            // Sort by severity (critical > high > medium > low > info)
            const aIssue = filteredIssues.find(i => i.title === a.label)!;
            const bIssue = filteredIssues.find(i => i.title === b.label)!;
            return this.compareSeverity(bIssue.severity, aIssue.severity);
        });
    }

    private loadSecurityIssues(): void {
        // In a real implementation, this would load from the diagnostics provider
        // For now, we'll simulate some data
        this.securityIssues = [];
        
        // Get security issues from VS Code diagnostics
        const diagnostics = vscode.languages.getDiagnostics();
        diagnostics.forEach(([uri, fileDiagnostics]) => {
            fileDiagnostics.forEach(diag => {
                if (diag.source === 'YC-PCA' && diag.code && 
                    typeof diag.code === 'string' && diag.code.startsWith('SEC')) {
                    
                    this.securityIssues.push({
                        filePath: uri.fsPath,
                        line: diag.range.start.line + 1,
                        column: diag.range.start.character + 1,
                        title: diag.message,
                        description: diag.message,
                        severity: this.mapVSCodeSeverity(diag.severity),
                        owaspCategory: this.extractOwaspCategory(diag.code),
                        ruleId: diag.code
                    });
                }
            });
        });
    }

    private mapOwaspCategory(owaspId: string): string {
        const owaspMap: { [key: string]: string } = {
            'A01': 'Broken Access Control',
            'A02': 'Cryptographic Failures',
            'A03': 'Injection',
            'A04': 'Insecure Design',
            'A05': 'Security Misconfiguration',
            'A06': 'Vulnerable Components',
            'A07': 'Authentication Failures',
            'A08': 'Software Integrity Failures',
            'A09': 'Logging Failures',
            'A10': 'Server-Side Request Forgery'
        };
        
        return owaspMap[owaspId] || 'Other Security Issues';
    }

    private extractOwaspCategory(ruleId: string): string {
        // Extract OWASP category from rule ID like "SEC_A03_SQL_INJECTION"
        const match = ruleId.match(/SEC_([A-Z]\d+)/);
        return match ? match[1] : 'OTHER';
    }

    private mapVSCodeSeverity(severity: vscode.DiagnosticSeverity): string {
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
                return 'info';
        }
    }

    private compareSeverity(a: string, b: string): number {
        const severityOrder = ['critical', 'high', 'medium', 'low', 'info'];
        return severityOrder.indexOf(a) - severityOrder.indexOf(b);
    }

    private getSeverityIcon(severity: string): vscode.ThemeIcon {
        switch (severity.toLowerCase()) {
            case 'critical':
                return new vscode.ThemeIcon('error', new vscode.ThemeColor('errorForeground'));
            case 'high':
                return new vscode.ThemeIcon('warning', new vscode.ThemeColor('editorWarning.foreground'));
            case 'medium':
                return new vscode.ThemeIcon('info', new vscode.ThemeColor('editorInfo.foreground'));
            case 'low':
                return new vscode.ThemeIcon('lightbulb', new vscode.ThemeColor('editorHint.foreground'));
            default:
                return new vscode.ThemeIcon('circle-outline');
        }
    }
}

class SecurityItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly contextValue: string,
        public readonly iconPath?: vscode.ThemeIcon
    ) {
        super(label, collapsibleState);
    }
}

interface SecurityIssue {
    filePath: string;
    line: number;
    column: number;
    title: string;
    description: string;
    severity: string;
    owaspCategory: string;
    ruleId: string;
}