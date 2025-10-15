/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: Minimal YC-PCA extension for debugging
 */

import * as vscode from 'vscode';

export function activate(context: vscode.ExtensionContext) {
    console.log('YC-PCA (Minimal) extension is now active!');
    vscode.window.showInformationMessage('YC-PCA Extension Activated!');

    // Register only the essential commands
    const commands = [
        vscode.commands.registerCommand('yc-pca.refreshDecorations', () => {
            console.log('YC-PCA: refreshDecorations command executed (minimal)');
            vscode.window.showInformationMessage('Security decorations refreshed successfully!');
        }),
        vscode.commands.registerCommand('yc-pca.analyzeFile', () => {
            vscode.window.showInformationMessage('File analysis would run here.');
        }),
        vscode.commands.registerCommand('yc-pca.analyzeWorkspace', () => {
            vscode.window.showInformationMessage('Workspace analysis would run here.');
        }),
        vscode.commands.registerCommand('yc-pca.runBenchmarks', () => {
            vscode.window.showInformationMessage('Benchmarks would run here.');
        }),
        vscode.commands.registerCommand('yc-pca.generateReport', () => {
            vscode.window.showInformationMessage('Report generation would run here.');
        }),
        vscode.commands.registerCommand('yc-pca.clearDiagnostics', () => {
            vscode.window.showInformationMessage('Diagnostics cleared.');
        }),
        vscode.commands.registerCommand('yc-pca.showSecurityPanel', () => {
            vscode.window.showInformationMessage('Security panel would show here.');
        }),
        vscode.commands.registerCommand('yc-pca.toggleHighlighting', () => {
            vscode.window.showInformationMessage('Security highlighting toggled.');
        })
    ];

    context.subscriptions.push(...commands);
    console.log(`YC-PCA (Minimal): Registered ${commands.length} commands`);
    vscode.window.showInformationMessage(`YC-PCA: ${commands.length} commands registered`);
}

export function deactivate(): void {
    console.log('YC-PCA (Minimal) extension is now deactivated.');
}