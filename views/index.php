<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/auth.php';
checkAuth(); // 验证用户状态

// 确保在文件开头启动session
ob_start();
session_start();

// 检查配置文件是否存在
$configPath = dirname(dirname(__FILE__)) . '/config.php';
if (!file_exists($configPath)) {
    die('请先运行 <a href="../install.php">install.php</a> 完成系统安装');
}

// 引入配置文件
require_once $configPath;

// 检查是否已安装
if (!defined('INSTALLED') || INSTALLED !== true) {
    die('系统未安装，请先完成安装步骤。');
}

// 检查必要的常量是否定义
if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
    ob_end_clean();
    die('数据库配置未完成，请重新运行 <a href="install">install</a>');
}

// 检查includes目录下的文件
if (!file_exists('../models/Database.php') || !file_exists('../includes/ImageUploader.php')) {
    ob_end_clean();
    die('系统关键文件丢失');
}

require_once '../models/Database.php';
require_once '../includes/ImageUploader.php';

// 初始化消息变量
$message = '';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    // 处理登录请求
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== '封禁') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    error_log("用户登录成功: {$username}");
                    ob_end_clean();
                    header('Location: /');
                    exit;
                } else {
                    $error = '用户已被封禁';
                }
            } else {
                $error = '用户名或密码错误';
            }
        } catch (Exception $e) {
            $error = '登录失败：' . $e->getMessage();
        }
    }
    
    // 显示登录页面
    ob_end_clean();
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>登录 - 图床系统</title>
        <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
            }
            .login-container {
                max-width: 450px;
                margin: 40px auto;
                padding: 30px;
                background: rgba(255, 255, 255, 0.95);
                border-radius: 15px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.1);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.2);
            }
            .login-title {
                text-align: center;
                margin-bottom: 30px;
                color: #2c3e50;
                font-weight: 600;
                font-size: 28px;
                position: relative;
                padding-bottom: 10px;
            }
            .login-title::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 50%;
                transform: translateX(-50%);
                width: 60px;
                height: 3px;
                background: linear-gradient(90deg, #2196F3, #1976D2);
                border-radius: 3px;
            }
            .form-control {
                border: 1px solid #e0e0e0;
                padding: 12px;
                height: auto;
                font-size: 15px;
                background: #f8f9fa;
                transition: all 0.3s ease;
                margin-bottom: 20px;
            }
            .form-control:focus {
                background: #fff;
                border-color: #2196F3;
                box-shadow: 0 0 0 3px rgba(33,150,243,0.1);
            }
            .form-label {
                color: #546e7a;
                font-weight: 500;
                margin-bottom: 8px;
                font-size: 14px;
            }
            .btn-login {
                width: 100%;
                padding: 12px;
                font-size: 16px;
                font-weight: 500;
                background: linear-gradient(45deg, #2196F3, #1976D2);
                border: none;
                border-radius: 8px;
                transition: all 0.3s ease;
                color: white;
            }
            .btn-login:hover {
                background: linear-gradient(45deg, #1976D2, #1565C0);
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(33,150,243,0.2);
            }
            .register-link {
                text-align: center;
                margin-top: 25px;
                color: #78909c;
            }
            .register-link a {
                color: #2196F3;
                text-decoration: none;
                font-weight: 500;
                transition: all 0.3s ease;
            }
            .register-link a:hover {
                color: #1976D2;
                text-decoration: underline;
            }
            .alert {
                border: none;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 25px;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .alert-danger {
                background-color: #fee2e2;
                color: #ef4444;
            }
            .alert i {
                font-size: 18px;
            }
            .input-group {
                position: relative;
                margin-bottom: 20px;
            }
            .input-group .form-control {
                padding-left: 40px;
                margin-bottom: 0;
            }
            .input-group i {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #90a4ae;
                font-size: 18px;
                z-index: 10;
            }
            .header {
                background-color: #f8f9fa;
                padding: 1rem 0;
                margin-bottom: 2rem;
                border-bottom: 1px solid #dee2e6;
                position: relative;
            }
            
            /* Toast 样式 */
            .toast-container {
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 9999;
                pointer-events: none;
            }
            
            .toast {
                min-width: 250px;
                padding: 0;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                opacity: 0;
                transform: translateY(-20px);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                background: rgba(255, 255, 255, 0.98);
                border: none;
                pointer-events: auto;
            }
            
            .toast.show {
                opacity: 1;
                transform: translateY(0);
            }
            
            .toast.error {
                border-left: 4px solid #dc3545;
            }
            
            .toast.success {
                border-left: 4px solid #28a745;
            }
            
            .toast-content {
                display: flex;
                align-items: center;
                width: 100%;
            }
            
            .toast .toast-body {
                display: flex;
                align-items: center;
                padding: 12px 15px;
                color: #333;
                font-size: 14px;
                line-height: 1.5;
                width: 100%;
            }
            
            .toast .bi {
                font-size: 18px;
                margin-right: 10px;
            }
            
            .toast.success .bi {
                color: #28a745;
            }
            
            .toast.error .bi {
                color: #dc3545;
            }
            
            .toast .toast-message {
                flex: 1;
            }
            
            /* 上传进度样式 */
            #uploadProgress {
                transition: opacity 0.3s ease-in-out;
            }
            
            #uploadProgress.show {
                opacity: 1;
            }
            
            #uploadProgress:not(.show) {
                opacity: 0;
                pointer-events: none;
            }
            
            #uploadProgressText {
                margin-top: 10px;
                font-size: 14px;
            }

            .modal-dialog {
                position: fixed;
                margin: 0 auto;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) !important;
                width: calc(100% - 2rem);
                max-width: 500px;
            }

            @media (max-width: 576px) {
                .modal-dialog {
                    margin: 0 auto;
                    width: calc(100% - 2rem);
                    max-height: 90vh;
                    overflow-y: auto;
                }
                
                .modal-content {
                    border-radius: 12px;
                    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
                }
                
                .folder-select-list {
                    max-height: 60vh;
                    overflow-y: auto;
                    -webkit-overflow-scrolling: touch;
                }
            }

            .modal.fade .modal-dialog {
                transition: transform 0.3s ease-out;
            }

            .modal.show .modal-dialog {
                transform: translate(-50%, -50%) !important;
            }
        </style>
    </head>
    <body class="bg-light">
        <div class="container">
            <div class="login-container">
                <h2 class="login-title">用户登录</h2>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">用户名</label>
                        <div class="input-group">
                            <i class="bi bi-person"></i>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="请输入用户名" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">密码</label>
                        <div class="input-group">
                            <i class="bi bi-lock"></i>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="请输入密码" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login">登 录</button>
                    
                    <div class="register-link">
                        还没有账号？<a href="../register">立即注册</a>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 用户已登录，显示主页面
ob_end_clean();

