<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 开启输出缓冲
ob_start();

// 启动会话
session_start();

require_once 'config.php';
require_once 'models/Database.php';

// 检查是否已安装
if (!defined('INSTALLED') || INSTALLED !== true) {
    die('系统未安装，请先完成安装步骤。');
}

// 检查用户是否已登录且是管理员
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Location: login');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT user_level FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user || $user['user_level'] !== '管理员') {
        ob_end_clean();
        header('Location: views/index');
        exit;
    }
} catch (Exception $e) {
    error_log("管理员验证错误: " . $e->getMessage());
    ob_end_clean();
    header('Location: views/index');
    exit;
}

// 处理用户管理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $user_id = $_POST['user_id'] ?? 0;
        
        switch ($action) {
            case 'delete':
                $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND user_level != '管理员'");
                $stmt->execute([$user_id]);
                break;
                
            case 'change_level':
                $new_level = $_POST['level'] ?? '';
                if (in_array($new_level, ['普通用户', 'VIP用户'])) {
                    $stmt = $db->prepare("UPDATE users SET user_level = ? WHERE id = ? AND user_level != '管理员'");
                    $stmt->execute([$new_level, $user_id]);
                }
                break;

            case 'ban':
                $ban_reason = $_POST['ban_reason'] ?? '';
                $stmt = $db->prepare("UPDATE users SET status = '封禁', ban_reason = ?, ban_time = NOW() WHERE id = ? AND user_level != '管理员'");
                $stmt->execute([$ban_reason, $user_id]);
                break;

            case 'unban':
                $stmt = $db->prepare("UPDATE users SET status = '正常', ban_reason = NULL, ban_time = NULL WHERE id = ? AND user_level != '管理员'");
                $stmt->execute([$user_id]);
                break;
        }
        
        // 重定向回管理页面
        ob_end_clean();
        header('Location: admin.php');
        exit;
    } catch (Exception $e) {
        error_log("管理操作错误: " . $e->getMessage());
        $error = '操作失败：' . $e->getMessage();
    }
}

