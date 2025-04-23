<?php
// 设置404状态码
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - 页面未找到</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.7.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .error-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .error-card {
            max-width: 500px;
            padding: 2rem;
            text-align: center;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #343a40;
        }
        .error-message {
            color: #6c757d;
            margin-bottom: 2rem;
        }
        .btn-home {
            padding: 0.5rem 2rem;
            border-radius: 25px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <i class="bi bi-exclamation-triangle error-icon"></i>
            <h1 class="error-title">404</h1>
            <p class="error-message">抱歉，您访问的页面不存在或已被移除</p>
            <div class="d-grid gap-2">
                <a href="/views/index" class="btn btn-primary btn-home">
                    <i class="bi bi-house-door"></i> 返回首页
                </a>
            </div>
        </div>
    </div>
</body>
</html> 
