import * as vscode from 'vscode';

export class DiagnosticsProvider {
    private diagnosticCollection: vscode.DiagnosticCollection;

    constructor() {
        this.diagnosticCollection = vscode.languages.createDiagnosticCollection('yc-pca');
    }

    public updateDiagnostics(uri: vscode.Uri, diagnostics: any[]): void {
        const vscDiagnostics: vscode.Diagnostic[] = diagnostics.map(diag => {
            const range = new vscode.Range(
                new vscode.Position(diag.line - 1, diag.column - 1),
                new vscode.Position(diag.endLine - 1, diag.endColumn - 1)
            );

            const severity = this.mapSeverity(diag.severity);
            const diagnostic = new vscode.Diagnostic(range, diag.message, severity);
            
            diagnostic.code = diag.code;
            diagnostic.source = 'YC-PCA';
            diagnostic.tags = this.mapTags(diag);
            
            if (diag.relatedInformation) {
                diagnostic.relatedInformation = diag.relatedInformation.map((info: any) => {
                    return new vscode.DiagnosticRelatedInformation(
                        new vscode.Location(
                            vscode.Uri.parse(info.location.uri),
                            new vscode.Range(
                                new vscode.Position(info.location.range.start.line, info.location.range.start.character),
                                new vscode.Position(info.location.range.end.line, info.location.range.end.character)
                            )
                        ),
                        info.message
                    );
                });
            }

            return diagnostic;
        });

        this.diagnosticCollection.set(uri, vscDiagnostics);
    }

    public clear(): void {
        this.diagnosticCollection.clear();
    }

    public dispose(): void {
        this.diagnosticCollection.dispose();
    }

    private mapSeverity(severity: string): vscode.DiagnosticSeverity {
        switch (severity.toLowerCase()) {
            case 'critical':
            case 'high':
                return vscode.DiagnosticSeverity.Error;
            case 'medium':
                return vscode.DiagnosticSeverity.Warning;
            case 'low':
            case 'info':
                return vscode.DiagnosticSeverity.Information;
            default:
                return vscode.DiagnosticSeverity.Hint;
        }
    }

    private mapTags(diag: any): vscode.DiagnosticTag[] {
        const tags: vscode.DiagnosticTag[] = [];
        
        if (diag.deprecated) {
            tags.push(vscode.DiagnosticTag.Deprecated);
        }
        
        if (diag.unnecessary) {
            tags.push(vscode.DiagnosticTag.Unnecessary);
        }

        return tags;
    }

    public getDiagnostics(uri?: vscode.Uri): readonly [vscode.Uri, readonly vscode.Diagnostic[]][] {
        if (uri) {
            const diagnostics = this.diagnosticCollection.get(uri);
            return diagnostics ? [[uri, diagnostics]] : [];
        }
        return Array.from(this.diagnosticCollection);
    }

    public hasDiagnostics(uri: vscode.Uri): boolean {
        const diagnostics = this.diagnosticCollection.get(uri);
        return diagnostics !== undefined && diagnostics.length > 0;
    }

    public getSecurityDiagnostics(): { uri: vscode.Uri, diagnostics: readonly vscode.Diagnostic[] }[] {
        const results: { uri: vscode.Uri, diagnostics: readonly vscode.Diagnostic[] }[] = [];
        
        this.diagnosticCollection.forEach((uri, diagnostics) => {
            const securityDiags = diagnostics.filter(diag => 
                diag.code && typeof diag.code === 'string' && diag.code.startsWith('SEC')
            );
            
            if (securityDiags.length > 0) {
                results.push({ uri, diagnostics: securityDiags });
            }
        });

        return results;
    }

    public getPerformanceDiagnostics(): { uri: vscode.Uri, diagnostics: readonly vscode.Diagnostic[] }[] {
        const results: { uri: vscode.Uri, diagnostics: readonly vscode.Diagnostic[] }[] = [];
        
        this.diagnosticCollection.forEach((uri, diagnostics) => {
            const performanceDiags = diagnostics.filter(diag => 
                diag.code && typeof diag.code === 'string' && diag.code.startsWith('PERF')
            );
            
            if (performanceDiags.length > 0) {
                results.push({ uri, diagnostics: performanceDiags });
            }
        });

        return results;
    }

    public getSeverityStats(): { [severity: string]: number } {
        const stats: { [severity: string]: number } = {
            'error': 0,
            'warning': 0,
            'information': 0,
            'hint': 0
        };

        this.diagnosticCollection.forEach((_uri, diagnostics) => {
            diagnostics.forEach(diag => {
                switch (diag.severity) {
                    case vscode.DiagnosticSeverity.Error:
                        stats.error++;
                        break;
                    case vscode.DiagnosticSeverity.Warning:
                        stats.warning++;
                        break;
                    case vscode.DiagnosticSeverity.Information:
                        stats.information++;
                        break;
                    case vscode.DiagnosticSeverity.Hint:
                        stats.hint++;
                        break;
                }
            });
        });

        return stats;
    }
}