<?php

class S3AB_Restore {
    
    private $backup_id;
    private $restore_dir;
    private $log = array();
    
    public function restore_from_directory($directory) {
        try {
            $this->restore_dir = rtrim($directory, '/') . '/';
            
            if (!is_dir($this->restore_dir)) {
                throw new Exception('還原目錄不存在');
            }
            
            $this->log('開始從上傳檔案還原...');
            
            // 還原資料庫
            $this->restore_database();
            
            // 還原檔案
            $this->restore_files();
            
            $this->log('還原完成');
            
            return array(
                'success' => true,
                'log' => $this->log,
            );
            
        } catch (Exception $e) {
            $this->log('錯誤: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'log' => $this->log,
            );
        }
    }
    
    public function start($backup_id) {
        try {
            $this->backup_id = $backup_id;
            $this->restore_dir = S3AB_BACKUP_DIR . 'restore-' . time() . '/';
            
            wp_mkdir_p($this->restore_dir);
            
            $this->log('開始還原備份: ' . $backup_id);
            
            // 1. 從 S3 下載備份
            $this->download_from_s3();
            
            // 2. 還原資料庫
            $this->restore_database();
            
            // 3. 還原檔案
            $this->restore_files();
            
            // 4. 清理
            $this->cleanup();
            
            $this->log('還原完成');
            
            return array(
                'success' => true,
                'log' => $this->log,
            );
            
        } catch (Exception $e) {
            $this->log('錯誤: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'log' => $this->log,
            );
        }
    }
    
    private function download_from_s3() {
        $this->log('從 S3 下載備份...');
        
        $s3 = new S3AB_S3_Uploader();
        $s3->download_backup($this->backup_id, $this->restore_dir);
        
        $this->log('下載完成');
    }
    
    private function restore_database() {
        global $wpdb;
        
        $this->log('開始還原資料庫...');
        
        // 尋找資料庫檔案
        $sql_file = $this->restore_dir . 'database.sql';
        $gz_file = $this->restore_dir . 'database.sql.gz';
        
        if (file_exists($gz_file)) {
            // 解壓縮
            $sql_content = gzdecode(file_get_contents($gz_file));
            file_put_contents($sql_file, $sql_content);
        } elseif (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
        } else {
            throw new Exception('找不到資料庫備份檔案');
        }
        
        // 執行 SQL
        $queries = explode(";\n", $sql_content);
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query) || strpos($query, '--') === 0) {
                continue;
            }
            
            $wpdb->query($query);
        }
        
        $this->log('資料庫還原完成');
    }
    
    private function restore_files() {
        $this->log('開始還原檔案...');
        
        $zip_file = $this->restore_dir . 'files.zip';
        
        if (!file_exists($zip_file)) {
            throw new Exception('找不到檔案備份');
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== true) {
            throw new Exception('無法開啟 ZIP 檔案');
        }
        
        // 解壓縮到 wp-content
        $zip->extractTo(WP_CONTENT_DIR);
        $zip->close();
        
        // 還原 wp-config.php
        $wp_config = $this->restore_dir . 'wp-config.php';
        if (file_exists($wp_config)) {
            copy($wp_config, ABSPATH . 'wp-config.php');
        }
        
        // 還原 .htaccess
        $htaccess = $this->restore_dir . '.htaccess';
        if (file_exists($htaccess)) {
            copy($htaccess, ABSPATH . '.htaccess');
        }
        
        $this->log('檔案還原完成');
    }
    
    private function cleanup() {
        $this->log('清理暫存檔案...');
        
        $this->delete_directory($this->restore_dir);
        
        $this->log('清理完成');
    }
    
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    private function log($message) {
        $this->log[] = array(
            'time' => current_time('mysql'),
            'message' => $message,
        );
        
        error_log('[S3 Auto Backup Restore] ' . $message);
    }
}
