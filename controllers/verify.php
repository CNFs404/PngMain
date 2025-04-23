<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 开启输出缓冲
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../models/EmailService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 验证请求来源 - 修改为更宽松的检查
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
            $current = parse_url(SITE_URL, PHP_URL_HOST);
            if ($referer !== $current) {
                throw new Exception('非法请求来源');
            }
        }

        // 验证请求头
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            throw new Exception('非法请求方式');
        }

        $email = $_POST['email'] ?? '';
        $recaptchaResponse = $_POST['recaptcha'] ?? '';
        
        // 更严格的邮箱验证
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('邮箱格式不正确');
        }

        // 邮箱长度限制
        if (strlen($email) > 100) {
            throw new Exception('邮箱长度超出限制');
        }

        if (empty($recaptchaResponse)) {
            throw new Exception('请完成人机验证');
        }

        // 验证 reCAPTCHA
        $recaptchaSecret = '6LfQIR8rAAAAAOFDwH8je5q9MtOqN0e8N55ZQqL-';
        $verifyResponse = file_get_contents('https://www.recaptcha.net/recaptcha/api/siteverify?secret='.$recaptchaSecret.'&response='.$recaptchaResponse);
        $responseData = json_decode($verifyResponse);

        if (!$responseData->success) {
            throw new Exception('人机验证失败，请重试');
        }
        
        // 生成6位随机验证码
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // 保存验证码到session
        session_start();
        $_SESSION['verify_code'] = $code;
        $_SESSION['verify_email'] = $email;
        $_SESSION['verify_time'] = time();
        
        // 发送验证码
        $emailService = new EmailService();
        if ($emailService->sendVerificationCode($email, $code)) {
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => '验证码已发送']);
        } else {
            throw new Exception('验证码发送失败，请稍后重试');
        }
    } catch (Exception $e) {
        error_log("验证码发送错误: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
} 
