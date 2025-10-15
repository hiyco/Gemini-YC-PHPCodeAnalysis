import * as vscode from 'vscode';

export class YcPcaStatusBar {
    private statusBarItem: vscode.StatusBarItem;
    private analysisCount = 0;
    private securityIssueCount = 0;
    private performanceIssueCount = 0;
    private lastBenchmarkTime?: Date;

    constructor() {
        this.statusBarItem = vscode.window.createStatusBarItem(
            vscode.StatusBarAlignment.Right,
            100
        );
        this.statusBarItem.command = 'yc-pca.showStatusMenu';
        this.updateStatusBar();
        this.statusBarItem.show();
    }

    public updateAnalysisStats(securityCount: number, performanceCount: number): void {
        this.analysisCount++;
        this.securityIssueCount = securityCount;
        this.performanceIssueCount = performanceCount;
        this.updateStatusBar();
    }

    public updateBenchmarkStatus(isRunning: boolean, lastRun?: Date): void {
        if (isRunning) {
            this.statusBarItem.text = '$(sync~spin) YC-PCA: Running benchmarks...';
            this.statusBarItem.tooltip = 'Performance benchmarks are currently running';
        } else {
            this.lastBenchmarkTime = lastRun;
            this.updateStatusBar();
        }
    }

    public showBenchmarkResult(result: { totalBenchmarks: number, avgTime: number, regressionCount: number }): void {
        const regressionText = result.regressionCount > 0 
            ? ` (${result.regressionCount} regressions)`
            : '';
        
        vscode.window.showInformationMessage(
            `Benchmarks completed: ${result.totalBenchmarks} tests, ` +
            `avg ${(result.avgTime * 1000).toFixed(1)}ms${regressionText}`,
            'View Results',
            'Run Again'
        ).then(action => {
            if (action === 'View Results') {
                vscode.commands.executeCommand('yc-pca-benchmarks.focus');
            } else if (action === 'Run Again') {
                vscode.commands.executeCommand('yc-pca.runBenchmarks');
            }
        });
    }

    public showAnalysisProgress(message: string): void {
        this.statusBarItem.text = `$(sync~spin) ${message}`;
        this.statusBarItem.tooltip = 'YC-PCA analysis in progress...';
    }

    public hideProgress(): void {
        this.updateStatusBar();
    }

    private updateStatusBar(): void {
        let icon = '$(shield)';
        let text = 'YC-PCA';
        let tooltip = 'YC PHP Code Analysis';

        // Determine icon based on issue counts
        if (this.securityIssueCount > 0 || this.performanceIssueCount > 0) {
            icon = this.securityIssueCount > 0 ? '$(warning)' : '$(info)';
        }

        // Build status text
        const issueParts: string[] = [];
        if (this.securityIssueCount > 0) {
            issueParts.push(`${this.securityIssueCount} security`);
        }
        if (this.performanceIssueCount > 0) {
            issueParts.push(`${this.performanceIssueCount} performance`);
        }

        if (issueParts.length > 0) {
            text += `: ${issueParts.join(', ')} issues`;
        } else if (this.analysisCount > 0) {
            text += ': No issues';
        }

        this.statusBarItem.text = `${icon} ${text}`;

        // Build detailed tooltip
        const tooltipParts = [tooltip];
        if (this.analysisCount > 0) {
            tooltipParts.push(`Files analyzed: ${this.analysisCount}`);
        }
        if (this.securityIssueCount > 0) {
            tooltipParts.push(`Security issues: ${this.securityIssueCount}`);
        }
        if (this.performanceIssueCount > 0) {
            tooltipParts.push(`Performance issues: ${this.performanceIssueCount}`);
        }
        if (this.lastBenchmarkTime) {
            const timeAgo = this.getTimeAgo(this.lastBenchmarkTime);
            tooltipParts.push(`Last benchmark: ${timeAgo}`);
        }
        tooltipParts.push('Click for options');

        this.statusBarItem.tooltip = tooltipParts.join('\n');
    }

    private getTimeAgo(date: Date): string {
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / (1000 * 60));
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        return `${diffDays}d ago`;
    }

    public dispose(): void {
        this.statusBarItem.dispose();
    }

    public registerStatusMenuCommand(context: vscode.ExtensionContext): void {
        const command = vscode.commands.registerCommand('yc-pca.showStatusMenu', () => {
            this.showStatusMenu();
        });
        context.subscriptions.push(command);
    }

    private showStatusMenu(): void {
        const items: vscode.QuickPickItem[] = [
            {
                label: '$(file-code) Analyze Current File',
                description: 'Run analysis on the currently open PHP file',
                detail: 'yc-pca.analyzeFile'
            },
            {
                label: '$(folder) Analyze Workspace',
                description: 'Run analysis on all PHP files in the workspace',
                detail: 'yc-pca.analyzeWorkspace'
            },
            {
                label: '$(pulse) Run Benchmarks',
                description: 'Execute performance benchmarks',
                detail: 'yc-pca.runBenchmarks'
            },
            {
                label: '$(shield) Show Security Issues',
                description: 'Open the security issues panel',
                detail: 'yc-pca.showSecurityPanel'
            },
            {
                label: '$(dashboard) Show Performance Issues',
                description: 'Open the performance issues panel',
                detail: 'yc-pca-performance.focus'
            },
            {
                label: '$(graph) Show Benchmark Results',
                description: 'View benchmark results and history',
                detail: 'yc-pca-benchmarks.focus'
            },
            {
                label: '$(file-text) Generate Report',
                description: 'Generate a comprehensive analysis report',
                detail: 'yc-pca.generateReport'
            },
            {
                label: '$(eye) Toggle Highlighting',
                description: 'Enable/disable security issue highlighting',
                detail: 'yc-pca.toggleHighlighting'
            },
            {
                label: '$(clear-all) Clear Diagnostics',
                description: 'Clear all diagnostic messages',
                detail: 'yc-pca.clearDiagnostics'
            }
        ];

        vscode.window.showQuickPick(items, {
            placeHolder: 'Select YC-PCA action',
            matchOnDescription: true,
            matchOnDetail: true
        }).then(selection => {
            if (selection) {
                vscode.commands.executeCommand(selection.detail!);
            }
        });
    }
}