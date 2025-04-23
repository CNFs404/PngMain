<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 启动会话
ob_start();
session_start();

// 清除所有会话变量
$_SESSION = array();

// 销毁会话 cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// 销毁会话
session_destroy();

// 清除输出缓冲区
ob_end_clean();

// 重定向到登录页面
header('Location: views/index');
exit;           
