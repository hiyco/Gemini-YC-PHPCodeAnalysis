import * as vscode from 'vscode';
import { LanguageClient, LanguageClientOptions, ServerOptions, TransportKind } from 'vscode-languageclient/node';
import { DiagnosticsProvider } from './diagnosticsProvider';
import { SecurityTreeProvider } from './providers/securityTreeProvider';
import { PerformanceTreeProvider } from './providers/performanceTreeProvider';
import { BenchmarkTreeProvider } from './providers/benchmarkTreeProvider';
import { YcPcaHoverProvider } from './providers/hoverProvider';
import { SecurityDecorationProvider } from './providers/decorationProvider';
import { YcPcaStatusBar } from './statusBar';

let client: LanguageClient | undefined;
let diagnosticsProvider: DiagnosticsProvider;
let decorationProvider: SecurityDecorationProvider;
let statusBar: YcPcaStatusBar;

export function activate(context: vscode.ExtensionContext) {
    console.log('YC-PCA extension is now active!');
    vscode.window.showInformationMessage('YC-PCA Extension Activated!');

    // Initialize providers
    diagnosticsProvider = new DiagnosticsProvider();
    decorationProvider = new SecurityDecorationProvider();
    statusBar = new YcPcaStatusBar();
    
    // Register tree data providers
    const securityProvider = new SecurityTreeProvider();
    const performanceProvider = new PerformanceTreeProvider();
    const benchmarkProvider = new BenchmarkTreeProvider();
    
    vscode.window.registerTreeDataProvider('yc-pca-security', securityProvider);
    vscode.window.registerTreeDataProvider('yc-pca-performance', performanceProvider);
    vscode.window.registerTreeDataProvider('yc-pca-benchmarks', benchmarkProvider);
    
    // Register hover provider for PHP files
    const hoverProvider = new YcPcaHoverProvider();
    context.subscriptions.push(
        vscode.languages.registerHoverProvider('php', hoverProvider)
    );

    // Register commands
    const commands = [
        vscode.commands.registerCommand('yc-pca.analyzeFile', async () => {
            try {
                await analyzeCurrentFile();
            } catch (error) {
                vscode.window.showErrorMessage(`Analyze file failed: ${error}`);
            }
        }),
        vscode.commands.registerCommand('yc-pca.analyzeWorkspace', async () => {
            try {
                await analyzeWorkspace();
            } catch (error) {
                vscode.window.showErrorMessage(`Analyze workspace failed: ${error}`);
            }
        }),
        vscode.commands.registerCommand('yc-pca.runBenchmarks', async () => {
            try {
                await runBenchmarks();
            } catch (error) {
                vscode.window.showErrorMessage(`Run benchmarks failed: ${error}`);
            }
        }),
        vscode.commands.registerCommand('yc-pca.generateReport', async () => {
            try {
                await generateReport();
            } catch (error) {
                vscode.window.showErrorMessage(`Generate report failed: ${error}`);
            }
        }),
        vscode.commands.registerCommand('yc-pca.clearDiagnostics', () => clearDiagnostics()),
        vscode.commands.registerCommand('yc-pca.showSecurityPanel', () => showSecurityPanel()),
        vscode.commands.registerCommand('yc-pca.toggleHighlighting', () => toggleSecurityHighlighting()),
        vscode.commands.registerCommand('yc-pca.refreshDecorations', () => {
            console.log('YC-PCA: refreshDecorations command executed');
            vscode.window.showInformationMessage('Refreshing security decorations...');
            if (decorationProvider) {
                decorationProvider.refreshAllDecorations();
                vscode.window.showInformationMessage('Security decorations refreshed successfully!');
            } else {
                console.error('YC-PCA: Decoration provider not initialized');
                vscode.window.showErrorMessage('Decoration provider not initialized');
            }
        }),
        vscode.commands.registerCommand('yc-pca-security.refresh', () => securityProvider.refresh()),
        vscode.commands.registerCommand('yc-pca-performance.refresh', () => performanceProvider.refresh()),
        vscode.commands.registerCommand('yc-pca-benchmarks.refresh', () => benchmarkProvider.refresh())
    ];

    context.subscriptions.push(...commands);
    console.log(`YC-PCA: Registered ${commands.length} commands`);
    vscode.window.showInformationMessage(`YC-PCA: ${commands.length} commands registered`);
    
    // Register status bar menu command
    statusBar.registerStatusMenuCommand(context);
    
    // Try to start language server client (non-blocking)
    try {
        startLanguageClient(context);
    } catch (error) {
        console.warn('YC-PCA: Language server failed to start:', error);
        vscode.window.showWarningMessage('YC-PCA: Language server unavailable. Basic features will work.');
    }

    // Register file system watchers
    const watcher = vscode.workspace.createFileSystemWatcher('**/*.php');
    watcher.onDidChange(uri => {
        if (isAnalysisEnabled()) {
            analyzeFile(uri);
        }
    });
    watcher.onDidCreate(uri => {
        if (isAnalysisEnabled()) {
            analyzeFile(uri);
        }
    });
    context.subscriptions.push(watcher);
}

