<?php
/**
 * 本地備份管理類別
 */

if (!defined('ABSPATH')) {
    exit;
}

class S3AB_Local_Backup {
    
    /**
     * 列出所有本地備份
     */
    public static function list_backups() {
        $backups = array();
        
        if (!is_dir(S3AB_BACKUP_DIR)) {
            return $backups;
        }
        
        $dirs = scandir(S3AB_BACKUP_DIR);
        
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || $dir === 'logs' || !is_dir(S3AB_BACKUP_DIR . $dir)) {
                continue;
            }
            
            $backup_dir = S3AB_BACKUP_DIR . $dir . '/';
            $metadata_file = $backup_dir . 'metadata.json';
            
            if (file_exists($metadata_file)) {
                $metadata = json_decode(file_get_contents($metadata_file), true);
                if ($metadata) {
                    $total_size = 0;
                    $files = scandir($backup_dir);
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..') {
                            $file_path = $backup_dir . $file;
                            if (is_file($file_path)) {
                                $total_size += filesize($file_path);
                            }
                        }
                    }
                    
                    $backups[] = array(
                        'id' => $dir,
                        'date' => isset($metadata['backup_time']) ? $metadata['backup_time'] : date('Y-m-d H:i:s', filemtime($metadata_file)),
                        'size' => size_format($total_size),
                        'metadata' => $metadata,
                        'type' => 'local',
                    );
                }
            }
        }
        
        // 按日期排序（最新的在前）
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $backups;
    }
    
    /**
     * 刪除本地備份
     */
    public static function delete_backup($backup_id) {
        $backup_dir = S3AB_BACKUP_DIR . $backup_id . '/';
        
        if (!is_dir($backup_dir)) {
            return false;
        }
        
        return self::delete_directory($backup_dir);
    }
    
    /**
     * 遞迴刪除目錄
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::delete_directory($path) : @unlink($path);
        }
        
        return @rmdir($dir);
    }
}
