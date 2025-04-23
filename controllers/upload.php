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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

require_once '../config.php';
require_once '../models/Database.php';

// 定义允许的文件类型和扩展名
$allowed_types = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif'
];

$max_file_size = 5 * 1024 * 1024; // 5MB
$response = ['success' => false, 'message' => ''];

try {
    $db = Database::getInstance()->getConnection();
    
    // 获取文件夹ID
    $folder_id = isset($_POST['folder_id']) ? $_POST['folder_id'] : null;
    $success_count = 0;
    $total_files = count($_FILES['images']['tmp_name']);
    $error_messages = [];

    // 验证文件夹ID（如果提供了的话）
    if ($folder_id !== null) {
        $folder_stmt = $db->prepare("SELECT * FROM folders WHERE id = ? AND user_id = ?");
        $folder_stmt->execute([$folder_id, $_SESSION['user_id']]);
        if (!$folder_stmt->fetch()) {
            die(json_encode(['success' => false, 'message' => '无效的文件夹']));
        }
    }

    // 处理每个上传的文件
    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        $file = [
            'name' => $_FILES['images']['name'][$key],
            'type' => $_FILES['images']['type'][$key],
            'tmp_name' => $tmp_name,
            'error' => $_FILES['images']['error'][$key],
            'size' => $_FILES['images']['size'][$key]
        ];
        
        // 验证文件大小
        if ($file['size'] > $max_file_size) {
            throw new Exception('文件大小超过限制（最大5MB）');
        }
        
        // 验证文件类型
        if (!isset($allowed_types[$file['type']])) {
            throw new Exception('不支持的文件类型');
        }
        
        // 验证文件内容
        if (!getimagesize($file['tmp_name'])) {
            throw new Exception('文件验证失败');
        }
        
        // 计算文件哈希值
        $file_hash = md5_file($file['tmp_name']);
        
        // 检查数据库中是否已存在相同哈希值的图片
        $stmt = $db->prepare("SELECT id, filename FROM images WHERE file_hash = ? LIMIT 1");
        $stmt->execute([$file_hash]);
        $existing_image = $stmt->fetch();
        
        if ($existing_image) {
            // 如果图片已存在，创建新的数据库记录但引用现有文件
            $stmt = $db->prepare("INSERT INTO images (user_id, filename, original_name, size, upload_date, file_hash) VALUES (?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $existing_image['filename'], // 使用原始文件路径，不添加额外的 ../
                $file['name'],
                $file['size'],
                $file_hash
            ]);
            
            $success_count++;
            $response['message'] = '图片已存在，已创建引用';
            $response['url'] = $existing_image['filename'];
            continue; // 处理下一个文件
        }
        
        // 如果图片不存在，则保存新文件
        // 生成唯一的文件名
        $date = date('Ymd');
        $filename = uniqid() . '.' . $allowed_types[$file['type']];
        $upload_dir = '../PNG/' . $date;
        
        // 确保上传目录存在
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // 开始事务
        $db->beginTransaction();

        // 插入图片记录
        $stmt = $db->prepare("INSERT INTO images (user_id, filename, original_name, size, upload_date, folder_id) VALUES (?, ?, ?, ?, NOW(), ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            '../PNG/' . $date . '/' . $filename,
            $file['name'],
            $file['size'],
            $folder_id
        ]);

        // 提交事务
        $db->commit();
        
        // 移动上传的文件
        if (!move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $filename)) {
            throw new Exception('无法移动上传的文件');
        }

        $success_count++;
        $response['message'] = '上传成功';
        $response['url'] = '../PNG/' . $date . '/' . $filename;
    }

    // 准备响应信息
    if ($success_count === $total_files) {
        $response = [
            'success' => true,
            'message' => $success_count > 1 ? "成功上传 {$success_count} 张图片" : "图片上传成功",
            'total' => $total_files,
            'succeeded' => $success_count
        ];
    } else if ($success_count > 0) {
        $response = [
            'success' => true,
            'message' => "成功上传 {$success_count}/{$total_files} 张图片，部分图片上传失败",
            'total' => $total_files,
            'succeeded' => $success_count,
            'errors' => $error_messages
        ];
    } else {
        $response = [
            'success' => false,
            'message' => "所有图片上传失败",
            'total' => $total_files,
            'succeeded' => 0,
            'errors' => $error_messages
        ];
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => '上传过程发生错误：' . $e->getMessage()
    ];
}

ob_end_clean();
header('Content-Type: application/json');
echo json_encode($response); 