// 获取用户列表和他们的图片数量
try {
    $stmt = $db->query("
        SELECT 
            u.id, 
            u.username, 
            u.email, 
            u.created_at, 
            u.last_login_ip, 
            u.user_level,
            COALESCE(u.status, '正常') as status,
            u.ban_reason,
            u.ban_time,
            COUNT(DISTINCT i.id) as image_count
        FROM users u
        LEFT JOIN images i ON u.id = i.user_id
        WHERE u.user_level != '管理员'
        GROUP BY u.id, u.username, u.email, u.created_at, u.last_login_ip, u.user_level, u.status, u.ban_reason, u.ban_time
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("获取用户列表错误: " . $e->getMessage());
    $error = '获取用户列表失败: ' . $e->getMessage();
    $users = [];
}

ob_end_clean();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 图床系统</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f7fb;
        }
        .admin-container {
            max-width: 1300px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .page-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h2 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
        }
        .user-table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.02);
        }
        .user-table th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }
        .user-table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
            position: relative;
        }
        .user-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .action-buttons .form-select {
            width: auto;
            min-width: 120px;
        }
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
        }
        .status-badge {
            padding: 0.35rem 0.65rem;
            border-radius: 50rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
        }
        .status-normal {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .status-banned {
            background-color: #ffebee;
            color: #c62828;
        }
        .ban-info {
            font-size: 0.8125rem;
            color: #d32f2f;
            background: #fff1f1;
            border-radius: 0.25rem;
            padding: 0.5rem;
            margin-top: 0.5rem;
            border: 1px solid #ffebee;
        }
        .image-count-link {
            text-decoration: none;
            color: #1976d2;
            font-weight: 500;
        }
        .image-count-link:hover {
            color: #1565c0;
        }
        .image-count-zero {
            color: #9e9e9e;
            font-size: 0.875rem;
        }
        .card {
            position: relative;
        }
        .card .delete-image {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(220, 53, 69, 0.9);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 1;
        }
        .card .delete-image:hover {
            background: rgba(220, 53, 69, 1);
            transform: scale(1.1);
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-radius: 15px 15px 0 0;
        }
        .modal-footer {
            background-color: #f8f9fa;
            border-radius: 0 0 15px 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-container">
            <div class="page-header">
                <h2><i class="bi bi-people-fill me-2"></i>用户管理</h2>
                <a href="views/index" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> 返回首页
                </a>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-hover user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>邮箱</th>
                            <th>注册时间</th>
                            <th>最后登录IP</th>
                            <th>用户等级</th>
                            <th>状态</th>
                            <th>图片数量</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($user['last_login_ip'] ?? '未记录'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['user_level'] === '管理员' ? 'danger' : ($user['user_level'] === 'VIP用户' ? 'warning' : 'secondary'); ?>">
                                    <?php echo htmlspecialchars($user['user_level']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $user['status'] === '正常' ? 'status-normal' : 'status-banned'; ?>">
                                    <?php echo htmlspecialchars($user['status']); ?>
                                </span>
                                <?php if ($user['status'] === '封禁'): ?>
                                    <div class="ban-info">
                                        <div>原因: <?php echo htmlspecialchars($user['ban_reason']); ?></div>
                                        <div>时间: <?php echo htmlspecialchars($user['ban_time']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['image_count'] > 0): ?>
                                <a href="javascript:void(0)" onclick="showUserImages(<?php echo $user['id']; ?>)" class="image-count-link">
                                    <i class="bi bi-images"></i> <?php echo $user['image_count']; ?> 张图片
                                </a>
                                <?php else: ?>
                                    <span class="image-count-zero">暂无图片</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-buttons">
                                <?php if ($user['user_level'] !== '管理员'): ?>
                                <form method="post" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="change_level">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="level" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="普通用户" <?php echo $user['user_level'] === '普通用户' ? 'selected' : ''; ?>>普通用户</option>
                                        <option value="VIP用户" <?php echo $user['user_level'] === 'VIP用户' ? 'selected' : ''; ?>>VIP用户</option>
                                    </select>
                                </form>

                                <?php if ($user['status'] === '正常'): ?>
                                <button type="button" class="btn btn-warning btn-sm" onclick="showBanModal(<?php echo $user['id']; ?>)">
                                    <i class="bi bi-slash-circle"></i> 封禁
                                </button>
                                <?php else: ?>
                                <form method="post" action="" style="display: inline;" onsubmit="return confirm('确定要解除该用户的封禁状态吗？');">
                                    <input type="hidden" name="action" value="unban">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="bi bi-check-circle"></i> 解封
                                    </button>
                                </form>
                                <?php endif; ?>

                                <form method="post" action="" style="display: inline;" onsubmit="return confirm('确定要删除此用户吗？');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash"></i> 删除
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 封禁用户模态框 -->
    <div class="modal fade" id="banModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>封禁用户</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="ban">
                        <input type="hidden" name="user_id" id="banUserId">
                        <div class="mb-3">
                            <label for="banReason" class="form-label">封禁原因</label>
                            <textarea class="form-control" id="banReason" name="ban_reason" required rows="3" placeholder="请输入封禁原因..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-shield-exclamation me-1"></i>确认封禁
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 查看用户图片模态框 -->
    <div class="modal fade" id="userImagesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-images me-2"></i>用户图片</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="userImagesContainer" class="row g-3">
                        <!-- 图片将通过 AJAX 加载到这里 -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container"></div>

    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // 初始化模态框
        const banModal = new bootstrap.Modal(document.getElementById('banModal'));
        const userImagesModal = new bootstrap.Modal(document.getElementById('userImagesModal'));

        // 显示提示消息
        function showToast(message, type = 'success') {
            const toastContainer = document.querySelector('.toast-container');
            const toastHtml = `
                <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            const toastElement = toastContainer.lastElementChild;
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            // 自动移除toast元素
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }

        // 删除图片
        function deleteImage(imageId, element) {
            if (!confirm('确定要删除这张图片吗？此操作不可恢复。')) {
                return;
            }

            const formData = new FormData();
            formData.append('image_id', imageId);

            fetch('controllers/delete_image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 移除图片卡片
                    element.closest('.col-md-4').remove();
                    showToast('图片删除成功');
                    
                    // 检查是否还有图片
                    const container = document.getElementById('userImagesContainer');
                    if (!container.querySelector('.col-md-4')) {
                        container.innerHTML = '<div class="col-12 text-center py-5 text-muted">暂无图片</div>';
                    }
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('删除失败，请重试', 'danger');
            });
        }

        // 显示封禁模态框
        function showBanModal(userId) {
            document.getElementById('banUserId').value = userId;
            banModal.show();
        }

        // 显示用户图片
        function showUserImages(userId, page = 1) {
            const container = document.getElementById('userImagesContainer');
            container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">加载中...</span></div></div>';
            userImagesModal.show();

            fetch(`controllers/get_user_images.php?user_id=${userId}&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 创建图片容器
                        let html = '<div class="row g-3 mb-3">';
                        
                        // 添加图片卡片
                        html += data.images.map(image => `
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <button type="button" class="delete-image" onclick="deleteImage(${image.id}, this)" title="删除图片">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <img src="${image.url.replace('controllers/', '')}" class="card-img-top" alt="${image.original_name}" style="height: 200px; object-fit: cover;">
                                    <div class="card-body">
                                        <p class="card-text small text-truncate" title="${image.original_name}">${image.original_name}</p>
                                        <p class="card-text small text-muted">${image.upload_date}</p>
                                    </div>
                                </div>
                            </div>
                        `).join('');
                        
                        html += '</div>';
                        
                        // 添加分页控件
                        if (data.pagination && data.pagination.total_pages > 1) {
                            html += '<div class="d-flex justify-content-between align-items-center mt-3">';
                            html += '<div class="text-muted">共 ' + data.pagination.total_images + ' 张图片</div>';
                            html += '<div class="btn-group">';
                            
                            // 上一页按钮
                            html += `<button type="button" class="btn btn-outline-primary btn-sm ${data.pagination.current_page === 1 ? 'disabled' : ''}"
                                onclick="showUserImages(${userId}, ${data.pagination.current_page - 1})"
                                ${data.pagination.current_page === 1 ? 'disabled' : ''}>
                                <i class="bi bi-chevron-left"></i> 上一页
                            </button>`;
                            
                            // 页码信息
                            html += `<button type="button" class="btn btn-outline-primary btn-sm disabled">
                                第 ${data.pagination.current_page} / ${data.pagination.total_pages} 页
                            </button>`;
                            
                            // 下一页按钮
                            html += `<button type="button" class="btn btn-outline-primary btn-sm ${data.pagination.current_page === data.pagination.total_pages ? 'disabled' : ''}"
                                onclick="showUserImages(${userId}, ${data.pagination.current_page + 1})"
                                ${data.pagination.current_page === data.pagination.total_pages ? 'disabled' : ''}>
                                下一页 <i class="bi bi-chevron-right"></i>
                            </button>`;
                            
                            html += '</div></div>';
                        }
                        
                        container.innerHTML = html || '<div class="col-12 text-center py-5 text-muted">暂无图片</div>';
                    } else {
                        container.innerHTML = `<div class="col-12"><div class="alert alert-danger">${data.message}</div></div>`;
                    }
                })
                .catch(error => {
                    container.innerHTML = '<div class="col-12"><div class="alert alert-danger">加载图片失败</div></div>';
                    console.error('Error:', error);
                });
        }
    </script>
</body>
</html> 
