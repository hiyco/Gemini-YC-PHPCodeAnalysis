import * as assert from 'assert';
import * as vscode from 'vscode';

suite('Extension Test Suite', () => {
    vscode.window.showInformationMessage('Start all tests.');

    test('Extension should be present', () => {
        assert.ok(vscode.extensions.getExtension('yc-2025.yc-php-code-analysis'));
    });

    test('Extension should activate', async () => {
        const extension = vscode.extensions.getExtension('yc-2025.yc-php-code-analysis');
        if (extension) {
            await extension.activate();
            assert.strictEqual(extension.isActive, true);
        }
    });

    test('Commands should be registered', async () => {
        const commands = await vscode.commands.getCommands(true);
        const expectedCommands = [
            'yc-pca.analyzeFile',
            'yc-pca.analyzeWorkspace',
            'yc-pca.runBenchmarks',
            'yc-pca.generateReport',
            'yc-pca.clearDiagnostics',
            'yc-pca.showSecurityPanel'
        ];

        expectedCommands.forEach(command => {
            assert.ok(commands.includes(command), `Command ${command} should be registered`);
        });
    });

    test('Configuration should have default values', () => {
        const config = vscode.workspace.getConfiguration('yc-pca');
        assert.strictEqual(config.get('enabled'), true);
        assert.strictEqual(config.get('phpVersion'), '8.3');
        assert.strictEqual(config.get('securityEnabled'), true);
        assert.strictEqual(config.get('performanceEnabled'), true);
    });
});