<?php
// 开启错误报告

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 确保在文件开头启动session
session_start();

// 检查用户是否已登录且是管理员
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

require_once '../config.php';
require_once '../models/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // 验证当前用户是否是管理员
    $stmt = $db->prepare("SELECT user_level FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['user_level'] !== '管理员') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '权限不足']);
        exit;
    }
    
    // 获取目标用户ID
    $user_id = $_GET['user_id'] ?? 0;
    
    // 获取分页参数
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 6; // 每页显示6张图片
    
    // 获取总图片数
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM images WHERE user_id = ?");
    $count_stmt->execute([$user_id]);
    $total_images = (int)$count_stmt->fetchColumn();
    
    // 确保每页显示数量为整数
    $per_page = (int)$per_page;
    
    // 计算总页数（向上取整）
    $total_pages = max(1, ceil($total_images / $per_page));
    
    // 确保当前页码在有效范围内
    $page = max(1, min($page, $total_pages));
    
    // 重新计算偏移量
    $offset = ($page - 1) * $per_page;
    
    // 获取用户图片列表（带分页）
    $stmt = $db->prepare("
        SELECT 
            id,
            filename,
            original_name,
            upload_date
        FROM images 
        WHERE user_id = ? 
        ORDER BY upload_date DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $images = $stmt->fetchAll();
    
    // 处理图片URL
    $processed_images = array_map(function($image) {
        return [
            'id' => $image['id'],
            'url' => SITE_URL . '/' . UPLOAD_DIR . $image['filename'],
            'original_name' => $image['original_name'],
            'upload_date' => $image['upload_date']
        ];
    }, $images);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'images' => $processed_images,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_images' => $total_images,
            'per_page' => $per_page
        ]
    ]);
    
} catch (Exception $e) {
    error_log("获取用户图片错误: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '获取图片失败：' . $e->getMessage()
    ]);
} 