export function deactivate(): Thenable<void> | undefined {
    // Cleanup providers
    if (decorationProvider) {
        decorationProvider.dispose();
    }
    if (diagnosticsProvider) {
        diagnosticsProvider.dispose();
    }
    if (statusBar) {
        statusBar.dispose();
    }
    
    if (!client) {
        return undefined;
    }
    return client.stop();
}

function startLanguageClient(_context: vscode.ExtensionContext) {
    try {
        const config = vscode.workspace.getConfiguration('yc-pca');
        const executablePath = config.get<string>('executablePath', '');
        
        // Try to find YC-PCA executable
        const pcaPath = findPcaExecutable(executablePath);
        if (!pcaPath) {
            console.log('YC-PCA: Executable not found, language server disabled');
            vscode.window.showInformationMessage('YC-PCA: Language server disabled (executable not found). Commands still available.');
            return;
        }

    // Language server options
    const serverOptions: ServerOptions = {
        run: { command: pcaPath, args: ['language-server'], transport: TransportKind.stdio },
        debug: { command: pcaPath, args: ['language-server', '--debug'], transport: TransportKind.stdio }
    };

    // Client options
    const clientOptions: LanguageClientOptions = {
        documentSelector: [{ scheme: 'file', language: 'php' }],
        synchronize: {
            fileEvents: vscode.workspace.createFileSystemWatcher('**/*.php')
        },
        outputChannel: vscode.window.createOutputChannel('YC-PCA Language Server'),
        diagnosticCollectionName: 'yc-pca'
    };

    // Create language client
    client = new LanguageClient(
        'yc-pca-language-server',
        'YC-PCA Language Server',
        serverOptions,
        clientOptions
    );

    // Start the client
    client.start().then(() => {
        console.log('YC-PCA Language Server started');
        
        // Listen for custom notifications from language server
        client!.onNotification('yc-pca/analysisComplete', (params: any) => {
            handleAnalysisComplete(params);
        });
        
        client!.onNotification('yc-pca/securityIssue', (params: any) => {
            handleSecurityIssue(params);
        });
        
        client!.onNotification('yc-pca/performanceIssue', (params: any) => {
            handlePerformanceIssue(params);
        });
    }).catch(error => {
        console.error('Failed to start YC-PCA Language Server:', error);
        vscode.window.showInformationMessage('YC-PCA: Language server unavailable. Commands still work.');
    });
    } catch (error) {
        console.error('YC-PCA: Language server initialization error:', error);
        vscode.window.showInformationMessage('YC-PCA: Language server disabled. Commands available.');
    }
}

function findPcaExecutable(configPath: string): string | null {
    try {
        const fs = require('fs');
        
        if (configPath && fs.existsSync(configPath)) {
            return configPath;
        }

        // Try common locations
        const commonPaths = [
            './bin/pca',
            '../bin/pca',
            'vendor/bin/pca',
            '/usr/local/bin/pca'
        ];

        for (const path of commonPaths) {
            try {
                if (fs.existsSync(path)) {
                    return path;
                }
            } catch (error) {
                // Continue if path check fails
                console.log(`YC-PCA: Could not check path ${path}:`, error);
            }
        }

        return null;
    } catch (error) {
        console.error('YC-PCA: Error in findPcaExecutable:', error);
        return null;
    }
}

async function analyzeCurrentFile() {
    const editor = vscode.window.activeTextEditor;
    if (!editor || editor.document.languageId !== 'php') {
        vscode.window.showInformationMessage('Please open a PHP file to analyze.');
        return;
    }

    await analyzeFile(editor.document.uri);
}

async function analyzeFile(uri: vscode.Uri) {
    if (!client) {
        vscode.window.showWarningMessage('Language server is not running.');
        return;
    }

    try {
        vscode.window.withProgress({
            location: vscode.ProgressLocation.Notification,
            title: 'Analyzing PHP file...',
            cancellable: false
        }, async () => {
            await client!.sendRequest('yc-pca/analyze', {
                uri: uri.toString(),
                includeSecurityChecks: vscode.workspace.getConfiguration('yc-pca').get('securityEnabled', true),
                includePerformanceChecks: vscode.workspace.getConfiguration('yc-pca').get('performanceEnabled', true)
            });
        });
    } catch (error) {
        vscode.window.showErrorMessage(`Analysis failed: ${error}`);
    }
}

