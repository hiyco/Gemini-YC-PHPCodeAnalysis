import * as vscode from 'vscode';

export class BenchmarkTreeProvider implements vscode.TreeDataProvider<BenchmarkItem> {
    private _onDidChangeTreeData: vscode.EventEmitter<BenchmarkItem | undefined | null | void> = new vscode.EventEmitter<BenchmarkItem | undefined | null | void>();
    readonly onDidChangeTreeData: vscode.Event<BenchmarkItem | undefined | null | void> = this._onDidChangeTreeData.event;

    private benchmarkResults: BenchmarkResult[] = [];
    private isRunning = false;

    constructor() {
        this.loadBenchmarkResults();
    }

    refresh(): void {
        this.loadBenchmarkResults();
        this._onDidChangeTreeData.fire();
    }

    getTreeItem(element: BenchmarkItem): vscode.TreeItem {
        return element;
    }

    getChildren(element?: BenchmarkItem): Thenable<BenchmarkItem[]> {
        if (!element) {
            // Root level - show benchmark suites and controls
            return Promise.resolve(this.getRootItems());
        } else if (element.contextValue === 'benchmarkSuite') {
            // Suite level - show individual benchmarks
            return Promise.resolve(this.getBenchmarksForSuite(element.label as string));
        }

        return Promise.resolve([]);
    }

    private getRootItems(): BenchmarkItem[] {
        const items: BenchmarkItem[] = [];

        // Add run benchmarks control
        const runItem = new BenchmarkItem(
            this.isRunning ? 'Running Benchmarks...' : 'Run All Benchmarks',
            vscode.TreeItemCollapsibleState.None,
            'runBenchmarks',
            this.isRunning ? new vscode.ThemeIcon('sync~spin') : new vscode.ThemeIcon('play')
        );
        runItem.tooltip = 'Execute performance benchmarks';
        runItem.command = this.isRunning ? undefined : {
            command: 'yc-pca.runBenchmarks',
            title: 'Run Benchmarks'
        };
        items.push(runItem);

        // Add separator
        if (this.benchmarkResults.length > 0) {
            items.push(new BenchmarkItem(
                '──────────────',
                vscode.TreeItemCollapsibleState.None,
                'separator'
            ));
        }

        // Group benchmarks by suite
        const suites = new Map<string, BenchmarkResult[]>();
        this.benchmarkResults.forEach(result => {
            if (!suites.has(result.suite)) {
                suites.set(result.suite, []);
            }
            suites.get(result.suite)!.push(result);
        });

        // Add suite items
        suites.forEach((results, suiteName) => {
            const avgTime = results.reduce((sum, r) => sum + r.averageTime, 0) / results.length;
            const avgMemory = results.reduce((sum, r) => sum + r.averageMemory, 0) / results.length;
            
            const statusIcon = this.getSuiteStatusIcon(results);
            const item = new BenchmarkItem(
                suiteName,
                vscode.TreeItemCollapsibleState.Expanded,
                'benchmarkSuite',
                statusIcon
            );
            
            item.description = `${results.length} tests`;
            item.tooltip = `Suite: ${suiteName}\nAverage Time: ${(avgTime * 1000).toFixed(2)}ms\nAverage Memory: ${(avgMemory / 1024 / 1024).toFixed(2)}MB`;
            
            items.push(item);
        });

        return items;
    }

    private getBenchmarksForSuite(suiteName: string): BenchmarkItem[] {
        const suiteResults = this.benchmarkResults.filter(r => r.suite === suiteName);
        
        return suiteResults.map(result => {
            const statusIcon = this.getBenchmarkStatusIcon(result);
            const item = new BenchmarkItem(
                result.name,
                vscode.TreeItemCollapsibleState.None,
                'benchmark',
                statusIcon
            );
            
            item.description = `${(result.averageTime * 1000).toFixed(2)}ms`;
            item.tooltip = this.createBenchmarkTooltip(result);
            
            return item;
        }).sort((a, b) => {
            const aResult = suiteResults.find(r => r.name === a.label)!;
            const bResult = suiteResults.find(r => r.name === b.label)!;
            return bResult.averageTime - aResult.averageTime; // Sort by time desc
        });
    }

    private createBenchmarkTooltip(result: BenchmarkResult): string {
        const lines = [
            `Benchmark: ${result.name}`,
            `Suite: ${result.suite}`,
            `──────────────────────`,
            `Average Time: ${(result.averageTime * 1000).toFixed(2)}ms`,
            `Min Time: ${(result.minTime * 1000).toFixed(2)}ms`,
            `Max Time: ${(result.maxTime * 1000).toFixed(2)}ms`,
            `Standard Deviation: ${(result.standardDeviation * 1000).toFixed(2)}ms`,
            `Average Memory: ${(result.averageMemory / 1024 / 1024).toFixed(2)}MB`,
            `Iterations: ${result.iterations}`,
            `Success Rate: ${result.successRate.toFixed(1)}%`
        ];

        if (result.regressionInfo) {
            lines.push('──────────────────────');
            lines.push('Regression Analysis:');
            if (result.regressionInfo.timeRegression) {
                lines.push(`Time Regression: ${result.regressionInfo.timeRegressionPercent.toFixed(1)}%`);
            }
            if (result.regressionInfo.memoryRegression) {
                lines.push(`Memory Regression: ${result.regressionInfo.memoryRegressionPercent.toFixed(1)}%`);
            }
        }

        return lines.join('\n');
    }

