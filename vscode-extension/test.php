<?php
/*
 * Copyright: YC-2025Copyright
 * Created: 2025-01-15
 * Author: YC
 * Description: PHP测试文件用于验证YC-PCA扩展功能
 */

// 安全问题示例 - 应该被检测到
$username = $_GET['username']; // XSS漏洞风险
$password = $_POST['password']; // 未验证的用户输入
$data = $_REQUEST['data']; // 危险的请求变量

// 危险函数示例
eval($code); // 严重安全风险
system($command); // 命令执行风险
exec("rm -rf /"); // 系统命令执行

// 已弃用的函数
$result = mysql_query($sql); // 已弃用的MySQL函数

// 文件包含漏洞
include($_GET['file']); // 文件包含漏洞
file_get_contents($_POST['url']); // 远程文件包含风险

// 正常代码 - 不应该被标记
$config = [
    'database' => 'localhost',
    'username' => 'admin',
    'password' => 'secure_password_123'
];

function validateInput($input) {
    return filter_var($input, FILTER_SANITIZE_STRING);
}

$safeData = validateInput($userInput);
echo htmlspecialchars($safeData, ENT_QUOTES, 'UTF-8');

?>