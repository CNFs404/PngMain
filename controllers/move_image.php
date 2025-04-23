<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 启动会话
session_start();
// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 检查是否是POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 检查必要参数
if (!isset($_POST['image_id']) || !isset($_POST['folder_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '缺少必要参数']);
    exit;
}

// 引入数据库配置
require_once '../config.php';
require_once '../models/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // 验证图片所有权
    $stmt = $db->prepare("SELECT * FROM images WHERE id = ? AND user_id = ?");
    $stmt->execute([$_POST['image_id'], $_SESSION['user_id']]);
    $image = $stmt->fetch();
    
    if (!$image) {
        throw new Exception('找不到指定的图片或没有权限');
    }
    
    // 验证文件夹所有权
    $stmt = $db->prepare("SELECT * FROM folders WHERE id = ? AND user_id = ?");
    $stmt->execute([$_POST['folder_id'], $_SESSION['user_id']]);
    $folder = $stmt->fetch();
    
    if (!$folder) {
        throw new Exception('找不到指定的文件夹或没有权限');
    }
    
    // 更新图片的文件夹ID
    $stmt = $db->prepare("UPDATE images SET folder_id = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$_POST['folder_id'], $_POST['image_id'], $_SESSION['user_id']]);
    
    // 返回成功响应
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => '图片已成功移动到文件夹'
    ]);
    
} catch (Exception $e) {
    // 返回错误响应
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '移动图片失败：' . $e->getMessage()
    ]);
} 