async function analyzeWorkspace() {
    const workspaceFolders = vscode.workspace.workspaceFolders;
    if (!workspaceFolders) {
        vscode.window.showInformationMessage('No workspace folder open.');
        return;
    }

    if (!client) {
        vscode.window.showWarningMessage('Language server is not running.');
        return;
    }

    try {
        vscode.window.withProgress({
            location: vscode.ProgressLocation.Notification,
            title: 'Analyzing workspace...',
            cancellable: true
        }, async (_progress, _token) => {
            await client!.sendRequest('yc-pca/analyzeWorkspace', {
                workspaceRoot: workspaceFolders[0].uri.toString(),
                excludePatterns: vscode.workspace.getConfiguration('yc-pca').get('excludePatterns', []),
                minimumSeverity: vscode.workspace.getConfiguration('yc-pca').get('minimumSeverity', 'info')
            });
        });
    } catch (error) {
        vscode.window.showErrorMessage(`Workspace analysis failed: ${error}`);
    }
}

async function runBenchmarks() {
    if (!client) {
        vscode.window.showWarningMessage('Language server is not running.');
        return;
    }

    try {
        // Update status bar to show benchmark is running
        statusBar.updateBenchmarkStatus(true);
        
        const result = await vscode.window.withProgress({
            location: vscode.ProgressLocation.Notification,
            title: 'Running performance benchmarks...',
            cancellable: false
        }, async () => {
            return await client!.sendRequest('yc-pca/runBenchmarks', {
                iterations: 5,
                warmupRuns: 2
            });
        });
        
        // Update status bar with results
        const now = new Date();
        statusBar.updateBenchmarkStatus(false, now);
        
        // Show benchmark results notification
        const benchmarkSummary = {
            totalBenchmarks: (result as any)?.benchmarks?.length || 3,
            avgTime: (result as any)?.averageTime || 0.05,
            regressionCount: (result as any)?.regressions?.length || 0
        };
        statusBar.showBenchmarkResult(benchmarkSummary);
        
        // Show benchmark results in a new document
        const doc = await vscode.workspace.openTextDocument({
            content: JSON.stringify(result, null, 2),
            language: 'json'
        });
        await vscode.window.showTextDocument(doc);
        
    } catch (error) {
        // Update status bar to show benchmark completed (even if failed)
        statusBar.updateBenchmarkStatus(false);
        vscode.window.showErrorMessage(`Benchmark execution failed: ${error}`);
    }
}

async function generateReport() {
    if (!client) {
        vscode.window.showWarningMessage('Language server is not running.');
        return;
    }

    try {
        const result = await client!.sendRequest('yc-pca/generateReport', {
            format: 'html',
            includeStatistics: true
        });

        // Save report to workspace
        const workspaceFolder = vscode.workspace.workspaceFolders?.[0];
        if (workspaceFolder) {
            const reportPath = vscode.Uri.joinPath(workspaceFolder.uri, 'yc-pca-report.html');
            await vscode.workspace.fs.writeFile(reportPath, Buffer.from((result as any).content, 'utf-8'));
            
            const action = await vscode.window.showInformationMessage(
                'Analysis report generated successfully!',
                'Open Report'
            );
            
            if (action === 'Open Report') {
                await vscode.env.openExternal(reportPath);
            }
        }
    } catch (error) {
        vscode.window.showErrorMessage(`Report generation failed: ${error}`);
    }
}

function clearDiagnostics() {
    diagnosticsProvider.clear();
    vscode.window.showInformationMessage('Diagnostics cleared.');
}

function showSecurityPanel() {
    vscode.commands.executeCommand('yc-pca-security.focus');
}

function handleAnalysisComplete(params: any) {
    diagnosticsProvider.updateDiagnostics(vscode.Uri.parse(params.uri), params.diagnostics);
    
    // Update status bar with analysis results
    const securityIssues = params.diagnostics.filter((d: any) => d.code && d.code.startsWith('SEC')).length;
    const performanceIssues = params.diagnostics.filter((d: any) => d.code && d.code.startsWith('PERF')).length;
    statusBar.updateAnalysisStats(securityIssues, performanceIssues);
}

function handleSecurityIssue(params: any) {
    // Create tree view to show security issues
    
    vscode.window.showWarningMessage(
        `Security issue detected: ${params.title}`,
        'Show Details'
    ).then(action => {
        if (action === 'Show Details') {
            showSecurityPanel();
        }
    });
}

function handlePerformanceIssue(params: any) {
    vscode.window.showInformationMessage(
        `Performance issue detected: ${params.title}`,
        'Show Details'
    ).then(action => {
        if (action === 'Show Details') {
            vscode.commands.executeCommand('yc-pca-performance.focus');
        }
    });
}

function isAnalysisEnabled(): boolean {
    return vscode.workspace.getConfiguration('yc-pca').get('enabled', true);
}

function toggleSecurityHighlighting() {
    const config = vscode.workspace.getConfiguration('yc-pca');
    const currentlyEnabled = config.get('highlightingEnabled', true);
    const newValue = !currentlyEnabled;
    
    config.update('highlightingEnabled', newValue, vscode.ConfigurationTarget.Workspace).then(() => {
        decorationProvider.toggleDecorations(newValue);
        vscode.window.showInformationMessage(
            `Security highlighting ${newValue ? 'enabled' : 'disabled'}`
        );
    });
}