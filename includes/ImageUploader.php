<?php
class ImageUploader {
    private $db;
    private $baseUploadDir = 'PNG/'; // 基础上传目录
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        
        // 确保基础上传目录存在
        if (!file_exists($this->baseUploadDir)) {
            mkdir($this->baseUploadDir, 0777, true);
        }
        
        // 确保当天的目录存在
        $this->ensureDailyDirectory();
    }
    
    private function getDailyDirectory() {
        // 获取当天零点的时间戳
        return date('Ymd', time());
    }
    
    private function ensureDailyDirectory() {
        $dailyDir = $this->baseUploadDir . $this->getDailyDirectory() . '/';
        if (!file_exists($dailyDir)) {
            mkdir($dailyDir, 0777, true);
        }
        return $dailyDir;
    }
    
    private function generateFilename($originalName, $ip) {
        // 获取时间戳
        $timestamp = time();
        
        // 处理IP地址（去掉点）
        $ip = str_replace('.', '', $ip);
        
        // 获取文件扩展名
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        // 生成新文件名：时间戳_IP.扩展名
        return $timestamp . '_' . $ip . '.' . $extension;
    }
    
    public function upload($file) {
        // 验证文件
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception('无效的文件参数');
        }

        // 检查文件错误
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('文件大小超过限制');
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('没有文件被上传');
            default:
                throw new Exception('未知错误');
        }

        // 检查文件大小
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('文件大小不能超过' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB');
        }

        // 检查MIME类型
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
        if (!in_array($mime_type, ALLOWED_TYPES)) {
            throw new Exception('不支持的文件类型');
        }

        // 获取当天的目录
        $dailyDir = $this->ensureDailyDirectory();
        
        // 生成文件名
        $filename = $this->generateFilename($file['name'], $_SERVER['REMOTE_ADDR']);
        
        // 完整的文件路径（相对于网站根目录）
        $relativePath = $this->getDailyDirectory() . '/' . $filename;
        $fullFilePath = $this->baseUploadDir . $relativePath;

        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $fullFilePath)) {
            throw new Exception('文件上传失败');
        }

        // 保存到数据库
        $stmt = $this->db->prepare("INSERT INTO images (filename, original_name, mime_type, size, upload_date, uploader_ip, uploader_agent) VALUES (?, ?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([
            $relativePath, // 保存相对路径
            $file['name'], 
            $mime_type, 
            $file['size'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        return [
            'filename' => $relativePath,
            'url' => UPLOAD_URL . $relativePath
        ];
    }
}
?> 
