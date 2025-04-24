![](https://img.shields.io/github/stars/pandao/editor.md.svg) ![](https://img.shields.io/github/forks/pandao/editor.md.svg) ![](https://img.shields.io/github/tag/pandao/editor.md.svg)
# PngMain图床系统 🌅

一个基于 PHP 的简单图床系统，支持图片上传、管理和分享功能。
![主页](https://github.com/user-attachments/assets/b0c3d46d-8186-472a-ad40-760e3bcfa585)
![多张上传](https://github.com/user-attachments/assets/863c636e-bd46-4576-9159-fbb416c511ad)
![文件夹](https://github.com/user-attachments/assets/cc6f9dd1-9afe-430f-b2a1-5be36303f9a2)
![游览](https://github.com/user-attachments/assets/cd3c0097-6124-4f84-bceb-acfa1dc334a2)
![主页](https://github.com/user-attachments/assets/7bf2b1ae-64f6-4ae9-874d-11c32b39174a)

## 功能特性

- 用户注册与登录
- 图片上传与管理
- 图片分享与访问控制
- 管理员后台管理
- 邮件验证功能
- 支持多种图片格式
- 响应式设计，支持移动端访问

## 系统要求

### 服务器要求
- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- Web服务器（推荐 Apache 或 Nginx）
- 支持 GD 或 Imagick 扩展
- 支持 SMTP 邮件服务
- 支持 mod_rewrite 模块

### 目录权限要求
确保以下目录具有写入权限：
- `uploads/` 目录
- `PNG/` 目录
- `config.php` 文件

## 快速开始

### 1. 下载安装
1. 下载最新版本
2. 解压到网站根目录
3. 确保目录权限正确

### 2. 配置 Web 服务器

#### Nginx 配置
在 Nginx 配置文件中添加：
```nginx
location / {
    if (!-e $request_filename){
        rewrite ^/folder/([0-9]+)$ /views/index.php?folder=$1 last;
        rewrite ^/([^\.]+)$ /$1.php last;
        rewrite ^/([^\.]+)$ /$1.html last;
        rewrite ^(.*)$ $1.jpg last;
        rewrite ^(.*)$ $1.jpeg last;
        rewrite ^(.*)$ $1.png last;
        rewrite ^(.*)$ $1.gif last;
    }
}
location /PNG/ {
    add_header Access-Control-Allow-Origin *;
    expires 7d;
    add_header Cache-Control "public, no-transform";
    try_files $uri $uri.jpg $uri.jpeg $uri.png $uri.gif 404;
}
```

### 配置默认文档

```
book/website.html
```

### 3. 安装步骤

1. 访问 `http://你的域名/install`
2. 按照安装向导完成以下步骤：

#### 3.1 数据库配置
- 数据库主机（通常是 localhost）
- 数据库用户名
- 数据库密码
- 数据库名称（默认为 pngmain）

#### 3.2 SMTP 配置
- SMTP 服务器地址
- SMTP 端口（通常是 465 或 587）
- SMTP 用户名（邮箱地址）
- SMTP 密码
- 发件人邮箱
- 发件人名称

#### 3.3 管理员账号设置
- 管理员用户名
- 管理员密码
- 管理员邮箱

## 目录结构

```
├── admin.php              # 管理后台初始入口[安装成功后自动删除变成安全加密文件名]
├── config.php             # 系统配置文件
├── install.php            # 安装程序
├── login.php              # 登录页
├── register.php           # 注册页
├── 404.php                # 错误页面
├── logout.php             # 退出登录页
├── models/                # 模型文件
│   ├── Database.php       # 数据库操作类
│   ├── EmailService.php   # 邮件服务类
│   └── Image.php          # 图片处理类
├── views/                 # 视图文件
│   ├── index.php          # 首页
│   └── register.php       # 注册页
└── PNG/                   # 上传文件目录
```

## 配置文件说明

`config.php` 包含以下主要配置项：

```php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_NAME', 'pngmain');

// SMTP 配置
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'email@example.com');
define('SMTP_PASSWORD', 'password');
define('SMTP_FROM_EMAIL', 'email@example.com');
define('SMTP_FROM_NAME', '系统名称');

// 系统配置
define('SITE_NAME', '图床系统');
define('SITE_URL', 'http://example.com');
define('ADMIN_PATH', 'admin_path');
define('INSTALLED', true);
```

## 使用说明

### 用户功能
1. 注册账号
2. 登录系统
3. 上传图片
4. 管理图片
5. 分享图片

### 管理员功能
1. 用户管理
2. 图片管理
3. 系统设置
4. 数据统计

## 安全说明

- 所有上传的图片都会进行安全检查
- 密码使用 bcrypt 加密存储
- 支持 CSRF 防护
- 支持 XSS 防护
- 支持 SQL 注入防护

## 维护建议

### 定期维护
- 定期备份数据库
- 定期清理上传目录
- 定期检查系统日志

### 安全维护
- 定期更新系统
- 定期检查文件权限
- 定期修改管理员密码

## 更新日志

### v1.0.0
- 初始版本发布
- 基础功能实现
- 安装程序完成

## 贡献指南

1. Fork 项目
2. 创建特性分支
3. 提交更改
4. 推送到分支
5. 创建 Pull Request

## 许可证

MIT License

## 联系方式

如有问题或建议，请通过以下方式联系：
- 邮箱：3366251627@qq.com
- GitHub：https://github.com/CNFs404