// 获取用户上传的图片
try {
    $db = Database::getInstance()->getConnection();
    
    // 获取当前文件夹ID和名称
    $current_folder = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
    $current_folder_name = '';

    if ($current_folder) {
        $stmt = $db->prepare("SELECT * FROM folders WHERE id = ? AND user_id = ?");
        $stmt->execute([$current_folder, $_SESSION['user_id']]);
        $folder = $stmt->fetch();
        
        if (!$folder) {
            $_SESSION['error'] = '您没有权限访问此文件夹';
            echo '<script>window.location.href = "/";</script>';
            exit;
        }
        
        $current_folder_name = $folder['name'];
        
        // 获取当前文件夹中的图片
        $stmt = $db->prepare("SELECT * FROM images WHERE folder_id = ? AND user_id = ? ORDER BY upload_date DESC");
        $stmt->execute([$current_folder, $_SESSION['user_id']]);
        $images = $stmt->fetchAll();
    } else {
        // 获取未分类的图片
        $stmt = $db->prepare("SELECT * FROM images WHERE folder_id IS NULL AND user_id = ? ORDER BY upload_date DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $images = $stmt->fetchAll();
    }

    // 获取用户的所有文件夹
    $stmt = $db->prepare("SELECT * FROM folders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $folders = $stmt->fetchAll();

    // 显示错误信息（如果有）
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
    // 处理新建文件夹请求
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_folder') {
        $folder_name = trim($_POST['folder_name'] ?? '');
        if (!empty($folder_name)) {
            try {
                $stmt = $db->prepare("INSERT INTO folders (user_id, name) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $folder_name]);
                header('Location: ' . $_SERVER['PHP_SELF'] . ($current_folder_id ? "?folder={$current_folder_id}" : ''));
                exit;
            } catch (Exception $e) {
                $message = '创建文件夹失败：' . $e->getMessage();
            }
        } else {
            $message = '文件夹名称不能为空';
        }
    }

    // 处理移动图片到文件夹的请求
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'move_image') {
        $image_id = $_POST['image_id'] ?? null;
        $target_folder_id = $_POST['folder_id'] ?? null;
        if ($image_id && $target_folder_id) {
            try {
                $stmt = $db->prepare("UPDATE images SET folder_id = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$target_folder_id, $image_id, $_SESSION['user_id']]);
                header('Location: ' . $_SERVER['PHP_SELF'] . ($current_folder_id ? "?folder={$current_folder_id}" : ''));
                exit;
            } catch (Exception $e) {
                $message = '移动图片失败：' . $e->getMessage();
            }
        }
    }
} catch (Exception $e) {
    $message = '加载图片失败：' . $e->getMessage();
    $images = [];
    $folders = [];
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图片管理系统</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .header {
            background-color: #f8f9fa;
            padding: 1rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid #dee2e6;
            position: relative;
        }
        .header-title {
            font-size: 1.5rem;
            font-weight: 500;
            color: #212529;
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .header-icon {
            width: 32px;
            height: 32px;
            display: inline-block;
            vertical-align: middle;
            transition: transform 0.3s ease;
        }
        .header-title:hover .header-icon {
            transform: scale(1.1) rotate(5deg);
        }
        .glitch {
            position: relative;
            color: #212529;
            letter-spacing: 3px;
            animation: glitch 3s infinite;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .glitch:hover {
            animation: glitch 0.5s infinite;
            text-shadow: 2px 2px #ff00ff, -2px -2px #00ffff;
        }
        .glitch::before,
        .glitch::after {
            content: attr(data-text);
            position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            background: #f8f9fa;
        }
        .glitch::before {
            left: 2px;
            text-shadow: -2px 0 #ff00ff;
            clip: rect(0, 900px, 20px, 0);
            animation: glitch-anim 2s infinite linear alternate-reverse;
        }
        .glitch::after {
            left: -2px;
            text-shadow: -2px 0 #00ffff;
            clip: rect(0, 900px, 20px, 0);
            animation: glitch-anim2 2s infinite linear alternate-reverse;
        }
        .glitch:hover::before {
            animation: glitch-anim 0.3s infinite linear alternate-reverse;
        }
        .glitch:hover::after {
            animation: glitch-anim2 0.3s infinite linear alternate-reverse;
        }
        .glitch span {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            clip-path: polygon(0 0, 100% 0, 100% 45%, 0 45%);
            transform: translateX(-0.04em);
            opacity: 0.7;
        }
        .glitch:hover span {
            animation: glitch-span 0.3s infinite linear alternate-reverse;
        }
        @keyframes glitch-span {
            0% {
                transform: translateX(-0.04em);
                opacity: 0.7;
            }
            50% {
                transform: translateX(0.04em);
                opacity: 0.4;
            }
            100% {
                transform: translateX(-0.04em);
                opacity: 0.7;
            }
        }
        @keyframes glitch {
            0% {
                transform: translate(0);
            }
            20% {
                transform: translate(-2px, 2px);
            }
            40% {
                transform: translate(-2px, -2px);
            }
            60% {
                transform: translate(2px, 2px);
            }
            80% {
                transform: translate(2px, -2px);
            }
            100% {
                transform: translate(0);
            }
        }
        @keyframes glitch-anim {
            0% {
                clip: rect(31px, 9999px, 94px, 0);
                transform: skew(0.9deg);
            }
            5% {
                clip: rect(60px, 9999px, 29px, 0);
                transform: skew(0.45deg);
            }
            10% {
                clip: rect(31px, 9999px, 96px, 0);
                transform: skew(0.7deg);
            }
            15% {
                clip: rect(63px, 9999px, 100px, 0);
                transform: skew(0.83deg);
            }
            20% {
                clip: rect(39px, 9999px, 85px, 0);
                transform: skew(0.62deg);
            }
            25% {
                clip: rect(25px, 9999px, 50px, 0);
                transform: skew(0.45deg);
            }
            30% {
                clip: rect(14px, 9999px, 91px, 0);
                transform: skew(0.25deg);
            }
            35% {
                clip: rect(89px, 9999px, 11px, 0);
                transform: skew(0.2deg);
            }
            40% {
                clip: rect(95px, 9999px, 73px, 0);
                transform: skew(0.4deg);
            }
            45% {
                clip: rect(11px, 9999px, 35px, 0);
                transform: skew(0.52deg);
            }
            50% {
                clip: rect(31px, 9999px, 50px, 0);
                transform: skew(0.6deg);
            }
            55% {
                clip: rect(75px, 9999px, 5px, 0);
                transform: skew(0.85deg);
            }
            60% {
                clip: rect(2px, 9999px, 60px, 0);
                transform: skew(0.9deg);
            }
            65% {
                clip: rect(23px, 9999px, 30px, 0);
                transform: skew(0.75deg);
            }
            70% {
                clip: rect(76px, 9999px, 92px, 0);
                transform: skew(0.8deg);
            }
            75% {
                clip: rect(1px, 9999px, 91px, 0);
                transform: skew(0.9deg);
            }
            80% {
                clip: rect(54px, 9999px, 27px, 0);
                transform: skew(0.75deg);
            }
            85% {
                clip: rect(88px, 9999px, 12px, 0);
                transform: skew(0.45deg);
            }
            90% {
                clip: rect(31px, 9999px, 14px, 0);
                transform: skew(0.6deg);
            }
            95% {
                clip: rect(83px, 9999px, 5px, 0);
                transform: skew(0.2deg);
            }
            100% {
                clip: rect(40px, 9999px, 73px, 0);
                transform: skew(0.4deg);
            }
        }
        @keyframes glitch-anim2 {
            0% {
                clip: rect(76px, 9999px, 100px, 0);
                transform: skew(0.2deg);
            }
            5% {
                clip: rect(54px, 9999px, 45px, 0);
                transform: skew(0.3deg);
            }
            10% {
                clip: rect(79px, 9999px, 66px, 0);
                transform: skew(0.4deg);
            }
            15% {
                clip: rect(48px, 9999px, 47px, 0);
                transform: skew(0.5deg);
            }
            20% {
                clip: rect(14px, 9999px, 50px, 0);
                transform: skew(0.6deg);
            }
            25% {
                clip: rect(31px, 9999px, 35px, 0);
                transform: skew(0.7deg);
            }
            30% {
                clip: rect(89px, 9999px, 51px, 0);
                transform: skew(0.8deg);
            }
            35% {
                clip: rect(95px, 9999px, 30px, 0);
                transform: skew(0.9deg);
            }
            40% {
                clip: rect(11px, 9999px, 14px, 0);
                transform: skew(0.2deg);
            }
            45% {
                clip: rect(31px, 9999px, 100px, 0);
                transform: skew(0.3deg);
            }
            50% {
                clip: rect(75px, 9999px, 5px, 0);
                transform: skew(0.4deg);
            }
            55% {
                clip: rect(2px, 9999px, 60px, 0);
                transform: skew(0.5deg);
            }
            60% {
                clip: rect(23px, 9999px, 30px, 0);
                transform: skew(0.6deg);
            }
            65% {
                clip: rect(76px, 9999px, 92px, 0);
                transform: skew(0.7deg);
            }
            70% {
                clip: rect(1px, 9999px, 91px, 0);
                transform: skew(0.8deg);
            }
            75% {
                clip: rect(54px, 9999px, 27px, 0);
                transform: skew(0.9deg);
            }
            80% {
                clip: rect(88px, 9999px, 12px, 0);
                transform: skew(0.2deg);
            }
            85% {
                clip: rect(31px, 9999px, 14px, 0);
                transform: skew(0.3deg);
            }
            90% {
                clip: rect(83px, 9999px, 5px, 0);
                transform: skew(0.4deg);
            }
            95% {
                clip: rect(40px, 9999px, 73px, 0);
                transform: skew(0.5deg);
            }
            100% {
                clip: rect(76px, 9999px, 100px, 0);
                transform: skew(0.6deg);
            }
        }
        .header-actions {
                display: flex;
                align-items: center;
            gap: 1.5rem;
        }
        .admin-link {
            color: #2196F3;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(33, 150, 243, 0.1);
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        .admin-link:hover {
            background: rgba(33, 150, 243, 0.2);
            color: #1976D2;
            transform: translateY(-1px);
        }
        .admin-link i {
            font-size: 1rem;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .user-name {
            color: #6c757d;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .user-name:hover {
            color: #495057;
        }
        .logout-link {
            color: #dc3545;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 6px;
        }
        .logout-link:hover {
            color: #c82333;
            background: rgba(220, 53, 69, 0.1);
        }
        .upload-form {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        .image-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            min-height: 400px;
        }
        .image-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #fff;
            transition: all 0.3s ease;
        }
        
        .image-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .image-item img {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 20px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .image-item img:hover {
            transform: scale(1.05);
        }
        
        .image-info {
            flex: 1;
                display: flex;
                flex-direction: column;
            gap: 8px;
        }
        
        .image-info .filename {
            font-weight: 500;
            color: #333;
            margin: 0;
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .image-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .image-actions {
            display: flex;
            gap: 10px;
            margin-left: auto;
            align-items: center;
        }
        
        .image-actions button {
            padding: 8px 15px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: #fff;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .image-actions button:hover {
            background: #f8f9fa;
            color: #495057;
        }
        
        .image-actions .copy-link-btn.copied {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        @media (max-width: 768px) {
            .image-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .image-item img {
                width: 100%;
                height: 150px;
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .image-info {
                width: 100%;
            }
            
            .image-meta {
                flex-direction: column;
                gap: 5px;
            }
            
            .image-actions {
                width: 100%;
                margin-top: 15px;
                justify-content: flex-start;
            }
            
            .image-actions button {
                flex: 1;
                justify-content: center;
            }
        }
        .copy-link-btn {
            padding: 8px 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 15px;
            white-space: nowrap;
        }
        .copy-link-btn:hover {
            background: #e9ecef;
            color: #495057;
        }
        .copy-link-btn.copied {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        .move-to-folder {
            padding: 8px 15px;
            color: #2196F3;
            cursor: pointer;
                display: flex;
                align-items: center;
            gap: 5px;
            margin-right: 15px;
        }
        .move-to-folder:hover {
            color: #1976D2;
        }
        #imagePreview {
            max-width: 300px;
            max-height: 300px;
            margin-top: 10px;
            display: none;
            cursor: pointer;
        }
        #imagePreview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .modal-dialog {
            max-width: 90%;
            max-height: 90vh;
        }
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            background: #ffffff;
            max-width: 600px;
            margin: 1.75rem auto;
        }
        .modal-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 16px 16px 0 0;
            padding: 1.5rem;
        }
            .modal-title {
                color: #1e293b;
                font-size: 1.25rem;
                font-weight: 600;
                margin: 0;
            }
        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }
        .folder-select-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 4px;
        }
        .folder-select-item {
            display: flex;
            align-items: center;
            padding: 16px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            gap: 16px;
            position: relative;
        }
        .folder-select-item:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .folder-select-item.selected {
            background: #e8f4ff;
            border-color: #90caf9;
            box-shadow: 0 0 0 2px #2196F3;
        }
        .folder-select-item .folder-icon {
            color: #ffd700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255, 215, 0, 0.1);
            border-radius: 8px;
        }
        .folder-select-item .folder-info {
            flex: 1;
        }
        .folder-select-item .folder-name {
            color: #334155;
            font-weight: 500;
            font-size: 1rem;
            margin-bottom: 4px;
        }
        .folder-select-item .folder-meta {
            display: flex;
            gap: 16px;
            color: #64748b;
            font-size: 0.875rem;
        }
        .folder-select-item .folder-count,
        .folder-select-item .folder-date {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .folder-select-item .folder-count i,
        .folder-select-item .folder-date i {
            font-size: 1rem;
            opacity: 0.7;
        }
        .folder-select-item .select-indicator {
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 50%;
            margin-left: auto;
            transition: all 0.2s ease;
            position: relative;
        }
        .folder-select-item.selected .select-indicator {
            border-color: #2196F3;
            background: #2196F3;
        }
        .folder-select-item.selected .select-indicator::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 10px;
            height: 10px;
            background: #fff;
            border-radius: 50%;
        }
        .modal-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-radius: 0 0 16px 16px;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
            .modal-footer .btn {
                padding: 0.5rem 1.25rem;
                font-size: 0.875rem;
                font-weight: 500;
                border-radius: 8px;
                transition: all 0.2s ease;
            }
            .modal-footer .btn-secondary {
                background: #f1f5f9;
                border: 1px solid #e2e8f0;
                color: #64748b;
            }
            .modal-footer .btn-secondary:hover {
                background: #e2e8f0;
                color: #334155;
            }
            .modal-footer .btn-primary {
                background: #2196F3;
                border: none;
                color: #fff;
            }
            .modal-footer .btn-primary:hover:not(:disabled) {
                background: #1976D2;
                transform: translateY(-1px);
            }
            .modal-footer .btn-primary:disabled {
                background: #90caf9;
                cursor: not-allowed;
            }
            /* 滚动条样式 */
            .modal-body::-webkit-scrollbar {
                width: 8px;
            }
            .modal-body::-webkit-scrollbar-track {
                background: #f1f5f9;
                border-radius: 4px;
            }
            .modal-body::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 4px;
            }
            .modal-body::-webkit-scrollbar-thumb:hover {
                background: #94a3b8;
            }
            /* 动画效果 */
            .modal.fade .modal-dialog {
                transform: scale(0.95);
                opacity: 0;
                transition: all 0.2s ease-out;
            }
            .modal.show .modal-dialog {
                transform: scale(1);
                opacity: 1;
            }
            /* 响应式调整 */
            @media (max-width: 640px) {
                .modal-content {
                    margin: 1rem;
                }
                
                .folder-select-item {
                    padding: 12px;
                }
                
                .folder-select-item .folder-meta {
                    flex-direction: column;
                    gap: 4px;
                }
                
                .modal-footer {
                    flex-direction: column-reverse;
                }
                
                .modal-footer .btn {
                    width: 100%;
                }
            }
            /* 空状态样式 */
            .folder-select-empty {
                text-align: center;
                padding: 2rem;
                color: #64748b;
            }
            .folder-select-empty i {
                font-size: 3rem;
                color: #cbd5e1;
                margin-bottom: 1rem;
                display: block;
            }
            .folder-select-empty p {
                margin: 0;
                font-size: 0.875rem;
            }
            /* 加载状态样式 */
            .folder-select-loading {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
                color: #64748b;
            }
            .folder-select-loading .spinner {
                width: 24px;
                height: 24px;
                border: 3px solid #e2e8f0;
                border-top-color: #2196F3;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-right: 12px;
            }
            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }
            /* 搜索框样式 */
            .folder-search {
                margin-bottom: 1rem;
                position: relative;
            }
            .folder-search input {
                width: 100%;
                padding: 0.75rem 1rem 0.75rem 2.5rem;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                font-size: 0.875rem;
                color: #334155;
                transition: all 0.2s ease;
            }
            .folder-search input:focus {
                outline: none;
                border-color: #2196F3;
                box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
            }
            .folder-search i {
                position: absolute;
                left: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: #94a3b8;
                font-size: 1rem;
            }
            /* 错误状态样式 */
            .folder-select-error {
                text-align: center;
                padding: 2rem;
                color: #ef4444;
                background: #fef2f2;
                border-radius: 8px;
                margin: 1rem 0;
            }
            .folder-select-error i {
                font-size: 2rem;
                margin-bottom: 0.5rem;
                display: block;
            }
            .folder-select-error p {
                margin: 0;
                font-size: 0.875rem;
            }
            /* 提示文本样式 */
            .folder-select-hint {
                color: #64748b;
                font-size: 0.875rem;
                margin-top: 0.5rem;
                padding: 0 0.5rem;
            }
            .zoom-controls {
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(0, 0, 0, 0.7);
                padding: 10px;
                border-radius: 5px;
                display: flex;
                gap: 10px;
                z-index: 1050;
            }
            .zoom-controls button {
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                padding: 5px 15px;
                border-radius: 3px;
                cursor: pointer;
            }
            .zoom-controls button:hover {
                background: rgba(255, 255, 255, 0.3);
            }
            .zoom-info {
                color: white;
                padding: 5px 15px;
                font-size: 14px;
            }
            .preview-container {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 10px;
            }
            .preview-item {
                position: relative;
                width: 100px;
                height: 100px;
                border: 1px solid #ddd;
                border-radius: 5px;
                overflow: hidden;
                cursor: pointer;
                margin: 5px;
            }
            .preview-item img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.3s ease;
            }
            .preview-item .remove-btn {
                position: absolute;
                top: 5px;
                right: 5px;
                background: rgba(255, 0, 0, 0.7);
                color: white;
                border: none;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                line-height: 20px;
                text-align: center;
                cursor: pointer;
                font-size: 12px;
            }
            .preview-item .remove-btn:hover {
                background: rgba(255, 0, 0, 0.9);
            }
            .preview-item .progress-container {
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 4px;
                background: rgba(0, 0, 0, 0.1);
                display: none;
            }
            .preview-item .progress-bar {
                height: 100%;
                width: 0%;
                background: #28a745;
                transition: width 0.3s ease;
            }
            .preview-item.uploading {
                opacity: 0.8;
            }
            .preview-item.uploading .progress-container {
                display: block;
            }
            .preview-item.upload-success .progress-bar {
                background: #28a745;
            }
            .preview-item.upload-error .progress-bar {
                background: #dc3545;
            }
            .toast-container {
                position: fixed;
                top: 0;
                left: 50%;
                transform: translateX(-50%);
                z-index: 9999;
                pointer-events: none;
            }
            .toast {
                padding: 12px 24px;
                margin-top: -100px;
                background: rgba(40, 167, 69, 0.9);
                color: white;
                border-radius: 4px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                transition: all 0.3s ease-in-out;
                font-size: 16px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .toast.error {
                background: rgba(220, 53, 69, 0.9);
            }
            .toast.show {
                margin-top: 20px;
            }
            .toast i {
                font-size: 20px;
            }
            /* 验证码按钮样式 */
            .verify-btn {
                background: linear-gradient(45deg, #2196F3, #1976D2);
                color: white;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.3s ease;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                position: relative;
                overflow: hidden;
                height: 38px; /* 与Bootstrap输入框默认高度一致 */
                line-height: 26px; /* 确保文字垂直居中 */
            }

            .verify-btn:hover {
                background: linear-gradient(45deg, #1976D2, #1565C0);
                transform: translateY(-1px);
                box-shadow: 0 3px 6px rgba(0,0,0,0.3);
            }

            .verify-btn:active {
                transform: translateY(0);
                box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            }

            .verify-btn:disabled {
                background: #ccc;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
            }

            .verify-btn::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255,255,255,0.1);
                transition: transform 0.3s ease;
            }

            .verify-btn:hover::after {
                transform: scale(1.1);
            }

            /* 倒计时样式 */
            .countdown {
                color: #666;
                font-size: 14px;
                margin-left: 10px;
                line-height: 38px; /* 与按钮高度一致 */
            }

            /* 按钮容器样式 */
            .btn-container {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .folder-navigation {
                background: #fff;
                padding: 15px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                margin-bottom: 20px;
            }
            .folder-list {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: center;
            }
            .folder-item {
                display: inline-flex;
                align-items: center;
                padding: 8px 15px;
                background: #f8f9fa;
                border-radius: 4px;
                color: #495057;
                text-decoration: none;
                transition: all 0.3s ease;
                font-size: 14px;
            }
            .folder-item:hover {
                background: #e9ecef;
                color: #212529;
                text-decoration: none;
            }
            .folder-item.active {
                background: #2196F3;
                color: white;
            }
            .folder-item i {
                margin-right: 5px;
            }
            .create-folder-form {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #dee2e6;
            }
            .create-folder-form .form-control {
                min-width: 200px;
            }
            /* 文件夹容器样式优化 */
            .folder-container {
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                margin-bottom: 20px;
                display: flex;
                flex-direction: column;
                min-height: 500px;
                position: relative;
            }

            .folder-header {
                padding: 15px;
                border-bottom: 1px solid #dee2e6;
                flex-shrink: 0;
            }

            .folder-grid {
                flex: 1;
                padding: 15px;
                display: flex;
                flex-direction: column;
                gap: 10px;
                min-height: 200px;
                overflow-y: auto;
            }

            .pagination-container {
                border-top: 1px solid #dee2e6;
                background: #fff;
                padding: 15px;
                margin-top: auto;
                flex-shrink: 0;
            }

            .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin: 0;
                padding: 0;
            }

            @media (max-width: 768px) {
                .folder-container {
                    margin-bottom: 10px;
                }

                .folder-grid {
                    padding: 10px;
                }

                .pagination-container {
                    padding: 10px 5px;
                }

                .pagination {
                    gap: 5px;
                }
            }

            /* 分页按钮样式 */
            .pagination-btn {
                padding: 5px 15px;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                background: #fff;
                color: #333;
                cursor: pointer;
                transition: all 0.3s ease;
                min-width: 40px;
                text-align: center;
            }

            .pagination-btn:hover {
                background: #f8f9fa;
            }

            .pagination-btn.active {
                background: #2196F3;
                color: #fff;
                border-color: #2196F3;
            }

            .pagination-btn:disabled {
                opacity: 0.5;
                background: #f8f9fa;
                cursor: not-allowed;
            }

            #pageNumbers {
                display: flex;
                gap: 5px;
                flex-wrap: wrap;
                justify-content: center;
            }

            /* 移动端分页按钮优化 */
            @media (max-width: 768px) {
                .pagination-btn {
                    padding: 8px 12px;
                    font-size: 14px;
                    min-width: 36px;
                    height: 36px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    touch-action: manipulation;
                    -webkit-tap-highlight-color: transparent;
                }
            }

            /* 特小屏幕的分页优化 */
            @media (max-width: 375px) {
                .pagination-btn {
                    padding: 6px 10px;
                    font-size: 13px;
                    min-width: 32px;
                    height: 32px;
                }

                #pageNumbers {
                    gap: 3px;
                }
            }
            .folder-item-card {
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                background: #fff;
                transition: all 0.3s ease;
                cursor: pointer;
                width: 100%;
                padding: 0;
            }
            .folder-item-card:hover {
                transform: translateX(5px);
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .folder-link {
                text-decoration: none;
                color: inherit;
                display: flex;
                align-items: center;
                padding: 15px 20px;
                gap: 15px;
            }
            .folder-icon {
                font-size: 1.5rem;
                color: #ffd700;
                display: flex;
                align-items: center;
            }
            .folder-name {
                font-weight: 500;
                color: #333;
                flex-grow: 1;
                margin: 0;
                font-size: 1rem;
                /* 添加文件名溢出处理 */
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 200px;
            }
            
            /* 移动端适配 */
            @media (max-width: 768px) {
                .folder-link {
                    flex-wrap: wrap;
                    gap: 10px;
                }
                
                .folder-name {
                    max-width: 100%;
                    width: calc(100% - 50px); /* 减去图标宽度和间距 */
                    order: 1;
                }
                
                .folder-icon {
                    order: 0;
                }
                
                .folder-info {
                    width: 100%;
                    order: 2;
                    justify-content: space-between;
                    margin-top: 5px;
                }
                
                .image-info {
                    flex-wrap: wrap;
                }
                
                .image-actions {
                    width: 100%;
                    padding-left: 0;
                    margin-top: 10px;
                    justify-content: flex-start;
                    gap: 10px;
                }
                
                .copy-link-btn,
                .move-to-folder-btn {
                    padding: 6px 12px;
                    font-size: 14px;
                    white-space: nowrap;
                }
                
                .folder-item-card .folder-link {
                    padding: 12px 15px;
                }
                
                .image-item img {
                    width: 80px;
                    height: 60px;
                }
                
                .image-meta {
                    flex-direction: column;
                    gap: 5px;
                }
                
                /* 优化移动端按钮布局 */
                .btn-container {
                    flex-direction: column;
                    width: 100%;
                }
                
                .btn-container .btn {
                    width: 100%;
                    margin-bottom: 10px;
                }
                
                /* 优化移动端文件夹信息显示 */
                .folder-info {
                    font-size: 0.8rem;
                }
                
                .folder-count,
                .folder-date {
                    display: flex;
                    align-items: center;
                    gap: 4px;
                }
                
                /* 优化移动端模态框 */
                .modal-dialog {
                    margin: 10px;
                    max-width: calc(100% - 20px);
                }
                
                .folder-select-item {
                    flex-wrap: wrap;
                    padding: 10px;
                }
                
                .folder-select-item .folder-name {
                    width: calc(100% - 30px);
                }
                
                .folder-select-item .folder-count {
                    width: 100%;
                    margin-top: 5px;
                }
            }
            
            /* 针对特小屏幕的优化 */
            @media (max-width: 375px) {
                .folder-name {
                    font-size: 0.9rem;
                }
                
                .folder-info {
                    font-size: 0.75rem;
                }
                
                .folder-link {
                    padding: 10px;
                }
                
                .image-actions button {
                    padding: 4px 8px;
                    font-size: 12px;
                }
            }
            .folder-info {
                color: #666;
                font-size: 0.9rem;
                display: flex;
                align-items: center;
                gap: 15px;
                margin-left: auto;
                flex-shrink: 0;
            }
            .folder-date {
                color: #888;
            }
            .bi-folder-fill {
                filter: drop-shadow(1px 1px 1px rgba(0,0,0,0.1));
            }
            .btn-outline-primary {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                font-size: 16px;
                transition: all 0.3s ease;
            }

            .btn-outline-primary:hover {
                transform: translateX(-5px);
            }

            .btn-outline-primary i {
                font-size: 18px;
            }
            .context-menu {
                position: fixed;
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                padding: 8px 0;
                display: none;
                z-index: 1000;
            }

            .context-menu-item {
                padding: 8px 16px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: background-color 0.2s;
            }

            .context-menu-item:hover {
                background-color:rgba(176, 175, 175, 0.63);
            }

            .context-menu-item i {
                font-size: 16px;
            }

            .text-danger:hover {
                background-color:rgba(220, 53, 70, 0.53);
                color: white;
            }
            .folder-item-card.editing {
                background: #f8f9fa;
                border: 2px dashed #dee2e6;
            }
            .folder-item-card.editing .folder-link {
                display: none;
            }
            .folder-item-card.editing .folder-edit-form {
                display: flex;
                align-items: center;
                padding: 15px 20px;
                gap: 10px;
            }
            .folder-edit-form {
                display: none;
            }
            .folder-edit-input {
                flex-grow: 1;
                border: none;
                background: transparent;
                font-size: 1rem;
                padding: 5px;
                outline: none;
            }
            .folder-edit-input:focus {
                border-bottom: 1px solid #2196F3;
            }
            .image-actions {
                display: flex;
                gap: 10px;
                margin-left: auto;
                padding-left: 20px;
            }

            .image-actions button {
                padding: 4px 8px;
            font-size: 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: #fff;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .image-actions button:hover {
            background: #f8f9fa;
            color: #495057;
        }

        .image-actions .copy-link-btn.copied {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }

        .folder-item-card .folder-link {
            cursor: default;
        }

        .folder-item-card a.folder-link {
            cursor: pointer;
        }

        .folder-select-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .folder-select-item {
            padding: 12px 15px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-bottom: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
        }

        .folder-select-item:hover {
            background-color: #f8f9fa;
        }

        .folder-select-item.selected {
            background-color: #e3f2fd;
            border-color: #90caf9;
        }

        .folder-select-item i {
            color: #ffd700;
            font-size: 1.2rem;
        }

        .folder-select-item .folder-name {
            flex-grow: 1;
            font-weight: 500;
        }

        .folder-select-item .folder-count {
            color: #6c757d;
            font-size: 0.9rem;
        }

        #confirmMove:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* 添加面包屑导航样式 */
        .breadcrumb {
            background: #fff;
            padding: 10px 15px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .breadcrumb-item a {
            color: #2196F3;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .breadcrumb-item.active {
            color: #6c757d;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: #6c757d;
        }

        /* 图片预览模态框样式 */
        #imageModal .modal-dialog {
            max-width: 90vw;
            max-height: 90vh;
            margin: 0 auto;
            overflow: hidden;
        }

        #imageModal .modal-content {
            background: transparent;
            border: none;
            overflow: hidden;
        }

        #imageModal .modal-body {
            display: flex;
            justify-content: center;
            align-items: center;
            background: transparent;
            overflow: hidden;
            padding: 0;
        }

        .image-preview-container {
            position: relative;
            width: 100%;
            height: 85vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            cursor: grab;
        }

        .image-preview-container.grabbing {
            cursor: grabbing;
        }

        #modalImage {
            max-width: 100%;
            max-height: 85vh;
            object-fit: contain;
            transition: transform 0.3s ease;
            transform-origin: center center;
            user-select: none;
            -webkit-user-drag: none;
        }

        .zoom-controls {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            padding: 10px 15px;
            border-radius: 8px;
            display: flex;
            gap: 15px;
            z-index: 1060;
        }

        .zoom-controls button {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 5px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .zoom-controls button:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .zoom-info {
            color: white;
            padding: 5px 10px;
            min-width: 60px;
            text-align: center;
        }

        /* 移动端适配 */
        @media (max-width: 768px) {
            #imageModal .modal-dialog {
                max-width: 95vw;
                margin: 10px;
            }

            .image-preview-container {
                height: 80vh;
            }

            #modalImage {
                max-height: 80vh;
            }

            .zoom-controls {
                padding: 8px 12px;
                gap: 10px;
            }

            .zoom-controls button {
                padding: 4px 12px;
                font-size: 14px;
            }
        }

        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .spinner-container {
            text-align: center;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 0.25em;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            margin-top: 1rem;
            color: #6c757d;
        }

        .loading-dots {
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .loading-dots span {
            animation: dots 1.5s infinite;
            opacity: 0;
        }

        .loading-dots span:nth-child(1) {
            animation-delay: 0.2s;
        }

        .loading-dots span:nth-child(2) {
            animation-delay: 0.4s;
        }

        .loading-dots span:nth-child(3) {
            animation-delay: 0.6s;
        }

        .loading-message {
            font-size: 1.1rem;
            font-weight: 500;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes dots {
            0% { opacity: 0; }
            50% { opacity: 1; }
            100% { opacity: 0; }
        }

        .folder-content {
            transition: opacity 0.5s ease-in-out;
        }

        .image-actions {
            margin-top: 10px;
            display: flex;
            gap: 8px;
        }

        .copy-link-btn, .move-to-folder-btn {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            background-color: #f8f9fa;
            color: #495057;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .copy-link-btn:hover, .move-to-folder-btn:hover {
            background-color: #e9ecef;
            color: #212529;
        }

        .copy-link-btn i, .move-to-folder-btn i {
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="container py-4">
    <div class="container py-4">
        <!-- 添加 Toast 提示组件 -->
        <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 9999;">
            <div id="toast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-content">
                    <div class="toast-body">
                        <i class="bi"></i>
                        <span class="toast-message"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 添加上传进度提示 -->
        <div id="uploadProgress" class="position-fixed top-0 start-0 w-100 h-100" style="z-index: 9999; display: none; background: rgba(0,0,0,0.5);">
            <div class="position-absolute top-50 start-50 translate-middle bg-white p-4 rounded-3 shadow-lg text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">上传中...</span>
                </div>
                <div id="uploadProgressText" class="text-primary">正在上传...</div>
            </div>
        </div>

        <div class="header">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="header-title">
                        <svg t="1745196265712" class="header-icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="1656">
                            <path d="M125.9 185h772.2v653.9H125.9z" fill="#1F53CC" p-id="1657"></path>
                            <path d="M164.7 217.2h694.6v516.7H164.7z" fill="#FECD44" p-id="1658"></path>
                            <path d="M458.9 734l-8.6-43.8-101.5-102.8-135 146.6z" fill="#FC355D" p-id="1659"></path>
                            <path d="M306.9 348.7m-66.7 0a66.7 66.7 0 1 0 133.4 0 66.7 66.7 0 1 0-133.4 0Z" fill="#FFFFFF" p-id="1660"></path>
                            <path d="M384.6 734h474.7V608.8L687.8 400.1z" fill="#FC355D" p-id="1661"></path>
                            <path d="M422.5 662l-37.9 72 52.1-57.5z" fill="#BF2847" p-id="1662"></path>
                            <path d="M302.5 778.9h418.9v16.7H302.5z" fill="#00F0D4" p-id="1663"></path>
                        </svg>
                        <span class="glitch" data-text="图床系统" onclick="window.location.href='/book/website.html'">
                            图床系统
                            <span></span>
                        </span>
                    </div>
                    <div class="header-actions">
                        <?php if (isset($_SESSION['user_level']) && $_SESSION['user_level'] === '管理员'): ?>
                        <a href="/<?php echo ADMIN_PATH; ?>" class="admin-link">
                            <i class="bi bi-gear-fill"></i>
                            后台管理
                        </a>
                        <?php endif; ?>
                        <div class="user-info">
                            <a href="../views/profile" class="user-name text-decoration-none">
                                欢迎，<?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <a href="../logout" class="logout-link">退出登录</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="upload-form">
            <h2 class="h4 mb-3">上传新图片</h2>
            <form method="post" enctype="multipart/form-data" class="row g-3" id="uploadForm">
                <div class="col-auto">
                    <input type="file" name="images[]" accept="image/*" multiple required class="form-control" id="imageInput" max="6">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">上传</button>
                </div>
                <?php if (isset($_GET['folder']) && $_GET['folder'] !== 'unclassified'): ?>
                <input type="hidden" name="folder_id" value="<?php echo htmlspecialchars($_GET['folder']); ?>">
                <?php endif; ?>
            </form>
            <div class="preview-container" id="previewContainer"></div>
            <div class="mt-2 text-muted small">
                支持拖拽文件或 Ctrl+V 粘贴图片，一次最多上传6张图片
            </div>
            <div class="alert alert-warning mt-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>重要提示：</strong>请勿上传违规图片（包括但不限于色情、暴力、政治敏感等内容），违者将进行封号处理！
            </div>
        </div>
            <?php if (isset($_GET['folder'])): ?>
        <div class="container mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="/views/index" class="text-decoration-none">
                            <i class="bi bi-house-door"></i> 主页
                        </a>
                    </li>
                    <?php if ($_GET['folder'] === 'unclassified'): ?>
                        <li class="breadcrumb-item active">未分类图片</li>
                    <?php else: ?>
                        <?php if (isset($folder) && is_array($folder) && isset($folder['name'])): ?>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($folder['name']); ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item active">未知文件夹</li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ol>
            </nav>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['folder'])): ?>
        <div class="folder-container">
            <div class="folder-grid" id="folderContent">
                <div class="loading-spinner" style="display: none;">
                    <div class="spinner-container">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">加载中...</span>
                        </div>
                        <div class="loading-text">
                            <div class="loading-dots">
                                <span>.</span>
                                <span>.</span>
                                <span>.</span>
                            </div>
                            <div class="loading-message">正在加载文件夹内容</div>
                        </div>
                    </div>
                </div>
                <div class="folder-content" style="display: none; opacity: 0; transition: opacity 0.5s ease-in-out;">
                    <?php if (empty($images)): ?>
                        <div class="alert alert-info">
                            当前文件夹没有任何图片
                        </div>
                    <?php else: ?>
                        <?php foreach ($images as $image): ?>
                            <div class="folder-item-card">
                                <div class="folder-link">
                                    <div class="folder-icon">
                                        <img src="<?php echo htmlspecialchars($image['filename']); ?>" 
                                             alt="<?php echo htmlspecialchars($image['original_name']); ?>"
                                             style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                    </div>
                                    <div class="folder-name">
                                        <?php echo htmlspecialchars($image['original_name']); ?>
                                    </div>
                                    <div class="folder-info">
                                        <span class="folder-count">
                                            <i class="bi bi-file-image"></i> 
                                            <?php echo number_format($image['size'] / 1024, 2); ?> KB
                                        </span>
                                        <span class="folder-date">
                                            <i class="bi bi-calendar"></i>
                                            <?php echo date('Y-m-d', strtotime($image['upload_date'])); ?>
                                        </span>
                                    </div>
                                    <div class="image-actions">
                                        <button class="copy-link-btn" data-url="<?php 
                                            $url = 'http://' . $_SERVER['HTTP_HOST'] . str_replace('../', '/', $image['filename']);
                                            echo htmlspecialchars(preg_replace('/\.[^.]+$/', '', $url));
                                        ?>">
                                            <i class="bi bi-clipboard"></i>复制链接
                                        </button>
                                        <button class="move-to-folder-btn" data-image-id="<?php echo $image['id']; ?>">
                                            <i class="bi bi-folder"></i>移动到文件夹
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const folderContent = document.getElementById('folderContent');
            const loadingSpinner = folderContent.querySelector('.loading-spinner');
            const content = folderContent.querySelector('.folder-content');
            
            // 显示加载状态
            loadingSpinner.style.display = 'flex';
            content.style.display = 'block';
            content.style.opacity = '0';
            
            // 模拟加载延迟，确保内容完全加载
            setTimeout(function() {
                loadingSpinner.style.display = 'none';
                content.style.opacity = '1';
            }, 800);
        });
        </script>
        <?php endif; ?>

        <?php if (empty($images) && empty($folders)): ?>
            <div class="folder-container" id="folderContainer">
                <div class="folder-header">
                    <h5 class="mb-0">我的文件夹</h5>
                </div>
                <div class="folder-grid">
                    <div class="alert alert-info">
                        当前没有任何内容，请上传图片或创建文件夹
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php if (!$current_folder): ?>
            <div class="folder-container" id="folderContainer">
                <div class="folder-header">
                    <h5 class="mb-0">我的文件夹</h5>
                </div>
                <div class="folder-grid">
                    <?php if (empty($folders) && empty($images)): ?>
                        <div class="alert alert-info">
                            当前没有任何内容，请上传图片或创建文件夹
                        </div>
                    <?php else: ?>
                        <?php 
                        $items_per_page = 5;
                        $current_page = 1;
                        $total_items = count($folders);
                        $start_index = ($current_page - 1) * $items_per_page;
                        $visible_folders = array_slice($folders, $start_index, $items_per_page);
                        
                        foreach ($folders as $index => $folder): 
                            $is_visible = $index < $items_per_page ? '' : 'style="display: none;"';
                        ?>
                            <div class="folder-item-card" data-folder-id="<?php echo $folder['id']; ?>" 
                                 data-folder-name="<?php echo htmlspecialchars($folder['name']); ?>" <?php echo $is_visible; ?>>
                                <a href="/views/index?folder=<?php echo $folder['id']; ?>" class="folder-link">
                                    <div class="folder-icon">
                                        <i class="bi bi-folder-fill"></i>
                                    </div>
                                    <div class="folder-name">
                                        <?php echo htmlspecialchars($folder['name']); ?>
                                    </div>
                                    <div class="folder-info">
                                        <span class="folder-count">
                                            <i class="bi bi-image"></i> 
                                            <?php 
                                                $stmt = $db->prepare("SELECT COUNT(*) FROM images WHERE folder_id = ?");
                                                $stmt->execute([$folder['id']]);
                                                echo $stmt->fetchColumn() . ' 张图片';
                                            ?>
                                        </span>
                                        <span class="folder-date">
                                            <i class="bi bi-calendar"></i>
                                            <?php echo date('Y-m-d', strtotime($folder['created_at'])); ?>
                                        </span>
                                    </div>
                                </a>
                                <div class="folder-edit-form">
                                    <input type="text" class="folder-edit-input" value="<?php echo htmlspecialchars($folder['name']); ?>" placeholder="输入文件夹名称">
                                    <button type="button" class="btn btn-sm btn-outline-secondary cancel-edit">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php 
                        // 获取未分类图片
                        $unclassified_stmt = $db->prepare("SELECT * FROM images WHERE user_id = ? AND folder_id IS NULL ORDER BY upload_date DESC");
                        $unclassified_stmt->execute([$_SESSION['user_id']]);
                        $unclassified_images = $unclassified_stmt->fetchAll();
                        
                        // 计算剩余的显示槽位
                        $remaining_slots = $items_per_page - min($items_per_page, count($visible_folders));
                        $visible_unclassified = array_slice($unclassified_images, 0, $remaining_slots);
                        
                        foreach ($unclassified_images as $index => $image): 
                            // 如果第一页的文件夹数量已经达到每页限制，则隐藏所有未分类图片
                            $should_hide = count($visible_folders) >= $items_per_page || $index >= $remaining_slots;
                            $is_visible = $should_hide ? 'style="display: none;"' : '';
                        ?>
                            <div class="folder-item-card" <?php echo $is_visible; ?>>
                                <div class="folder-link">
                                    <div class="folder-icon">
                                        <img src="<?php echo htmlspecialchars($image['filename']); ?>" 
                                             alt="<?php echo htmlspecialchars($image['original_name']); ?>"
                                             style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                    </div>
                                    <div class="folder-name">
                                        <?php echo htmlspecialchars($image['original_name']); ?>
                                    </div>
                                    <div class="folder-info">
                                        <span class="folder-count">
                                            <i class="bi bi-file-image"></i> 
                                            <?php echo number_format($image['size'] / 1024, 2); ?> KB
                                        </span>
                                        <span class="folder-date">
                                            <i class="bi bi-calendar"></i>
                                            <?php echo date('Y-m-d', strtotime($image['upload_date'])); ?>
                                        </span>
                                    </div>
                                    <div class="image-actions">
                                        <button class="copy-link-btn" data-url="<?php 
                                            $url = 'http://' . $_SERVER['HTTP_HOST'] . str_replace('../', '/', $image['filename']);
                                            echo htmlspecialchars(preg_replace('/\.[^.]+$/', '', $url));
                                        ?>">
                                            <i class="bi bi-clipboard"></i>复制链接
                                        </button>
                                        <button class="move-to-folder-btn" data-image-id="<?php echo $image['id']; ?>">
                                            <i class="bi bi-folder"></i>移动到文件夹
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($folders) || !empty($unclassified_images)): ?>
                <div class="pagination-container" <?php echo $current_folder ? 'style="display: none;"' : ''; ?>>
                    <div class="pagination">
                        <button class="pagination-btn" id="prevPage" disabled>上一页</button>
                        <div id="pageNumbers"></div>
                        <button class="pagination-btn" id="nextPage">下一页</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- 移动到文件夹的模态框 -->
        <div class="modal fade" id="moveToFolderModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">移动到文件夹</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="folder-select-list">
                            <?php 
                            // 过滤掉当前文件夹
                            $filtered_folders = array_filter($folders, function($folder) use ($current_folder) {
                                return $folder['id'] != $current_folder;
                            });
                            
                            if (empty($filtered_folders)): ?>
                                <div class="alert alert-info">
                                    没有其他可用的文件夹，请先创建新文件夹
                                </div>
                            <?php else: ?>
                                <?php foreach ($filtered_folders as $folder): ?>
                                    <div class="folder-select-item" data-folder-id="<?php echo $folder['id']; ?>">
                                        <i class="bi bi-folder-fill"></i>
                                        <span class="folder-name"><?php echo htmlspecialchars($folder['name']); ?></span>
                                        <span class="folder-count">
                                            <?php 
                                                $stmt = $db->prepare("SELECT COUNT(*) FROM images WHERE folder_id = ?");
                                                $stmt->execute([$folder['id']]);
                                                echo $stmt->fetchColumn() . ' 张图片';
                                            ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="button" class="btn btn-primary" id="confirmMove" disabled>确认移动</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 图片模态框 -->
        <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-body p-0">
                        <div class="image-preview-container">
                            <img src="" alt="预览图片" id="modalImage">
                        </div>
                    </div>
                </div>
            </div>
            <div class="zoom-controls">
                <button id="zoomOut">-</button>
                <span class="zoom-info">100%</span>
                <button id="zoomIn">+</button>
                <button id="resetZoom">重置</button>
            </div>
        </div>

        <!-- 右键菜单 -->
        <div class="context-menu" id="folderContextMenu">
            <div class="context-menu-item" id="newFolderMenuItem">
                <i class="bi bi-folder-plus"></i> 新建文件夹
            </div>
        </div>

        <div class="context-menu" id="folderItemContextMenu">
            <div class="context-menu-item" id="renameFolderMenuItem">
                <i class="bi bi-pencil"></i> 重命名
            </div>
            <div class="context-menu-item text-danger" id="deleteFolderMenuItem">
                <i class="bi bi-trash"></i> 删除
            </div>
        </div>

        <!-- 重命名对话框 -->
        <div class="modal fade" id="renameFolderModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">重命名文件夹</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="text" class="form-control" id="newFolderName">
                        <input type="hidden" id="renameFolderId">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="button" class="btn btn-primary" id="confirmRename">确定</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 添加Bootstrap JS 和 Popper.js -->
        <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
        <script>
            // 初始化 Toast 组件
            const toastEl = document.getElementById('toast');
            const toast = new bootstrap.Toast(toastEl, {
                delay: 3000
            });

            function showToast(message, type = 'success') {
                const icon = toastEl.querySelector('.bi');
                const messageEl = toastEl.querySelector('.toast-message');
                
                messageEl.textContent = message;
                toastEl.classList.remove('error');
                
                if (type === 'success') {
                    icon.className = 'bi bi-check-circle-fill';
                } else {
                    toastEl.classList.add('error');
                    icon.className = 'bi bi-x-circle-fill';
                }
                
                toast.show();
            }

            // 上传处理
            document.getElementById('uploadForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const files = document.getElementById('imageInput').files;
                
                if (files.length === 0) {
                    showToast('请选择要上传的图片', 'error');
                    return;
                }

                // 显示上传进度
                const uploadProgress = document.getElementById('uploadProgress');
                const uploadProgressText = document.getElementById('uploadProgressText');
                uploadProgress.style.display = 'flex';
                
                const formData = new FormData(this);
                const xhr = new XMLHttpRequest();
                
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        uploadProgressText.textContent = `正在上传... ${percent}%`;
                    }
                };
                
                xhr.onload = function() {
                    uploadProgress.style.display = 'none';
                    
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                showToast(response.message || '上传成功', 'success');
                                // 延迟刷新页面
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                showToast(response.message || '上传失败', 'error');
                                console.error('Upload errors:', response.errors);
                            }
                        } catch (e) {
                            showToast('服务器响应格式错误', 'error');
                            console.error('Parse error:', e);
                        }
                    } else {
                        showToast('上传失败: ' + xhr.statusText, 'error');
                    }
                };
                
                xhr.onerror = function() {
                    uploadProgress.style.display = 'none';
                    showToast('网络错误，请重试', 'error');
                };
                
                xhr.open('POST', '../controllers/upload.php', true);
                xhr.send(formData);
            });

            // 多文件预览功能
            document.getElementById('imageInput').addEventListener('change', function(e) {
                const files = e.target.files;
                const previewContainer = document.getElementById('previewContainer');
                previewContainer.innerHTML = ''; // 清空预览容器

                if (files.length === 0) {
                    this.value = '';
                    return;
                }

                // 检查文件数量
                if (files.length > 6) {
                    showToast('一次最多只能上传6张图片', 'error');
                    this.value = '';
                    return;
                }

                Array.from(files).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'preview-item';
                            previewItem.innerHTML = `
                                <img src="${e.target.result}" alt="预览图片">
                                <button type="button" class="remove-btn" data-index="${index}">×</button>
                                <div class="progress-container">
                                    <div class="progress-bar"></div>
                                </div>
                            `;
                            previewContainer.appendChild(previewItem);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            });

            // 使用事件委托处理删除按钮点击
            document.getElementById('previewContainer').addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-btn')) {
                    e.stopPropagation();
                    const index = parseInt(e.target.dataset.index);
                    const input = document.getElementById('imageInput');
                    const dt = new DataTransfer();
                    
                    // 从文件列表中移除选中的文件
                    Array.from(input.files).forEach((file, i) => {
                        if (i !== index) {
                            dt.items.add(file);
                        }
                    });
                    
                    // 更新文件输入框的文件列表
                    input.files = dt.files;
                    
                    // 重新触发change事件以更新预览
                    const event = new Event('change', { bubbles: true });
                    input.dispatchEvent(event);
                }
            });

            // 图片缩放功能
            let currentScale = 1;
            const modalImage = document.getElementById('modalImage');
            const zoomInfo = document.querySelector('.zoom-info');
            const ZOOM_FACTOR = 0.2; // 每次缩放的比例

            function updateZoom() {
                modalImage.style.transform = `scale(${currentScale})`;
                zoomInfo.textContent = `${Math.round(currentScale * 100)}%`;
            }

            // 放大按钮
            document.getElementById('zoomIn').addEventListener('click', () => {
                currentScale += ZOOM_FACTOR;
                updateZoom();
            });

            // 缩小按钮
            document.getElementById('zoomOut').addEventListener('click', () => {
                if (currentScale > ZOOM_FACTOR) {
                    currentScale -= ZOOM_FACTOR;
                    updateZoom();
                }
            });

            // 重置缩放
            document.getElementById('resetZoom').addEventListener('click', () => {
                currentScale = 1;
                updateZoom();
            });

            // 鼠标滚轮缩放
            document.getElementById('imageModal').addEventListener('wheel', (e) => {
                e.preventDefault();
                if (e.deltaY < 0) {
                    // 向上滚动，放大
                    currentScale += ZOOM_FACTOR;
                } else {
                    // 向下滚动，缩小
                    if (currentScale > ZOOM_FACTOR) {
                        currentScale -= ZOOM_FACTOR;
                    }
                }
                updateZoom();
            });

            // 为预览图片添加点击事件
            document.getElementById('previewContainer').addEventListener('click', function(e) {
                const img = e.target.closest('.preview-item img');
                if (img) {
                    const modalImage = document.getElementById('modalImage');
                    modalImage.src = img.src;
                    currentScale = 1; // 重置缩放
                    updateZoom();
                    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                    modal.show();
                }
            });

            // 为已上传的图片添加点击事件
            document.querySelectorAll('.image-item img').forEach(img => {
                img.addEventListener('click', function(e) {
                    const modalImage = document.getElementById('modalImage');
                    modalImage.src = this.src;
                    currentScale = 1; // 重置缩放
                    updateZoom();
                    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                    modal.show();
                });
            });

            // 处理粘贴事件
            document.addEventListener('paste', function(e) {
                e.preventDefault();
                const items = e.clipboardData.items;
                const dt = new DataTransfer();
                
                // 如果已经有选择的文件，先保留它们
                const input = document.getElementById('imageInput');
                if (input.files.length > 0) {
                    Array.from(input.files).forEach(file => {
                        dt.items.add(file);
                    });
                }

                // 处理粘贴的图片
                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        const file = items[i].getAsFile();
                        if (file) {
                            // 检查总文件数量
                            if (dt.files.length >= 6) {
                                showToast('一次最多只能上传6张图片', 'error');
                                break;
                            }
                            
                            // 生成唯一的文件名
                            const timestamp = new Date().getTime();
                            const newFile = new File([file], `pasted_image_${timestamp}.png`, {
                                type: file.type
                            });
                            dt.items.add(newFile);
                        }
                    }
                }

                // 更新文件输入框并触发change事件
                if (dt.files.length > 0) {
                    input.files = dt.files;
                    const event = new Event('change', { bubbles: true });
                    input.dispatchEvent(event);
                }
            });

            // 处理拖拽
            const dropZone = document.querySelector('.upload-form');
            
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('border', 'border-primary');
            });

            dropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.remove('border', 'border-primary');
            });

            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.remove('border', 'border-primary');

                const dt = new DataTransfer();
                const input = document.getElementById('imageInput');

                // 保留现有文件
                if (input.files.length > 0) {
                    Array.from(input.files).forEach(file => {
                        dt.items.add(file);
                    });
                }

                // 添加拖拽的文件
                Array.from(e.dataTransfer.files).forEach(file => {
                    if (file.type.startsWith('image/')) {
                        // 检查总文件数量
                        if (dt.files.length >= 6) {
                            showToast('一次最多只能上传6张图片', 'error');
                            return;
                        }
                        dt.items.add(file);
                    }
                });

                // 更新文件输入框并触发change事件
                if (dt.files.length > 0) {
                    input.files = dt.files;
                    const event = new Event('change', { bubbles: true });
                    input.dispatchEvent(event);
                }
            });

            // 复制链接功能
            document.querySelectorAll('.copy-link-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault(); // 阻止默认行为
                    e.stopPropagation(); // 阻止事件冒泡
                    const url = this.dataset.url;
                    
                    // 创建临时输入框
                    const input = document.createElement('input');
                    input.value = url;
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    document.body.removeChild(input);
                    
                    // 更新按钮状态
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check"></i>已复制';
                    this.classList.add('copied');
                    
                    // 2秒后恢复按钮状态
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('copied');
                    }, 2000);

                    // 显示提示信息
                    showToast('链接已复制到剪贴板', 'success');
                    
                    return false; // 阻止链接的默认行为
                });
            });

            // 修改图片点击事件，确保不会与复制按钮冲突
            function bindImageClickEvents() {
                document.querySelectorAll('.folder-item-card img, .image-item img').forEach(img => {
                    img.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const modalImage = document.getElementById('modalImage');
                        modalImage.src = this.src;
                        currentScale = 1; // 重置缩放
                        updateZoom();
                        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                        modal.show();
                    });
                });
            }

            // 在页面加载完成后绑定事件
            document.addEventListener('DOMContentLoaded', function() {
                bindImageClickEvents();
            });

            function sendVerificationCode() {
                const email = document.querySelector('input[name="email"]').value;
                const btn = document.querySelector('.verify-btn');
                const countdown = document.querySelector('.countdown');
                
                if (!email) {
                    alert('请输入邮箱地址');
                    return;
                }
                
                // 禁用按钮
                btn.disabled = true;
                
                // 发送验证码请求
                fetch('../controllers/verify.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `email=${encodeURIComponent(email)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 显示倒计时
                        let timeLeft = 60;
                        countdown.style.display = 'inline';
                        countdown.textContent = `${timeLeft}秒后重试`;
                        
                        const timer = setInterval(() => {
                            timeLeft--;
                            countdown.textContent = `${timeLeft}秒后重试`;
                            
                            if (timeLeft <= 0) {
                                clearInterval(timer);
                                btn.disabled = false;
                                countdown.style.display = 'none';
                            }
                        }, 1000);
                        
                        alert('验证码已发送，请查收邮件');
                    } else {
                        alert(data.message || '发送失败，请稍后重试');
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('发送失败，请稍后重试');
                    btn.disabled = false;
                });
            }

            // 处理移动图片到文件夹
            document.querySelectorAll('.move-to-folder').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const imageId = this.dataset.imageId;
                    document.getElementById('moveImageId').value = imageId;
                    const modal = new bootstrap.Modal(document.getElementById('moveToFolderModal'));
                    modal.show();
                });
            });

            document.addEventListener('DOMContentLoaded', function() {
                const folderContainer = document.getElementById('folderContainer');
                const folderContextMenu = document.getElementById('folderContextMenu');
                const folderItemContextMenu = document.getElementById('folderItemContextMenu');
                const renameFolderModal = new bootstrap.Modal(document.getElementById('renameFolderModal'));
                let currentFolderId = null;

                // 阻止默认右键菜单
                document.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                });

                // 点击其他地方关闭右键菜单
                document.addEventListener('click', function() {
                    folderContextMenu.style.display = 'none';
                    folderItemContextMenu.style.display = 'none';
                });

                // 文件夹容器右键菜单
                document.addEventListener('contextmenu', function(e) {
                    const folderItem = e.target.closest('.folder-item-card');
                    const folderContainer = document.getElementById('folderContainer');
                    const folderGrid = folderContainer.querySelector('.folder-grid');
                    
                    // 隐藏所有右键菜单
                    folderContextMenu.style.display = 'none';
                    folderItemContextMenu.style.display = 'none';

                    if (folderItem) {
                        // 在文件夹上右键
                        currentFolderId = folderItem.dataset.folderId;
                        folderItemContextMenu.style.left = e.pageX + 'px';
                        folderItemContextMenu.style.top = e.pageY + 'px';
                        folderItemContextMenu.style.display = 'block';
                    } else if (folderGrid && folderGrid.contains(e.target)) {
                        // 在文件夹网格空白处右键
                        folderContextMenu.style.left = e.pageX + 'px';
                        folderContextMenu.style.top = e.pageY + 'px';
                        folderContextMenu.style.display = 'block';
                    }
                });

                // 新建文件夹
                document.getElementById('newFolderMenuItem').addEventListener('click', function() {
                    const folderGrid = document.querySelector('.folder-grid');
                    const folderCards = document.querySelectorAll('.folder-item-card');
                    const totalPages = Math.ceil(folderCards.length / ITEMS_PER_PAGE);
                    const currentPageFolders = Array.from(folderCards).filter((card, index) => {
                        return index >= (currentPage - 1) * ITEMS_PER_PAGE && index < currentPage * ITEMS_PER_PAGE;
                    });

                    // 如果当前页已满5个文件夹，自动进入新页面
                    if (currentPageFolders.length >= ITEMS_PER_PAGE) {
                        currentPage = totalPages + 1;
                        // 更新分页按钮
                        const pageNumbers = document.getElementById('pageNumbers');
                        const newPageBtn = document.createElement('button');
                        newPageBtn.className = 'pagination-btn active';
                        newPageBtn.textContent = currentPage;
                        newPageBtn.onclick = () => goToPage(currentPage);
                        pageNumbers.querySelectorAll('.pagination-btn').forEach(btn => btn.classList.remove('active'));
                        pageNumbers.appendChild(newPageBtn);
                        
                        // 更新上一页/下一页按钮状态
                        document.getElementById('prevPage').disabled = false;
                        document.getElementById('nextPage').disabled = true;
                        
                        // 隐藏所有文件夹
                        folderCards.forEach(card => card.style.display = 'none');
                    }

                    const newFolderCard = document.createElement('div');
                    newFolderCard.className = 'folder-item-card editing';
                    newFolderCard.style.display = ''; // 确保新文件夹可见
                    newFolderCard.innerHTML = `
                        <a href="#" class="folder-link" style="display: none;">
                            <div class="folder-icon">
                                <i class="bi bi-folder-fill"></i>
                            </div>
                            <div class="folder-name">新建文件夹</div>
                            <div class="folder-info">
                                <span class="folder-count"><i class="bi bi-image"></i> 0 张图片</span>
                                <span class="folder-date"><i class="bi bi-calendar"></i> ${new Date().toISOString().split('T')[0]}</span>
                            </div>
                        </a>
                        <div class="folder-edit-form">
                            <input type="text" class="folder-edit-input" value="新建文件夹" placeholder="输入文件夹名称">
                            <button type="button" class="btn btn-sm btn-outline-secondary cancel-edit">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    `;
                    
                    // 插入到文件夹列表的开头
                    folderGrid.insertBefore(newFolderCard, folderGrid.firstChild);
                    
                    // 自动聚焦输入框
                    const input = newFolderCard.querySelector('.folder-edit-input');
                    input.focus();
                    input.select();
                    
                    // 处理输入框事件
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            createFolder(this.value);
                        } else if (e.key === 'Escape') {
                            newFolderCard.remove();
                            if (currentPageFolders.length === 0) {
                                goToPage(Math.max(1, currentPage - 1));
                            }
                        }
                    });
                    
                    // 处理取消按钮
                    newFolderCard.querySelector('.cancel-edit').addEventListener('click', function() {
                        newFolderCard.remove();
                        if (currentPageFolders.length === 0) {
                            goToPage(Math.max(1, currentPage - 1));
                        }
                    });
                    
                    // 处理失去焦点
                    input.addEventListener('blur', function() {
                        if (this.value.trim() === '') {
                            newFolderCard.remove();
                            if (currentPageFolders.length === 0) {
                                goToPage(Math.max(1, currentPage - 1));
                            }
                        } else {
                            createFolder(this.value);
                        }
                    });
                });

                // 重命名文件夹
                document.getElementById('renameFolderMenuItem').addEventListener('click', function() {
                    const folderItem = document.querySelector(`.folder-item-card[data-folder-id="${currentFolderId}"]`);
                    if (!folderItem) return;
                    
                    // 保存原始名称
                    const originalName = folderItem.dataset.folderName;
                    
                    // 切换到编辑模式
                    folderItem.classList.add('editing');
                    const input = folderItem.querySelector('.folder-edit-input');
                    input.value = originalName;
                    input.focus();
                    input.select();
                    
                    // 处理输入框事件
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            renameFolder(this.value);
                        } else if (e.key === 'Escape') {
                            cancelRename();
                        }
                    });
                    
                    // 处理取消按钮
                    folderItem.querySelector('.cancel-edit').addEventListener('click', cancelRename);
                    
                    // 处理失去焦点
                    input.addEventListener('blur', function() {
                        if (this.value.trim() === '') {
                            cancelRename();
                        } else {
                            renameFolder(this.value);
                        }
                    });
                    
                    function cancelRename() {
                        folderItem.classList.remove('editing');
                        input.value = originalName;
                    }
                    
                    function renameFolder(newName) {
                        if (!newName.trim()) {
                            cancelRename();
                            return;
                        }
                        
                        // 检查是否存在同名文件夹
                        const existingFolders = Array.from(document.querySelectorAll('.folder-name'))
                            .filter(el => el.closest('.folder-item-card') !== folderItem)
                            .map(el => el.textContent.trim());
                        
                        let finalName = newName.trim();
                        let counter = 2;
                        
                        while (existingFolders.includes(finalName)) {
                            finalName = `${newName.trim()} (${counter})`;
                            counter++;
                        }
                        
                        fetch('../controllers/folder.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=rename&id=${currentFolderId}&name=${encodeURIComponent(finalName)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                showToast(data.message || '重命名失败', 'error');
                                cancelRename();
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToast('重命名失败，请稍后重试', 'error');
                            cancelRename();
                        });
                    }
                });

                // 删除文件夹
                document.getElementById('deleteFolderMenuItem').addEventListener('click', function() {
                    if (confirm('确定要删除此文件夹吗？文件夹内的所有图片也会被删除！')) {
                        fetch('../controllers/folder.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=delete&id=${currentFolderId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert(data.message || '删除失败');
                            }
                        });
                    }
                });

                // 创建文件夹函数
                function createFolder(name) {
                    if (!name.trim()) {
                        name = '新建文件夹';
                    }
                    
                    // 检查是否存在同名文件夹
                    const existingFolders = Array.from(document.querySelectorAll('.folder-name')).map(el => el.textContent.trim());
                    let finalName = name.trim();
                    let counter = 1;
                    
                    // 如果文件夹名已存在，添加数字
                    while (existingFolders.includes(finalName)) {
                        counter++;
                        finalName = `${name.trim()} ${counter}`;
                    }
                    
                    fetch('../controllers/folder.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=create&name=${encodeURIComponent(finalName)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            showToast(data.message || '创建失败', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('创建失败，请稍后重试', 'error');
                    });
                }
            });

            // 分页相关的JavaScript代码
            const ITEMS_PER_PAGE = 5;
            let currentPage = 1;

            function initPagination() {
                const items = document.querySelectorAll('.folder-item-card');
                const totalPages = Math.ceil(items.length / ITEMS_PER_PAGE);
                
                // 初始化页码按钮
                const pageNumbers = document.getElementById('pageNumbers');
                if (!pageNumbers) return;
                
                pageNumbers.innerHTML = '';
                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement('button');
                    btn.className = `pagination-btn ${i === currentPage ? 'active' : ''}`;
                    btn.textContent = i;
                    btn.onclick = () => goToPage(i);
                    pageNumbers.appendChild(btn);
                }
                
                // 初始化上一页/下一页按钮
                const prevBtn = document.getElementById('prevPage');
                const nextBtn = document.getElementById('nextPage');
                
                if (prevBtn && nextBtn) {
                    prevBtn.onclick = () => goToPage(currentPage - 1);
                    nextBtn.onclick = () => goToPage(currentPage + 1);
                    
                    // 显示当前页
                    showPage(currentPage);
                }
            }

            function showPage(page) {
                const items = document.querySelectorAll('.folder-item-card');
                const totalPages = Math.ceil(items.length / ITEMS_PER_PAGE);
                
                // 确保页码在有效范围内
                if (page < 1) page = 1;
                if (page > totalPages) page = totalPages;
                
                // 更新按钮状态
                const prevBtn = document.getElementById('prevPage');
                const nextBtn = document.getElementById('nextPage');
                if (prevBtn) prevBtn.disabled = page === 1;
                if (nextBtn) nextBtn.disabled = page === totalPages;
                
                // 更新页码按钮状态
                document.querySelectorAll('#pageNumbers .pagination-btn').forEach(btn => {
                    btn.classList.toggle('active', parseInt(btn.textContent) === page);
                });
                
                // 隐藏所有内容
                items.forEach(item => {
                    item.style.display = 'none';
                });
                
                // 显示当前页的内容
                const startIndex = (page - 1) * ITEMS_PER_PAGE;
                const endIndex = Math.min(startIndex + ITEMS_PER_PAGE, items.length);
                
                for (let i = startIndex; i < endIndex; i++) {
                    if (items[i]) {
                        items[i].style.display = '';
                    }
                }
                
                currentPage = page;
                // 保存当前页码到 sessionStorage
                sessionStorage.setItem('currentPage', page);
            }

            function goToPage(page) {
                const items = document.querySelectorAll('.folder-item-card');
                const totalPages = Math.ceil(items.length / ITEMS_PER_PAGE);
                
                if (page < 1 || page > totalPages) return;
                showPage(page);
            }

            // 初始化分页
            document.addEventListener('DOMContentLoaded', function() {
                if (document.querySelector('.folder-grid')) {
                    // 从 sessionStorage 获取保存的页码
                    const savedPage = sessionStorage.getItem('currentPage');
                    if (savedPage) {
                        currentPage = parseInt(savedPage);
                    }
                    
                    initPagination();
                    showPage(currentPage);
                }
            });

            // 处理移动到文件夹的功能
            document.addEventListener('DOMContentLoaded', function() {
                const moveToFolderModal = new bootstrap.Modal(document.getElementById('moveToFolderModal'));
                let selectedFolderId = null;
                let currentImageId = null;

                // 点击移动到文件夹按钮
                document.querySelectorAll('.move-to-folder-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        currentImageId = this.dataset.imageId;
                        selectedFolderId = null;
                        
                        // 重置选中状态
                        document.querySelectorAll('.folder-select-item').forEach(item => {
                            item.classList.remove('selected');
                        });
                        
                        // 禁用确认按钮
                        document.getElementById('confirmMove').disabled = true;
                        
                        // 显示模态框
                        moveToFolderModal.show();
                    });
                });

                // 选择文件夹
                document.querySelectorAll('.folder-select-item').forEach(item => {
                    item.addEventListener('click', function() {
                        // 移除其他文件夹的选中状态
                        document.querySelectorAll('.folder-select-item').forEach(i => {
                            i.classList.remove('selected');
                        });
                        
                        // 选中当前文件夹
                        this.classList.add('selected');
                        selectedFolderId = this.dataset.folderId;
                        
                        // 启用确认按钮
                        document.getElementById('confirmMove').disabled = false;
                    });
                });

                // 确认移动
                document.getElementById('confirmMove').addEventListener('click', function() {
                    if (!selectedFolderId || !currentImageId) return;

                    // 发送移动请求
                    fetch('../controllers/move_image.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `image_id=${currentImageId}&folder_id=${selectedFolderId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // 关闭模态框
                            moveToFolderModal.hide();
                            
                            // 显示成功提示
                            showToast('图片已成功移动到文件夹', 'success');
                            
                            // 延迟刷新页面
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            showToast(data.message || '移动失败', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('移动失败，请稍后重试', 'error');
                    });
                });
            });

            // 修改图片预览相关的JavaScript代码
            document.addEventListener('DOMContentLoaded', function() {
                const imageModal = document.getElementById('imageModal');
                const modalImage = document.getElementById('modalImage');
                const container = document.querySelector('.image-preview-container');
                let currentScale = 1;
                let isDragging = false;
                let startX, startY, translateX = 0, translateY = 0;
                
                // 重置图片缩放和位置
                function resetImage() {
                    currentScale = 1;
                    translateX = 0;
                    translateY = 0;
                    updateImageTransform();
                    document.querySelector('.zoom-info').textContent = '100%';
                }
                
                // 更新图片变换
                function updateImageTransform() {
                    modalImage.style.transform = `translate(${translateX}px, ${translateY}px) scale(${currentScale})`;
                }
                
                // 在模态框显示前重置图片
                imageModal.addEventListener('show.bs.modal', function() {
                    resetImage();
                });
                
                // 图片点击事件
                document.querySelectorAll('.image-item img').forEach(img => {
                    img.addEventListener('click', function() {
                        modalImage.src = this.src;
                        const modal = new bootstrap.Modal(imageModal);
                        modal.show();
                    });
                });
                
                // 拖拽开始
                container.addEventListener('mousedown', function(e) {
                    if (e.button !== 0) return; // 只响应左键
                    isDragging = true;
                    container.classList.add('grabbing');
                    startX = e.clientX - translateX;
                    startY = e.clientY - translateY;
                });
                
                // 拖拽中
                container.addEventListener('mousemove', function(e) {
                    if (!isDragging) return;
                    e.preventDefault();
                    translateX = e.clientX - startX;
                    translateY = e.clientY - startY;
                    updateImageTransform();
                });
                
                // 拖拽结束
                function stopDragging() {
                    isDragging = false;
                    container.classList.remove('grabbing');
                }
                
                container.addEventListener('mouseup', stopDragging);
                container.addEventListener('mouseleave', stopDragging);
                
                // 缩放控制
                document.getElementById('zoomIn').addEventListener('click', () => {
                    currentScale = Math.min(currentScale + 0.2, 3);
                    updateImageTransform();
                    document.querySelector('.zoom-info').textContent = `${Math.round(currentScale * 100)}%`;
                });
                
                document.getElementById('zoomOut').addEventListener('click', () => {
                    currentScale = Math.max(currentScale - 0.2, 0.5);
                    updateImageTransform();
                    document.querySelector('.zoom-info').textContent = `${Math.round(currentScale * 100)}%`;
                });
                
                document.getElementById('resetZoom').addEventListener('click', resetImage);
                
                // 鼠标滚轮缩放
                container.addEventListener('wheel', function(e) {
                    e.preventDefault();
                    const delta = e.deltaY > 0 ? -0.1 : 0.1;
                    const newScale = Math.max(0.5, Math.min(3, currentScale + delta));
                    
                    // 计算鼠标位置相对于图片中心的偏移
                    const rect = modalImage.getBoundingClientRect();
                    const mouseX = e.clientX - rect.left - rect.width / 2;
                    const mouseY = e.clientY - rect.top - rect.height / 2;
                    
                    // 根据缩放比例调整偏移量
                    const scaleDiff = newScale - currentScale;
                    translateX += mouseX * scaleDiff / currentScale;
                    translateY += mouseY * scaleDiff / currentScale;
                    
                    currentScale = newScale;
                    updateImageTransform();
                    document.querySelector('.zoom-info').textContent = `${Math.round(currentScale * 100)}%`;
                });
                
                // 触摸设备支持
                let lastTouchDistance = 0;
                
                container.addEventListener('touchstart', function(e) {
                    if (e.touches.length === 2) {
                        lastTouchDistance = getTouchDistance(e.touches);
                    } else if (e.touches.length === 1) {
                        isDragging = true;
                        startX = e.touches[0].clientX - translateX;
                        startY = e.touches[0].clientY - translateY;
                    }
                });
                
                container.addEventListener('touchmove', function(e) {
                    e.preventDefault();
                    if (e.touches.length === 2) {
                        // 处理缩放
                        const distance = getTouchDistance(e.touches);
                        const scale = distance / lastTouchDistance;
                        
                        const newScale = Math.max(0.5, Math.min(3, currentScale * scale));
                        currentScale = newScale;
                        lastTouchDistance = distance;
                        
                        updateImageTransform();
                        document.querySelector('.zoom-info').textContent = `${Math.round(currentScale * 100)}%`;
                    } else if (e.touches.length === 1 && isDragging) {
                        // 处理拖动
                        translateX = e.touches[0].clientX - startX;
                        translateY = e.touches[0].clientY - startY;
                        updateImageTransform();
                    }
                });
                
                container.addEventListener('touchend', function() {
                    isDragging = false;
                    lastTouchDistance = 0;
                });
                
                function getTouchDistance(touches) {
                    return Math.hypot(
                        touches[0].clientX - touches[1].clientX,
                        touches[0].clientY - touches[1].clientY
                    );
                }
            });

            document.getElementById('confirmRename').addEventListener('click', function() {
                const newName = document.getElementById('newFolderName').value.trim();
                const folderId = document.getElementById('renameFolderId').value;
                const folderCard = document.querySelector(`[data-folder-id="${folderId}"]`);
                const originalName = folderCard.getAttribute('data-folder-name');
                
                // 如果新名称为空，显示错误提示
                if (newName === '') {
                    showToast('文件夹名称不能为空', 'error');
                    return;
                }
                
                // 如果名称没有改变，直接关闭模态框
                if (newName === originalName) {
                    renameFolderModal.hide();
                    return;
                }
                
                // 检查是否存在同名文件夹（排除当前文件夹）
                const existingFolders = Array.from(document.querySelectorAll('.folder-item-card'))
                    .filter(folder => folder.getAttribute('data-folder-id') !== folderId) // 排除当前文件夹
                    .map(folder => folder.getAttribute('data-folder-name'));
                
                if (existingFolders.includes(newName)) {
                    showToast('已存在同名文件夹', 'error');
                    return;
                }

                // 发送重命名请求
                fetch('rename_folder.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `folder_id=${folderId}&new_name=${encodeURIComponent(newName)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 更新文件夹名称
                        folderCard.setAttribute('data-folder-name', newName);
                        folderCard.querySelector('.folder-name').textContent = newName;
                        showToast('重命名成功');
                        renameFolderModal.hide();
                    } else {
                        showToast(data.message || '重命名失败', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('重命名失败', 'error');
                });
            });

            // 重命名模态框关闭时重置输入框
            document.getElementById('renameFolderModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('newFolderName').value = '';
                document.getElementById('renameFolderId').value = '';
            });

            // 重命名模态框显示时设置当前文件夹名称
            document.getElementById('renameFolderModal').addEventListener('show.bs.modal', function (event) {
                const folderId = document.getElementById('renameFolderId').value;
                const folderCard = document.querySelector(`[data-folder-id="${folderId}"]`);
                if (folderCard) {
                    const currentName = folderCard.getAttribute('data-folder-name');
                    document.getElementById('newFolderName').value = currentName;
                    // 选中文本，方便用户直接输入新名称
                    setTimeout(() => {
                        const input = document.getElementById('newFolderName');
                        input.select();
                    }, 100);
                }
            });

            // 处理回车键提交
            document.getElementById('newFolderName').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('confirmRename').click();
                }
            });
        </script>
    </div>
</body>
</html>
