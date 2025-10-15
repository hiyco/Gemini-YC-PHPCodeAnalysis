const vscode = require('vscode');

function activate(context) {
    console.log('YC-Test extension is now active!');
    vscode.window.showInformationMessage('YC-Test Extension Activated!');

    // Register commands
    const commands = [
        vscode.commands.registerCommand('yc-test.hello', () => {
            vscode.window.showInformationMessage('Hello World from YC-Test!');
        }),
        vscode.commands.registerCommand('yc-test.refresh', () => {
            vscode.window.showInformationMessage('Test Refresh Executed Successfully!');
        })
    ];

    context.subscriptions.push(...commands);
    console.log('YC-Test: Registered 2 commands');
}

function deactivate() {}

module.exports = {
    activate,
    deactivate
};