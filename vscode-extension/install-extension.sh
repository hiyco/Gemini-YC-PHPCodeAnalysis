#!/bin/bash

# YC-PCA VSCode Extension Installation Script
# Copyright: YC-2025Copyright
# Created: 2025-01-15

echo "🚀 Installing YC-PCA VSCode Extension..."

# Check if VSCode is installed
if ! command -v code &> /dev/null; then
    echo "❌ VSCode 'code' command not found. Please install VSCode and add it to PATH."
    exit 1
fi

# Check if VSIX file exists
if [ ! -f "yc-php-code-analysis-1.0.1.vsix" ]; then
    echo "❌ VSIX file not found. Please run 'npm run package' first."
    exit 1
fi

echo "📦 Installing extension from VSIX..."
code --install-extension yc-php-code-analysis-1.0.1.vsix --force

if [ $? -eq 0 ]; then
    echo "✅ YC-PCA Extension installed successfully!"
    echo ""
    echo "🔧 Next steps:"
    echo "1. Restart VSCode or reload the window (Ctrl+Shift+P -> 'Developer: Reload Window')"
    echo "2. You should see 'YC-PCA Extension Activated!' message when the extension loads"
    echo "3. You should see '9 commands registered' message in the notification"
    echo "4. Try the command: Ctrl+Shift+P -> 'YC-PCA: Refresh Security Decorations'"
    echo ""
    echo "📋 Available Commands:"
    echo "- YC-PCA: Analyze Current File"
    echo "- YC-PCA: Analyze Entire Workspace"
    echo "- YC-PCA: Run Performance Benchmarks"
    echo "- YC-PCA: Generate Analysis Report"
    echo "- YC-PCA: Clear All Diagnostics"
    echo "- YC-PCA: Show Security Issues"
    echo "- YC-PCA: Toggle Security Highlighting"
    echo "- YC-PCA: Refresh Security Decorations"
    echo ""
    echo "🐛 Debug Info:"
    echo "- Check VSCode Developer Console (Help -> Toggle Developer Tools)"
    echo "- Look for 'YC-PCA extension is now active!' in console logs"
    echo "- Extension ID: yc-php-code-analysis"
else
    echo "❌ Installation failed. Please check the error above."
    exit 1
fi