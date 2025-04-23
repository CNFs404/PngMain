# 图床系统

一个基于 PHP 的简单图床系统，支持图片上传、管理和分享功能。

## 功能特性

- 用户注册与登录
- 图片上传与管理
- 图片分享与访问控制
- 管理员后台管理
- 邮件验证功能
- 支持多种图片格式
- 响应式设计，支持移动端访问

## 系统要求

- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- 支持 GD 或 Imagick 扩展
- 支持 SMTP 邮件服务
- 支持 mod_rewrite 的 Web 服务器（如 Apache）

## 安装说明

1. 将项目文件上传到 Web 服务器
2. 访问 `install.php` 进行安装
3. 按照安装向导完成以下步骤：
   - 数据库配置
   - SMTP 邮件服务器配置
   - 管理员账号设置

### 数据库配置

- 数据库主机
- 数据库用户名
- 数据库密码
- 数据库名称

### SMTP 配置

- SMTP 服务器地址
- SMTP 端口
- SMTP 用户名
- SMTP 密码
- 发件人邮箱
- 发件人名称

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
└── png/               # 上传文件目录
```

## 配置文件说明

`config.php` 包含以下主要配置项：

```php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_NAME', 'database_name');

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
