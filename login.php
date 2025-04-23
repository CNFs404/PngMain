<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'C6hhbSrTJZ5zQbDG6vfNKAtsm_login_errors.log');

// 启动输出缓冲
ob_start();

// 启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否已登录
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    header('Location: /views/index');
    exit;
}

// 生成CSRF令牌
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 检查配置文件是否存在
if (!file_exists('config.php')) {
    ob_end_clean();
    die('请先运行 <a href="install">install</a> 完成系统安装');
}

require_once 'config.php';
require_once 'models/Database.php';

// 初始化消息变量
$message = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        // 记录登录尝试
        error_log("登录尝试 - 用户名: {$username}, IP: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']));
        
        // 测试数据库连接
        try {
            $db = Database::getInstance()->getConnection();
            if (!$db) {
                throw new Exception("数据库连接失败");
            }
            error_log("数据库连接成功");
        } catch (Exception $e) {
            error_log("数据库连接错误: " . $e->getMessage());
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
        
        $stmt = $db->prepare("SELECT id, username, password, status, ban_reason, ban_time, user_level FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("SQL准备失败: " . implode(", ", $db->errorInfo()));
        }
        
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // 检查用户是否被封禁
            if ($user['status'] === '封禁') {
                // 格式化封禁时间显示
                $ban_time_str = '';
                if ($user['ban_time']) {
                    $ban_time = new DateTime($user['ban_time']);
                    $now = new DateTime();
                    if ($ban_time > $now) {
                        $interval = $now->diff($ban_time);
                        $ban_time_str = "，封禁将持续至 " . $ban_time->format('Y-m-d H:i:s');
                        $ban_time_str .= "（还剩 " . $interval->days . " 天 " . $interval->h . " 小时）";
                    }
                }
                $message = '账号已被封禁，原因：' . htmlspecialchars($user['ban_reason']) . $ban_time_str;
                error_log("封禁用户尝试登录: {$username}");
            } else {
                try {
                    // 记录登录 IP
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
                    $updateStmt = $db->prepare("UPDATE users SET last_login_ip = ?, last_login_time = NOW() WHERE id = ?");
                    if (!$updateStmt) {
                        throw new Exception("更新SQL准备失败: " . implode(", ", $db->errorInfo()));
                    }
                    
                    $result = $updateStmt->execute([$ip, $user['id']]);
                    if (!$result) {
                        throw new Exception("更新用户登录信息失败: " . implode(", ", $updateStmt->errorInfo()));
                    }
                    
                    // 设置会话变量
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_level'] = $user['user_level'];
                    $_SESSION['last_login_time'] = time();
                    $_SESSION['login_ip'] = $ip;
                    
                    // 生成并存储会话标识
                    $_SESSION['session_id'] = bin2hex(random_bytes(32));
                    $sessionStmt = $db->prepare("UPDATE users SET session_id = ? WHERE id = ?");
                    if (!$sessionStmt->execute([$_SESSION['session_id'], $user['id']])) {
                        throw new Exception("更新会话ID失败: " . implode(", ", $sessionStmt->errorInfo()));
                    }
                    
                    error_log("用户登录成功: {$username}");
                    ob_end_clean();
                    header('Location: views/index');
                    exit;
                } catch (Exception $e) {
                    error_log("登录过程错误: " . $e->getMessage());
                    $message = '登录失败：' . $e->getMessage();
                }
            }
        } else {
            error_log("登录失败 - 用户名或密码错误: {$username}");
            $message = '用户名或密码错误';
        }
    } catch (Exception $e) {
        error_log("登录系统错误: " . $e->getMessage());
        $message = '登录失败：' . $e->getMessage();
    }
}

// 页面输出前的清理
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 图床系统</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
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
            color: white;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(33,150,243,0.3);
            background: linear-gradient(45deg, #1976D2, #1565C0);
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #546e7a;
            font-size: 14px;
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
            margin-bottom: 20px;
            padding: 15px;
        }
        .alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group .form-control {
            padding-left: 40px;
            margin-bottom: 0;
        }
        .input-group-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #90a4ae;
            z-index: 4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h2 class="login-title">登录</h2>
            <?php if (!empty($message)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="input-group">
                    <i class="bi bi-person input-group-icon"></i>
                    <input type="text" name="username" class="form-control" placeholder="用户名" required>
                </div>
                
                <div class="input-group">
                    <i class="bi bi-lock input-group-icon"></i>
                    <input type="password" name="password" class="form-control" placeholder="密码" required>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>登录
                </button>
                
                <div class="register-link">
                    还没有账号？ <a href="register">立即注册</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 
