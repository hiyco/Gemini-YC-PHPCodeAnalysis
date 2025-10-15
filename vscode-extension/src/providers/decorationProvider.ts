import * as vscode from 'vscode';

export class SecurityDecorationProvider {
    private securityDecorations: Map<string, vscode.TextEditorDecorationType> = new Map();
    private performanceDecorations: Map<string, vscode.TextEditorDecorationType> = new Map();
    private activeDecorations: Map<vscode.TextEditor, Array<{ decoration: vscode.TextEditorDecorationType, ranges: vscode.Range[] }>> = new Map();

    constructor() {
        this.initializeDecorationTypes();
        this.setupEventHandlers();
    }

    private initializeDecorationTypes(): void {
        // Security decoration types
        this.securityDecorations.set('critical', vscode.window.createTextEditorDecorationType({
            backgroundColor: new vscode.ThemeColor('errorBackground'),
            border: '2px solid',
            borderColor: new vscode.ThemeColor('errorForeground'),
            borderRadius: '3px',
            overviewRulerColor: new vscode.ThemeColor('errorForeground'),
            overviewRulerLane: vscode.OverviewRulerLane.Right,
            after: {
                contentText: ' 🚨 CRITICAL',
                color: new vscode.ThemeColor('errorForeground'),
                fontWeight: 'bold'
            }
        }));

        this.securityDecorations.set('high', vscode.window.createTextEditorDecorationType({
            backgroundColor: new vscode.ThemeColor('warningBackground'),
            border: '1px solid',
            borderColor: new vscode.ThemeColor('warningForeground'),
            borderRadius: '2px',
            overviewRulerColor: new vscode.ThemeColor('warningForeground'),
            overviewRulerLane: vscode.OverviewRulerLane.Right,
            after: {
                contentText: ' ⚠️ HIGH',
                color: new vscode.ThemeColor('warningForeground'),
                fontWeight: 'bold'
            }
        }));

        this.securityDecorations.set('medium', vscode.window.createTextEditorDecorationType({
            backgroundColor: new vscode.ThemeColor('infoBackground'),
            border: '1px dashed',
            borderColor: new vscode.ThemeColor('infoForeground'),
            borderRadius: '2px',
            overviewRulerColor: new vscode.ThemeColor('infoForeground'),
            overviewRulerLane: vscode.OverviewRulerLane.Right,
            after: {
                contentText: ' 📋 MEDIUM',
                color: new vscode.ThemeColor('infoForeground')
            }
        }));

        this.securityDecorations.set('low', vscode.window.createTextEditorDecorationType({
            backgroundColor: 'rgba(100, 149, 237, 0.1)',
            border: '1px dotted #6495ED',
            borderRadius: '1px',
            overviewRulerColor: '#6495ED',
            overviewRulerLane: vscode.OverviewRulerLane.Right,
            after: {
                contentText: ' 💡 LOW',
                color: '#6495ED'
            }
        }));

        // Performance decoration types
        this.performanceDecorations.set('critical', vscode.window.createTextEditorDecorationType({
            backgroundColor: 'rgba(255, 69, 0, 0.2)',
            border: '2px solid #FF4500',
            borderRadius: '3px',
            overviewRulerColor: '#FF4500',
            overviewRulerLane: vscode.OverviewRulerLane.Left,
            after: {
                contentText: ' 🔥 PERF CRITICAL',
                color: '#FF4500',
                fontWeight: 'bold'
            }
        }));

        this.performanceDecorations.set('high', vscode.window.createTextEditorDecorationType({
            backgroundColor: 'rgba(255, 165, 0, 0.15)',
            border: '1px solid #FFA500',
            borderRadius: '2px',
            overviewRulerColor: '#FFA500',
            overviewRulerLane: vscode.OverviewRulerLane.Left,
            after: {
                contentText: ' ⚡ PERF HIGH',
                color: '#FFA500',
                fontWeight: 'bold'
            }
        }));

        this.performanceDecorations.set('medium', vscode.window.createTextEditorDecorationType({
            backgroundColor: 'rgba(255, 215, 0, 0.1)',
            border: '1px dashed #FFD700',
            borderRadius: '2px',
            overviewRulerColor: '#FFD700',
            overviewRulerLane: vscode.OverviewRulerLane.Left,
            after: {
                contentText: ' ⏱️ PERF MEDIUM',
                color: '#FFD700'
            }
        }));
    }

