<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 确保在文件开头启动session
ob_start();
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: index');
    exit;
}

require_once '../config.php';
require_once '../models/Database.php';

$message = '';
$error = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = '无效的请求';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        try {
            $db = Database::getInstance()->getConnection();
            
            // 验证当前密码
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                $error = '当前密码错误';
            } elseif (empty($new_password)) {
                $error = '新密码不能为空';
            } elseif (strlen($new_password) < 8) {
                $error = '新密码长度不能少于8位';
            } elseif ($new_password === $current_password) {
                $error = '新密码不能与当前密码相同';
            } elseif ($new_password !== $confirm_password) {
                $error = '两次输入的密码不一致';
            } else {
                // 更新密码
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                
                // 清除会话中的CSRF令牌
                unset($_SESSION['csrf_token']);
                
                $message = '密码修改成功';
            }
        } catch (Exception $e) {
            $error = '修改密码失败：' . $e->getMessage();
        }
    }
}

// 生成新的CSRF令牌
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// 获取用户信息
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    $error = '获取用户信息失败：' . $e->getMessage();
}

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户信息 - 图床系统</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .username-display {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 1.5rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .form-label {
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="profile-container">
            <h2 class="mb-4">用户信息管理</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-4">
                <label class="form-label">用户名</label>
                <div class="username-display">
                    <?php echo htmlspecialchars($user['username']); ?>
                    <small class="text-muted">（用户名不可修改）</small>
                </div>
            </div>
            
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="mb-3">
                    <label for="current_password" class="form-label">当前密码</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                
                <div class="mb-3">
                    <label for="new_password" class="form-label">新密码</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required 
                           minlength="8" pattern="^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$"
                           title="密码必须包含字母和数字，且长度至少为8位">
                    <div class="form-text">密码必须包含字母和数字，且长度至少为8位</div>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">确认新密码</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">修改密码</button>
                    <a href="index" class="btn btn-outline-secondary">返回首页</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 
