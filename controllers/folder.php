<?php

require_once '../includes/auth.php';
checkAuth();

require_once '../models/Database.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => '未知操作'];
    
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                $response['message'] = '文件夹名称不能为空';
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO folders (user_id, name) VALUES (?, ?)");
            if ($stmt->execute([$_SESSION['user_id'], $name])) {
                $response['success'] = true;
                $response['message'] = '文件夹创建成功';
            } else {
                $response['message'] = '创建文件夹失败';
            }
            break;
            
        case 'rename':
            $id = $_POST['id'] ?? 0;
            $name = trim($_POST['name'] ?? '');
            
            if (empty($name)) {
                $response['message'] = '文件夹名称不能为空';
                break;
            }
            
            $stmt = $db->prepare("UPDATE folders SET name = ? WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$name, $id, $_SESSION['user_id']])) {
                $response['success'] = true;
                $response['message'] = '文件夹重命名成功';
            } else {
                $response['message'] = '重命名失败';
            }
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? 0;
            
            // 开始事务
            $db->beginTransaction();
            
            try {
                // 删除文件夹中的图片
                $stmt = $db->prepare("DELETE FROM images WHERE folder_id = ? AND user_id = ?");
                $stmt->execute([$id, $_SESSION['user_id']]);
                
                // 删除文件夹
                $stmt = $db->prepare("DELETE FROM folders WHERE id = ? AND user_id = ?");
                if ($stmt->execute([$id, $_SESSION['user_id']])) {
                    $db->commit();
                    $response['success'] = true;
                    $response['message'] = '文件夹删除成功';
                } else {
                    throw new Exception('删除文件夹失败');
                }
            } catch (Exception $e) {
                $db->rollBack();
                $response['message'] = $e->getMessage();
            }
            break;
            
        default:
            $response['message'] = '无效的操作类型';
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => '服务器错误：' . $e->getMessage()
    ];
}

echo json_encode($response); 
