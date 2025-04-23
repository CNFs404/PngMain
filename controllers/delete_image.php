<?php
require_once '../config.php';
require_once '../models/Database.php';

// 检查是否是管理员
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => '未登录']));
}

try {
    $db = Database::getInstance()->getConnection();
    
    // 验证管理员权限
    $stmt = $db->prepare("SELECT user_level FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['user_level'] !== '管理员') {
        die(json_encode(['success' => false, 'message' => '权限不足']));
    }
    
    // 获取要删除的图片ID
    $image_id = $_POST['image_id'] ?? null;
    if (!$image_id) {
        die(json_encode(['success' => false, 'message' => '参数错误']));
    }
    
    // 获取图片信息
    $stmt = $db->prepare("SELECT file_path FROM images WHERE id = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();
    
    if (!$image) {
        die(json_encode(['success' => false, 'message' => '图片不存在']));
    }
    
    // 删除本地文件
    
    $file_path = __DIR__ . '/../' . $image['file_path'];
    if (file_exists($file_path)) {
        if (!unlink($file_path)) {
            die(json_encode(['success' => false, 'message' => '删除文件失败']));
        }
    }
    
    // 删除数据库记录
    $stmt = $db->prepare("DELETE FROM images WHERE id = ?");
    $stmt->execute([$image_id]);
    
    echo json_encode(['success' => true, 'message' => '删除成功']);
    
} catch (Exception $e) {
    error_log("删除图片错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '删除失败：' . $e->getMessage()]);
} 