    private setupEventHandlers(): void {
        // Update decorations when active editor changes
        vscode.window.onDidChangeActiveTextEditor(editor => {
            if (editor && editor.document.languageId === 'php') {
                this.updateDecorations(editor);
            }
        });

        // Update decorations when document changes
        vscode.workspace.onDidChangeTextDocument(event => {
            const editor = vscode.window.activeTextEditor;
            if (editor && editor.document === event.document && event.document.languageId === 'php') {
                // Debounce updates to avoid excessive decoration updates
                this.debounceUpdateDecorations(editor);
            }
        });

        // Clear decorations when editor is closed
        vscode.window.onDidChangeVisibleTextEditors(() => {
            this.cleanupInactiveEditorDecorations();
        });
    }

    private debounceTimer: NodeJS.Timer | undefined;
    private debounceUpdateDecorations(editor: vscode.TextEditor): void {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        
        this.debounceTimer = setTimeout(() => {
            this.updateDecorations(editor);
        }, 300); // 300ms debounce
    }

    public updateDecorations(editor: vscode.TextEditor): void {
        if (!editor || editor.document.languageId !== 'php') {
            return;
        }

        // Clear existing decorations for this editor
        this.clearEditorDecorations(editor);

        const document = editor.document;
        const text = document.getText();
        
        // Find security issues
        const securityIssues = this.findSecurityIssues(document, text);
        const performanceIssues = this.findPerformanceIssues(document, text);

        const editorDecorations: Array<{ decoration: vscode.TextEditorDecorationType, ranges: vscode.Range[] }> = [];

        // Apply security decorations
        for (const [severity, issues] of securityIssues.entries()) {
            const decoration = this.securityDecorations.get(severity);
            if (decoration && issues.length > 0) {
                const ranges = issues.map(issue => new vscode.Range(
                    document.positionAt(issue.start),
                    document.positionAt(issue.end)
                ));
                
                editor.setDecorations(decoration, ranges);
                editorDecorations.push({ decoration, ranges });
            }
        }

        // Apply performance decorations
        for (const [impact, issues] of performanceIssues.entries()) {
            const decoration = this.performanceDecorations.get(impact);
            if (decoration && issues.length > 0) {
                const ranges = issues.map(issue => new vscode.Range(
                    document.positionAt(issue.start),
                    document.positionAt(issue.end)
                ));
                
                editor.setDecorations(decoration, ranges);
                editorDecorations.push({ decoration, ranges });
            }
        }

        // Store decorations for cleanup
        this.activeDecorations.set(editor, editorDecorations);
    }

