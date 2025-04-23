<?php
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;

    public function __construct() {
        try {
            $this->mailer = new PHPMailer(true);
            
            // 服务器设置
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = SMTP_AUTH;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = SMTP_SECURE;
            $this->mailer->Port = SMTP_PORT;
            $this->mailer->CharSet = 'UTF-8';
            
            // 调试模式
            if (SMTP_DEBUG) {
                $this->mailer->SMTPDebug = 2;
            }
            
            // 发件人设置
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        } catch (Exception $e) {
            error_log("邮件服务初始化错误: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function sendVerificationCode($toEmail, $code) {
        $subject = '图床系统 - 邮箱验证码';
        $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .code { font-size: 24px; color: #007bff; font-weight: bold; }
                    .footer { margin-top: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>邮箱验证码</h2>
                    <p>您的验证码是：<span class='code'>{$code}</span></p>
                    <p>验证码有效期为10分钟，请尽快使用。</p>
                    <div class='footer'>
                        <p>此邮件由系统自动发送，请勿回复。</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($toEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $message;
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("发送验证码错误: " . $e->getMessage());
            return false;
        }
    }
} 
