<?php
/**
 * 日誌記錄類別
 */

if (!defined('ABSPATH')) {
    exit;
}

class S3AB_Logger {
    
    private static $log_file;
    
    public static function init() {
        $log_dir = S3AB_BACKUP_DIR . 'logs/';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        self::$log_file = $log_dir . 'backup-' . date('Y-m') . '.log';
    }
    
    public static function log($message, $type = 'info') {
        if (empty(self::$log_file)) {
            self::init();
        }
        
        $timestamp = current_time('mysql');
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            strtoupper($type),
            $message
        );
        
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // 同時記錄到 WordPress 錯誤日誌
        error_log('[S3 Auto Backup] ' . $message);
    }
    
    public static function get_logs($lines = 100) {
        if (empty(self::$log_file) || !file_exists(self::$log_file)) {
            return array();
        }
        
        $file = file(self::$log_file);
        if ($file === false) {
            return array();
        }
        
        $file = array_slice($file, -$lines);
        $logs = array();
        
        foreach ($file as $line) {
            if (preg_match('/^\[([^\]]+)\] \[([^\]]+)\] (.+)$/', trim($line), $matches)) {
                $logs[] = array(
                    'time' => $matches[1],
                    'type' => $matches[2],
                    'message' => $matches[3],
                );
            }
        }
        
        return $logs;
    }
    
    public static function clear_logs() {
        if (!empty(self::$log_file) && file_exists(self::$log_file)) {
            file_put_contents(self::$log_file, '');
        }
    }
}
