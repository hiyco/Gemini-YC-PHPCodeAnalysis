# YC-PCA 扩展故障排除指南

## 🚨 命令未找到问题解决方案

### 问题现象
```
命令 "YC-PCA: Refresh Security Decorations" 导致错误
command 'yc-pca.refreshDecorations' not found
```

### 🔧 解决步骤

#### 1. 验证扩展安装状态
```bash
# 检查扩展是否已安装
code --list-extensions | grep yc-php-code-analysis

# 预期输出：hiyco.yc-php-code-analysis
```

#### 2. 重新安装扩展
```bash
# 卸载现有扩展
code --uninstall-extension hiyco.yc-php-code-analysis

# 重新安装
code --install-extension yc-php-code-analysis-1.0.1.vsix --force
```

#### 3. 强制重新加载 VSCode
1. 按 `Ctrl+Shift+P` (Windows/Linux) 或 `Cmd+Shift+P` (Mac)
2. 输入 "Developer: Reload Window" 并执行
3. 或者完全关闭 VSCode 并重新启动

#### 4. 验证扩展激活
重新启动后应该看到以下通知：
- "YC-PCA Extension Activated!"
- "YC-PCA: 9 commands registered"

#### 5. 测试命令
1. 按 `Ctrl+Shift+P` / `Cmd+Shift+P`
2. 输入 "YC-PCA: Refresh Security Decorations"
3. 命令应该出现并可执行
4. 执行后应显示："Refreshing security decorations..." → "Security decorations refreshed successfully!"

### 🐛 调试信息

#### 检查开发者控制台
1. 打开 `Help → Toggle Developer Tools`
2. 查看 Console 标签
3. 寻找以下日志：
   ```
   YC-PCA extension is now active!
   YC-PCA: Registered 9 commands
   YC-PCA: refreshDecorations command executed (执行命令时)
   ```

#### 检查扩展状态
1. 按 `Ctrl+Shift+P` → "Extensions: Show Installed Extensions"
2. 搜索 "YC PHP Code Analysis"
3. 确保扩展已启用且无错误标记

### 📋 可用命令列表

执行成功后，以下命令应该都可用：

1. **YC-PCA: Analyze Current File** - 分析当前文件
2. **YC-PCA: Analyze Entire Workspace** - 分析整个工作空间
3. **YC-PCA: Run Performance Benchmarks** - 运行性能基准测试
4. **YC-PCA: Generate Analysis Report** - 生成分析报告
5. **YC-PCA: Clear All Diagnostics** - 清除所有诊断
6. **YC-PCA: Show Security Issues** - 显示安全问题
7. **YC-PCA: Toggle Security Highlighting** - 切换安全高亮
8. **YC-PCA: Refresh Security Decorations** - 刷新安全装饰 ✅

### 🔍 高级排除

#### 方法一：完全重置
```bash
# 1. 完全卸载
code --uninstall-extension hiyco.yc-php-code-analysis

# 2. 清理扩展目录 (可选)
rm -rf ~/.vscode/extensions/hiyco.yc-php-code-analysis-*

# 3. 重新安装
code --install-extension yc-php-code-analysis-1.0.1.vsix --force

# 4. 重启 VSCode
```

#### 方法二：使用测试扩展验证
我们创建了一个简化的测试扩展来验证 VSCode 扩展系统：
```bash
# 安装测试扩展
code --install-extension yc-test-extension-1.0.0.vsix --force

# 测试命令：YC-Test: Hello World 和 YC-Test: Test Refresh
```

### 🛠️ 技术细节

#### 扩展配置
- **激活事件**: `"*"` (立即激活)
- **主入口**: `./out/extension.js`
- **命令数量**: 9 个
- **支持语言**: PHP

#### 已修复的问题
1. ✅ 添加了 `activationEvents: ["*"]`
2. ✅ 在 `commandPalette` 中注册了所有命令
3. ✅ 增加了详细的调试日志和状态反馈
4. ✅ 改进了错误处理和用户提示

### 📞 获取帮助

如果问题仍然存在，请提供以下信息：
1. VSCode 版本 (`Help → About`)
2. 操作系统版本
3. 开发者控制台中的错误信息
4. 扩展列表 (`code --list-extensions`)

---

**最后更新**: 2025-01-15  
**版权**: YC-2025Copyright