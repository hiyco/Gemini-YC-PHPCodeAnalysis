import * as assert from 'assert';
import * as vscode from 'vscode';
import { DiagnosticsProvider } from '../../diagnosticsProvider';

suite('DiagnosticsProvider Test Suite', () => {
    let diagnosticsProvider: DiagnosticsProvider;
    let testUri: vscode.Uri;

    setup(() => {
        diagnosticsProvider = new DiagnosticsProvider();
        testUri = vscode.Uri.file('/test/file.php');
    });

    teardown(() => {
        diagnosticsProvider.dispose();
    });

    test('Should update diagnostics correctly', () => {
        const mockDiagnostics = [
            {
                line: 10,
                column: 5,
                endLine: 10,
                endColumn: 15,
                severity: 'high',
                message: 'SQL injection vulnerability detected',
                code: 'SEC_A03_SQL_INJECTION'
            }
        ];

        diagnosticsProvider.updateDiagnostics(testUri, mockDiagnostics);
        
        const diagnostics = diagnosticsProvider.getDiagnostics(testUri);
        assert.strictEqual(diagnostics.length, 1);
        assert.strictEqual(diagnostics[0][1][0].message, 'SQL injection vulnerability detected');
        assert.strictEqual(diagnostics[0][1][0].severity, vscode.DiagnosticSeverity.Error);
    });

    test('Should map severity correctly', () => {
        const testCases = [
            { input: 'critical', expected: vscode.DiagnosticSeverity.Error },
            { input: 'high', expected: vscode.DiagnosticSeverity.Error },
            { input: 'medium', expected: vscode.DiagnosticSeverity.Warning },
            { input: 'low', expected: vscode.DiagnosticSeverity.Information },
            { input: 'info', expected: vscode.DiagnosticSeverity.Information }
        ];

        testCases.forEach(testCase => {
            const mockDiag = [{
                line: 1,
                column: 1,
                endLine: 1,
                endColumn: 10,
                severity: testCase.input,
                message: 'Test message',
                code: 'TEST_001'
            }];

            diagnosticsProvider.updateDiagnostics(testUri, mockDiag);
            const diagnostics = diagnosticsProvider.getDiagnostics(testUri);
            assert.strictEqual(diagnostics[0][1][0].severity, testCase.expected);
        });
    });

    test('Should filter security diagnostics', () => {
        const mockDiagnostics = [
            {
                line: 1, column: 1, endLine: 1, endColumn: 10,
                severity: 'high', message: 'Security issue', code: 'SEC_001'
            },
            {
                line: 2, column: 1, endLine: 2, endColumn: 10,
                severity: 'medium', message: 'Performance issue', code: 'PERF_001'
            },
            {
                line: 3, column: 1, endLine: 3, endColumn: 10,
                severity: 'low', message: 'Another security issue', code: 'SEC_002'
            }
        ];

        diagnosticsProvider.updateDiagnostics(testUri, mockDiagnostics);
        const securityDiags = diagnosticsProvider.getSecurityDiagnostics();
        
        assert.strictEqual(securityDiags.length, 1);
        assert.strictEqual(securityDiags[0].diagnostics.length, 2);
        assert.ok(securityDiags[0].diagnostics.every(d => 
            d.code && typeof d.code === 'string' && d.code.startsWith('SEC')
        ));
    });

    test('Should clear diagnostics', () => {
        const mockDiagnostics = [
            {
                line: 1, column: 1, endLine: 1, endColumn: 10,
                severity: 'high', message: 'Test issue', code: 'TEST_001'
            }
        ];

        diagnosticsProvider.updateDiagnostics(testUri, mockDiagnostics);
        assert.ok(diagnosticsProvider.hasDiagnostics(testUri));

        diagnosticsProvider.clear();
        assert.ok(!diagnosticsProvider.hasDiagnostics(testUri));
    });

    test('Should provide severity statistics', () => {
        const mockDiagnostics = [
            { line: 1, column: 1, endLine: 1, endColumn: 10, severity: 'critical', message: 'Critical issue', code: 'TEST_001' },
            { line: 2, column: 1, endLine: 2, endColumn: 10, severity: 'high', message: 'High issue', code: 'TEST_002' },
            { line: 3, column: 1, endLine: 3, endColumn: 10, severity: 'medium', message: 'Medium issue', code: 'TEST_003' },
            { line: 4, column: 1, endLine: 4, endColumn: 10, severity: 'low', message: 'Low issue', code: 'TEST_004' }
        ];

        diagnosticsProvider.updateDiagnostics(testUri, mockDiagnostics);
        const stats = diagnosticsProvider.getSeverityStats();

        assert.strictEqual(stats.error, 2); // critical + high
        assert.strictEqual(stats.warning, 1); // medium
        assert.strictEqual(stats.information, 1); // low
        assert.strictEqual(stats.hint, 0);
    });
});