    private findSecurityIssues(_document: vscode.TextDocument, text: string): Map<string, Array<{ start: number, end: number }>> {
        const issues = new Map<string, Array<{ start: number, end: number }>>();
        
        const securityPatterns = [
            {
                pattern: /\$_(GET|POST|REQUEST|COOKIE)\s*\[\s*['"][^'"]*['"]\s*\]/g,
                severity: 'high'
            },
            {
                pattern: /(mysql_query|mysqli_query)\s*\(\s*['"][^'"]*\$[^'"]*['"]/g,
                severity: 'critical'
            },
            {
                pattern: /echo\s+\$_(GET|POST|REQUEST|COOKIE)/g,
                severity: 'high'
            },
            {
                pattern: /(md5|sha1)\s*\(\s*\$\w+\s*\)/g,
                severity: 'medium'
            },
            {
                pattern: /eval\s*\(\s*\$[^)]+\)/g,
                severity: 'critical'
            },
            {
                pattern: /(exec|shell_exec|system|passthru)\s*\(\s*[^)]*\$[^)]*\)/g,
                severity: 'critical'
            },
            {
                pattern: /file_get_contents\s*\(\s*\$_(GET|POST|REQUEST)\s*\[/g,
                severity: 'high'
            },
            {
                pattern: /\$password\s*=\s*['"][^'"]{1,8}['"]/g,
                severity: 'medium'
            }
        ];

        securityPatterns.forEach(({ pattern, severity }) => {
            const matches = [...text.matchAll(pattern)];
            matches.forEach(match => {
                if (match.index !== undefined) {
                    if (!issues.has(severity)) {
                        issues.set(severity, []);
                    }
                    issues.get(severity)!.push({
                        start: match.index,
                        end: match.index + match[0].length
                    });
                }
            });
        });

        return issues;
    }

    private findPerformanceIssues(_document: vscode.TextDocument, text: string): Map<string, Array<{ start: number, end: number }>> {
        const issues = new Map<string, Array<{ start: number, end: number }>>();
        
        const performancePatterns = [
            {
                pattern: /for\s*\([^)]*\)\s*\{[^}]*for\s*\([^)]*\)/g,
                impact: 'high'
            },
            {
                pattern: /while\s*\([^)]*\)\s*\{[^}]*while\s*\([^)]*\)/g,
                impact: 'high'
            },
            {
                pattern: /mysql_query\s*\(\s*['"][^'"]*['"][^)]*\)\s*;[^}]*mysql_query/gm,
                impact: 'critical'
            }
        ];

        performancePatterns.forEach(({ pattern, impact }) => {
            const matches = [...text.matchAll(pattern)];
            matches.forEach(match => {
                if (match.index !== undefined) {
                    if (!issues.has(impact)) {
                        issues.set(impact, []);
                    }
                    issues.get(impact)!.push({
                        start: match.index,
                        end: match.index + match[0].length
                    });
                }
            });
        });

        return issues;
    }

    private clearEditorDecorations(editor: vscode.TextEditor): void {
        const decorations = this.activeDecorations.get(editor);
        if (decorations) {
            decorations.forEach(({ decoration }) => {
                editor.setDecorations(decoration, []);
            });
            this.activeDecorations.delete(editor);
        }
    }

    private cleanupInactiveEditorDecorations(): void {
        const activeEditors = vscode.window.visibleTextEditors;
        const editorsToCleanup: vscode.TextEditor[] = [];

        for (const [editor] of this.activeDecorations) {
            if (!activeEditors.includes(editor)) {
                editorsToCleanup.push(editor);
            }
        }

        editorsToCleanup.forEach(editor => {
            this.clearEditorDecorations(editor);
        });
    }

    public dispose(): void {
        // Clear all decorations
        for (const [editor] of this.activeDecorations) {
            this.clearEditorDecorations(editor);
        }

        // Dispose decoration types
        this.securityDecorations.forEach(decoration => decoration.dispose());
        this.performanceDecorations.forEach(decoration => decoration.dispose());
        
        this.securityDecorations.clear();
        this.performanceDecorations.clear();
        this.activeDecorations.clear();
    }

    public refreshAllDecorations(): void {
        const activeEditor = vscode.window.activeTextEditor;
        if (activeEditor) {
            this.updateDecorations(activeEditor);
        }
    }

    public toggleDecorations(enabled: boolean): void {
        if (enabled) {
            const activeEditor = vscode.window.activeTextEditor;
            if (activeEditor) {
                this.updateDecorations(activeEditor);
            }
        } else {
            // Clear all decorations
            for (const [editor] of this.activeDecorations) {
                this.clearEditorDecorations(editor);
            }
        }
    }
}