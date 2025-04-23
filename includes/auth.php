<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 确保session已启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../models/Database.php';

function checkAuth() {
    // 检查是否登录
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }

    try {
        $db = Database::getInstance()->getConnection();
        
        // 检查用户状态
        $stmt = $db->prepare("SELECT status, ban_reason, ban_time, session_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // 用户不存在，清除session
            session_destroy();
            header('Location: /login.php');
            exit;
        }
        
        // 检查会话标识是否匹配
        if (!isset($_SESSION['session_id']) || $user['session_id'] !== $_SESSION['session_id']) {
            // 会话不匹配，可能是在其他地方登录
            session_destroy();
            header('Location: /login.php?error=' . urlencode('您的账号已在其他地方登录'));
            exit;
        }
        
        // 检查是否被封禁
        if ($user['status'] === '封禁') {
            // 检查是否是临时封禁
            $ban_message = '账号已被封禁，原因：' . htmlspecialchars($user['ban_reason']);
            if ($user['ban_time']) {
                $ban_time = new DateTime($user['ban_time']);
                $now = new DateTime();
                if ($ban_time > $now) {
                    $interval = $now->diff($ban_time);
                    $ban_message .= "，封禁将持续至 " . $ban_time->format('Y-m-d H:i:s');
                    $ban_message .= "（还剩 " . $interval->days . " 天 " . $interval->h . " 小时）";
                }
            }
            
            // 记录封禁用户访问日志
            error_log("封禁用户尝试访问: {$_SESSION['username']}, IP: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']));
            
            // 清除session
            session_destroy();
            header('Location: /login.php?error=' . urlencode($ban_message));
            exit;
        }
        
        // 更新最后活动时间
        $stmt = $db->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        return true;
    } catch (Exception $e) {
        error_log("验证用户状态错误: " . $e->getMessage());
        return false;
    }
} 