    private getSuiteStatusIcon(results: BenchmarkResult[]): vscode.ThemeIcon {
        const hasRegressions = results.some(r => 
            r.regressionInfo?.timeRegression || r.regressionInfo?.memoryRegression
        );
        const hasFailures = results.some(r => r.successRate < 100);

        if (hasFailures) {
            return new vscode.ThemeIcon('error', new vscode.ThemeColor('errorForeground'));
        } else if (hasRegressions) {
            return new vscode.ThemeIcon('warning', new vscode.ThemeColor('editorWarning.foreground'));
        } else {
            return new vscode.ThemeIcon('check', new vscode.ThemeColor('testing.iconPassed'));
        }
    }

    private getBenchmarkStatusIcon(result: BenchmarkResult): vscode.ThemeIcon {
        if (result.successRate < 100) {
            return new vscode.ThemeIcon('error', new vscode.ThemeColor('errorForeground'));
        } else if (result.regressionInfo?.timeRegression || result.regressionInfo?.memoryRegression) {
            return new vscode.ThemeIcon('warning', new vscode.ThemeColor('editorWarning.foreground'));
        } else {
            return new vscode.ThemeIcon('check', new vscode.ThemeColor('testing.iconPassed'));
        }
    }

    private loadBenchmarkResults(): void {
        // In a real implementation, this would load from saved benchmark results
        // For now, we'll create some sample data
        this.benchmarkResults = [
            {
                name: 'AST Parsing Performance',
                suite: 'Parsing Performance',
                averageTime: 0.025,
                minTime: 0.020,
                maxTime: 0.035,
                standardDeviation: 0.003,
                averageMemory: 15 * 1024 * 1024,
                iterations: 10,
                successRate: 100,
                timestamp: new Date(),
                regressionInfo: {
                    timeRegression: false,
                    memoryRegression: false,
                    timeRegressionPercent: 0,
                    memoryRegressionPercent: 0
                }
            },
            {
                name: 'Security Analysis Performance',
                suite: 'Analysis Performance',
                averageTime: 0.150,
                minTime: 0.120,
                maxTime: 0.200,
                standardDeviation: 0.015,
                averageMemory: 45 * 1024 * 1024,
                iterations: 5,
                successRate: 100,
                timestamp: new Date(),
                regressionInfo: {
                    timeRegression: true,
                    memoryRegression: false,
                    timeRegressionPercent: 12.5,
                    memoryRegressionPercent: 0
                }
            },
            {
                name: 'SQL Injection Detection',
                suite: 'Security Analysis',
                averageTime: 0.080,
                minTime: 0.065,
                maxTime: 0.095,
                standardDeviation: 0.008,
                averageMemory: 25 * 1024 * 1024,
                iterations: 10,
                successRate: 100,
                timestamp: new Date()
            }
        ];
    }

    public setRunning(running: boolean): void {
        this.isRunning = running;
        this.refresh();
    }

    public updateBenchmarkResults(newResults: BenchmarkResult[]): void {
        this.benchmarkResults = newResults;
        this.refresh();
    }

    public getBenchmarkSummary(): { totalBenchmarks: number, avgTime: number, regressionCount: number } {
        const totalBenchmarks = this.benchmarkResults.length;
        const avgTime = this.benchmarkResults.reduce((sum, r) => sum + r.averageTime, 0) / totalBenchmarks;
        const regressionCount = this.benchmarkResults.filter(r => 
            r.regressionInfo?.timeRegression || r.regressionInfo?.memoryRegression
        ).length;

        return { totalBenchmarks, avgTime, regressionCount };
    }
}

class BenchmarkItem extends vscode.TreeItem {
    constructor(
        public readonly label: string,
        public readonly collapsibleState: vscode.TreeItemCollapsibleState,
        public readonly contextValue: string,
        public readonly iconPath?: vscode.ThemeIcon
    ) {
        super(label, collapsibleState);
    }
}

interface BenchmarkResult {
    name: string;
    suite: string;
    averageTime: number; // seconds
    minTime: number;
    maxTime: number;
    standardDeviation: number;
    averageMemory: number; // bytes
    iterations: number;
    successRate: number; // percentage
    timestamp: Date;
    regressionInfo?: {
        timeRegression: boolean;
        memoryRegression: boolean;
        timeRegressionPercent: number;
        memoryRegressionPercent: number;
    };
}