<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 开启输出缓冲
ob_start();

// 启动会话
session_start();

// 如果用户已登录，重定向到首页
if (isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: views/index');
    exit;
}

// 请求频率限制
if (!isset($_SESSION['last_request_time'])) {
    $_SESSION['last_request_time'] = 0;
    $_SESSION['request_count'] = 0;
}

$current_time = time();
if ($current_time - $_SESSION['last_request_time'] < 1) { // 1秒内
    $_SESSION['request_count']++;
    if ($_SESSION['request_count'] > 3) { // 1秒内最多3次请求
        ob_end_clean();
        die('请求过于频繁，请稍后再试');
    }
} else {
    $_SESSION['request_count'] = 1;
    $_SESSION['last_request_time'] = $current_time;
}

// 生成 CSRF 令牌
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'config.php';
require_once 'models/Database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证 CSRF 令牌
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = '无效的请求';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $verify_code = $_POST['verify_code'] ?? '';
        
        try {
            // 验证输入
            if (empty($username) || empty($password) || empty($email) || empty($verify_code)) {
                throw new Exception('所有字段都必须填写');
            }
            
            if (strlen($username) < 3 || strlen($username) > 20) {
                throw new Exception('用户名长度必须在3-20个字符之间');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('邮箱格式不正确');
            }
            
            if (strlen($password) < 6) {
                throw new Exception('密码长度不能少于6个字符');
            }
            
            if ($password !== $confirm_password) {
                throw new Exception('两次输入的密码不一致');
            }
            
            // 验证验证码
            if (!isset($_SESSION['verify_code']) || !isset($_SESSION['verify_email']) || !isset($_SESSION['verify_time'])) {
                throw new Exception('请先获取验证码');
            }
            
            if ($_SESSION['verify_email'] !== $email) {
                throw new Exception('验证码与邮箱不匹配');
            }
            
            if ($_SESSION['verify_code'] !== $verify_code) {
                throw new Exception('验证码错误');
            }
            
            if (time() - $_SESSION['verify_time'] > 600) { // 10分钟有效期
                throw new Exception('验证码已过期，请重新获取');
            }
            
            // 检查用户名和邮箱是否已存在
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                throw new Exception('用户名或邮箱已存在');
            }
            
            // 创建新用户
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$username, $email, $hashed_password]);
            
            // 清除验证码session
            unset($_SESSION['verify_code']);
            unset($_SESSION['verify_email']);
            unset($_SESSION['verify_time']);
            
            $success = '注册成功！请登录';
            ob_end_clean();
            header('Location: login');
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - 图床系统</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- 添加 reCAPTCHA API -->
    <script src="https://www.recaptcha.net/recaptcha/api.js" async defer></script>
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-container {
            max-width: 450px;
            margin: 40px auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .register-title {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 28px;
            position: relative;
            padding-bottom: 10px;
        }
        .register-title::after {
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
        .btn-register {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            font-weight: 500;
            background: linear-gradient(45deg, #2196F3, #1976D2);
            border: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
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
        /* 验证码按钮样式 */
        .verify-btn {
            background: linear-gradient(45deg, #2196F3, #1976D2);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            white-space: nowrap;
            box-shadow: 0 2px 6px rgba(33,150,243,0.2);
        }
        .verify-btn:hover {
            background: linear-gradient(45deg, #1976D2, #1565C0);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(33,150,243,0.3);
        }
        .verify-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(33,150,243,0.2);
        }
        .verify-btn:disabled {
            background: linear-gradient(45deg, #b0bec5, #90a4ae);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.8;
        }
        .verify-btn i {
            margin-right: 6px;
            font-size: 16px;
        }
        /* 倒计时样式 */
        .countdown {
            color: #78909c;
            font-size: 14px;
            margin-left: 12px;
            font-weight: 500;
        }
        /* 输入框组样式 */
        .input-group {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .input-group .form-control {
            flex: 1;
            margin-bottom: 0;
        }
        /* 警告提示样式 */
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
        /* 添加 reCAPTCHA API */
        .g-recaptcha {
            margin-bottom: 15px;
        }
        .verify-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        #recaptcha-error {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <h2 class="register-title">用户注册</h2>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-3">
                    <label for="username" class="form-label">用户名</label>
                    <input type="text" class="form-control" id="username" name="username" required 
                           minlength="3" maxlength="20" placeholder="请输入3-20位用户名"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">邮箱</label>
                    <div class="input-group">
                        <input type="email" class="form-control" id="email" name="email" required 
                               placeholder="请输入邮箱地址"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <button type="button" class="verify-btn" onclick="beforeSendCode()">
                            <i class="bi bi-envelope"></i>获取验证码
                        </button>
                    </div>
                    <!-- 添加 reCAPTCHA -->
                    <div class="g-recaptcha" data-sitekey="6LfQIR8rAAAAABvT8L-R7SCHQ-J9Ddr3uPg3M41Y" data-callback="enableVerifyButton" data-size="normal"></div>
                    <div id="recaptcha-error">请先完成人机验证</div>
                </div>
                
                <div class="mb-3">
                    <label for="verify_code" class="form-label">验证码</label>
                    <input type="text" class="form-control" id="verify_code" name="verify_code" required 
                           maxlength="6" placeholder="请输入6位验证码"
                           value="<?php echo htmlspecialchars($_POST['verify_code'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">密码</label>
                    <input type="password" class="form-control" id="password" name="password" required 
                           minlength="6" placeholder="请输入至少6位密码">
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">确认密码</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                           minlength="6" placeholder="请再次输入密码">
                </div>
                
                <button type="submit" class="btn btn-primary btn-register">注册账号</button>
                
                <div class="register-link">
                    已有账号？ <a href="login">立即登录</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // 页面加载时禁用验证码按钮
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.verify-btn').disabled = true;
        });

        // reCAPTCHA 回调函数，验证成功后启用按钮
        function enableVerifyButton(token) {
            if (token) {
                document.querySelector('.verify-btn').disabled = false;
                document.getElementById('recaptcha-error').style.display = 'none';
            }
        }

        // 发送验证码前的检查
        function beforeSendCode() {
            const recaptchaResponse = grecaptcha.getResponse();
            if (!recaptchaResponse) {
                document.getElementById('recaptcha-error').style.display = 'block';
                return;
            }
            sendVerificationCode(recaptchaResponse);
        }

        // 修改验证码发送函数
        function sendVerificationCode(recaptchaToken) {
            const email = document.getElementById('email').value;
            const btn = document.querySelector('.verify-btn');
            
            if (!email) {
                alert('请输入邮箱地址');
                return;
            }
            
            // 禁用按钮
            btn.disabled = true;
            const originalHtml = btn.innerHTML;
            
            // 发送验证码请求
            fetch('controllers/verify.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `email=${encodeURIComponent(email)}&recaptcha=${recaptchaToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 显示倒计时
                    let timeLeft = 60;
                    btn.innerHTML = `<i class="bi bi-clock"></i>${timeLeft}秒后重试`;
                    
                    const timer = setInterval(() => {
                        timeLeft--;
                        btn.innerHTML = `<i class="bi bi-clock"></i>${timeLeft}秒后重试`;
                        
                        if (timeLeft <= 0) {
                            clearInterval(timer);
                            btn.disabled = true; // 重置后需要重新验证
                            btn.innerHTML = originalHtml;
                            grecaptcha.reset(); // 重置 reCAPTCHA
                        }
                    }, 1000);
                    
                    // 显示成功提示
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = '<i class="bi bi-check-circle-fill"></i>验证码已发送，请查收邮件';
                    document.querySelector('form').insertBefore(alertDiv, document.querySelector('.mb-3'));
                    
                    // 3秒后移除提示
                    setTimeout(() => alertDiv.remove(), 3000);
                } else {
                    btn.disabled = true;
                    btn.innerHTML = originalHtml;
                    grecaptcha.reset(); // 重置 reCAPTCHA
                    alert(data.message || '发送失败，请稍后重试');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = true;
                btn.innerHTML = originalHtml;
                grecaptcha.reset(); // 重置 reCAPTCHA
                alert('发送失败，请稍后重试');
            });
        }
    </script>
</body>
</html> 
