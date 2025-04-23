<?php
// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// 检查是否已安装
if (file_exists('config.php')) {
    require_once 'config.php';
    if (defined('INSTALLED') && INSTALLED === true) {
        header('Location: ../views/index');
        exit;
    }
}

// 如果已经提交数据库配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['smtp_config'])) {
    // 检查必要的POST数据是否存在
    if (!isset($_POST['db_host']) || !isset($_POST['db_user']) || !isset($_POST['db_pass'])) {
        $error = '请填写所有数据库配置信息';
    } else {
        $db_host = $_POST['db_host'];
        $db_user = $_POST['db_user'];
        $db_pass = $_POST['db_pass'];
        $db_name = isset($_POST['db_name']) ? $_POST['db_name'] : 'pngmain';
        
        try {
            // 测试数据库连接
            $pdo = new PDO("mysql:host=" . $db_host, $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 创建数据库（如果不存在）
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $db_name . "`");
            
            // 重新连接，确保使用正确的数据库
            $pdo = new PDO("mysql:host=" . $db_host . ";dbname=" . $db_name, $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 创建用户表
            $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `email` VARCHAR(100) NOT NULL UNIQUE,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_login_ip` VARCHAR(45) DEFAULT NULL,
                `last_login_time` DATETIME DEFAULT NULL,
                `session_id` VARCHAR(64) DEFAULT NULL,
                `user_level` ENUM('普通用户', 'VIP用户', '管理员') NOT NULL DEFAULT '普通用户',
                `status` ENUM('正常', '封禁') NOT NULL DEFAULT '正常',
                `ban_reason` TEXT DEFAULT NULL,
                `ban_time` DATETIME DEFAULT NULL
            )");
            
            // 创建文件夹表
            $sql = "CREATE TABLE IF NOT EXISTS folders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            $pdo->exec($sql);
            
            // 创建图片表
            $sql = "CREATE TABLE IF NOT EXISTS images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                size INT NOT NULL,
                upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                folder_id INT DEFAULT NULL,
                file_hash VARCHAR(32) NOT NULL DEFAULT '',
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
            )";
            $pdo->exec($sql);
            
            // 验证表是否创建成功
            $stmt = $pdo->query("SHOW TABLES LIKE 'images'");
            if ($stmt->rowCount() == 0) {
                throw new Exception('表创建失败，请检查数据库权限');
            }
            
            // 更新现有表结构
            try {
                $pdo->exec("ALTER TABLE images ADD COLUMN file_hash VARCHAR(32) NOT NULL DEFAULT ''");
            } catch (PDOException $e) {
                // 如果字段已存在，忽略错误
                if ($e->getCode() != '42S21') {
                    throw $e;
                }
            }
            
            // 为现有图片计算哈希值
            $stmt = $pdo->query("SELECT id, filename FROM images WHERE file_hash = ''");
            while ($row = $stmt->fetch()) {
                $filepath = dirname(__FILE__) . '/' . $row['filename'];
                if (file_exists($filepath)) {
                    $file_hash = md5_file($filepath);
                    $updateStmt = $pdo->prepare("UPDATE images SET file_hash = ? WHERE id = ?");
                    $updateStmt->execute([$file_hash, $row['id']]);
                }
            }
            
            // 生成随机管理 URL
            $admin_path = 'admin_' . bin2hex(random_bytes(16));
            
            // 复制admin.php到新的管理路径install.lock
            if (!copy('admin.php', $admin_path . '.php')) {
                throw new Exception('无法创建管理页面文件');
            }
            
            // 删除原始的admin.php
            if (file_exists('admin.php')) {
                unlink('admin.php');
            }
            
            // 生成数据库配置文件
            $config_content = "<?php\n";
            $config_content .= "// 开启错误报告\n";
            $config_content .= "error_reporting(E_ALL);\n";
            $config_content .= "ini_set('display_errors', 1);\n\n";
            
            // 数据库配置
            $config_content .= "// 数据库配置\n";
            $config_content .= "define('DB_HOST', '" . addslashes($db_host) . "');\n";
            $config_content .= "define('DB_USER', '" . addslashes($db_user) . "');\n";
            $config_content .= "define('DB_PASS', '" . addslashes($db_pass) . "');\n";
            $config_content .= "define('DB_NAME', '" . addslashes($db_name) . "');\n\n";
            
            // 站点配置
            $config_content .= "// 站点配置\n";
            $config_content .= "define('SITE_NAME', '图片上传系统');\n";
            $config_content .= "define('SITE_URL', 'http://' . \$_SERVER['HTTP_HOST'] . dirname(\$_SERVER['PHP_SELF']));\n";
            $config_content .= "define('ADMIN_PATH', '" . $admin_path . "');\n\n";
            
            // 上传配置
            $config_content .= "// 上传配置\n";
            $config_content .= "define('UPLOAD_DIR', 'uploads/');\n";
            $config_content .= "define('UPLOAD_URL', SITE_URL . '/' . UPLOAD_DIR);\n\n";
            
            // 文件类型限制
            $config_content .= "// 允许的文件类型\n";
            $config_content .= "define('ALLOWED_TYPES', [\n";
            $config_content .= "    'image/jpeg',\n";
            $config_content .= "    'image/png',\n";
            $config_content .= "    'image/gif'\n";
            $config_content .= "]);\n\n";
            
            // 文件大小限制
            $config_content .= "// 最大文件大小（字节）\n";
            $config_content .= "define('MAX_FILE_SIZE', 5 * 1024 * 1024);  // 5MB\n";
            
            // 创建uploads目录
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            // 写入配置文件
            if (file_put_contents('config.php', $config_content)) {
                $_SESSION['db_config'] = true;
                $_SESSION['admin_path'] = $admin_path;
                header('Location: install.php?step=smtp');
                exit;
            } else {
                throw new Exception('无法写入配置文件');
            }
            
        } catch(Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// 检查是否已完成数据库配置
if (isset($_GET['step']) && $_GET['step'] === 'smtp') {
    if (!isset($_SESSION['db_config'])) {
        header('Location: install.php');
        exit;
    }
}

// 如果已经提交SMTP配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['smtp_config'])) {
    $smtp_host = $_POST['smtp_host'];
    $smtp_port = $_POST['smtp_port'];
    $smtp_username = $_POST['smtp_username'];
    $smtp_password = $_POST['smtp_password'];
    $smtp_from_email = $_POST['smtp_from_email'];
    $smtp_from_name = $_POST['smtp_from_name'];
    
    try {
        // 读取现有配置文件
        $config_content = file_get_contents('config.php');
        
        // 添加SMTP配置
        $config_content .= "\n// SMTP邮件服务器配置\n";
        $config_content .= "define('SMTP_HOST', '" . addslashes($smtp_host) . "');\n";
        $config_content .= "define('SMTP_PORT', " . intval($smtp_port) . ");\n";
        $config_content .= "define('SMTP_USERNAME', '" . addslashes($smtp_username) . "');\n";
        $config_content .= "define('SMTP_PASSWORD', '" . addslashes($smtp_password) . "');\n";
        $config_content .= "define('SMTP_FROM_EMAIL', '" . addslashes($smtp_from_email) . "');\n";
        $config_content .= "define('SMTP_FROM_NAME', '" . addslashes($smtp_from_name) . "');\n";
        $config_content .= "define('SMTP_SECURE', 'ssl');\n";
        $config_content .= "define('SMTP_AUTH', true);\n";
        $config_content .= "define('SMTP_DEBUG', false);\n";
        
        // 写入配置文件
        if (file_put_contents('config.php', $config_content)) {
            $_SESSION['smtp_config'] = true;
            header('Location: install.php?step=admin');
            exit;
        } else {
            throw new Exception('无法写入SMTP配置');
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// 如果已经提交管理员配置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_config'])) {
    $admin_username = $_POST['admin_username'];
    $admin_password = $_POST['admin_password'];
    $admin_email = $_POST['admin_email'];
    
    try {
        // 检查配置文件是否存在
        if (!file_exists('config.php')) {
            throw new Exception('配置文件不存在，请先完成数据库配置');
        }
        
        // 包含配置文件以获取数据库常量
        require_once 'config.php';
        
        // 连接数据库
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 创建管理员账号
        $stmt = $db->prepare("INSERT INTO users (username, password, email, user_level) VALUES (?, ?, ?, '管理员')");
        $stmt->execute([$admin_username, password_hash($admin_password, PASSWORD_DEFAULT), $admin_email]);
    
        
        // 更新配置文件，添加INSTALLED变量（如果尚未定义）
        $config_content = file_get_contents('config.php');
        if (!defined('INSTALLED')) {
            $config_content .= "\n// 安装状态\n";
            $config_content .= "define('INSTALLED', true);\n";
            file_put_contents('config.php', $config_content);
        }
        
        // 显示成功页面
        echo '<!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>安装成功</title>
            <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css" rel="stylesheet">
            <style>
                body {
                    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                }
                .success-container {
                    max-width: 500px;
                    margin: 0 auto;
                    padding: 2rem;
                    background: rgba(255, 255, 255, 0.95);
                    border-radius: 15px;
                    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255,255,255,0.2);
                }
                .success-icon {
                    font-size: 4rem;
                    color: #28a745;
                    margin-bottom: 1rem;
                }
                .btn-group {
                    display: flex;
                    gap: 1rem;
                    margin-top: 2rem;
                }
                .btn {
                    flex: 1;
                    padding: 0.8rem;
                    font-size: 1.1rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                }
                .btn-primary {
                    background: linear-gradient(45deg, #2196F3, #1976D2);
                    border: none;
                }
                .btn-success {
                    background: linear-gradient(45deg, #28a745, #218838);
                    border: none;
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                }
            </style>
        </head>
        <body>
            <div class="success-container text-center">
                <i class="bi bi-check-circle-fill success-icon"></i>
                <h2 class="mb-4">安装成功！</h2>
                <p class="text-muted mb-4">系统已成功安装，您现在可以：</p>
                <div class="btn-group">
                    <a href="../views/index.php" class="btn btn-primary">
                        <i class="bi bi-house-door"></i>
                        进入首页
                    </a>
                    <a href="../' . ADMIN_PATH . '.php" class="btn btn-success">
                        <i class="bi bi-gear"></i>
                        管理后台
                    </a>
                </div>
            </div>
        </body>
        </html>';
        exit;
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// 显示SMTP配置页面
if (isset($_GET['step']) && $_GET['step'] === 'smtp' && isset($_SESSION['db_config'])) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SMTP配置 - 系统安装</title>
        <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="card p-5 shadow">
                <h1 class="text-center mb-4">SMTP邮件服务器配置</h1>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="smtp_config" value="1">
                    
                    <div class="mb-3">
                        <label for="smtp_host" class="form-label">SMTP服务器</label>
                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="smtpdm.aliyun.com" required>
                        <div class="form-text">例如：smtp.qq.com, smtp.163.com</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_port" class="form-label">SMTP端口</label>
                        <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="465" required>
                        <div class="form-text">SSL加密通常为465，TLS加密通常为587</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_username" class="form-label">SMTP用户名</label>
                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" required>
                        <div class="form-text">通常是您的邮箱地址</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_password" class="form-label">SMTP密码</label>
                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" required>
                        <div class="form-text">邮箱的SMTP授权码，不是邮箱密码</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_from_email" class="form-label">发件人邮箱</label>
                        <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="smtp_from_name" class="form-label">发件人名称</label>
                        <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name" value="图床系统" required>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">完成配置</button>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 显示管理员配置页面
if (isset($_GET['step']) && $_GET['step'] === 'admin' && isset($_SESSION['smtp_config'])) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理员配置 - 系统安装</title>
        <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="card p-5 shadow">
                <h1 class="text-center mb-4">管理员账号配置</h1>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="admin_config" value="1">
                    
                    <div class="mb-3">
                        <label for="admin_username" class="form-label">管理员用户名</label>
                        <input type="text" class="form-control" id="admin_username" name="admin_username" required>
                        <div class="form-text">请设置管理员账号的用户名</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_password" class="form-label">管理员密码</label>
                        <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                        <div class="form-text">请设置管理员账号的密码</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_email" class="form-label">管理员邮箱</label>
                        <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                        <div class="form-text">请设置管理员账号的邮箱</div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">完成安装</button>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 显示数据库配置页面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库配置 - 系统安装</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card p-5 shadow">
            <h1 class="text-center mb-4">数据库配置</h1>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="mb-3">
                    <label for="db_host" class="form-label">数据库主机</label>
                    <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                    <div class="form-text">通常为localhost或127.0.0.1</div>
                </div>
                
                <div class="mb-3">
                    <label for="db_user" class="form-label">数据库用户名</label>
                    <input type="text" class="form-control" id="db_user" name="db_user" required>
                </div>
                
                <div class="mb-3">
                    <label for="db_pass" class="form-label">数据库密码</label>
                    <input type="password" class="form-control" id="db_pass" name="db_pass" required>
                </div>
                
                <div class="mb-3">
                    <label for="db_name" class="form-label">数据库名</label>
                    <input type="text" class="form-control" id="db_name" name="db_name" value="pngmain" required>
                    <div class="form-text">如果数据库不存在将自动创建</div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">下一步：配置SMTP</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 
