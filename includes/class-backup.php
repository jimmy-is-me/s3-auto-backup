<?php

class S3AB_Backup {
    
    private $backup_id;
    private $backup_dir;
    private $log = array();
    
    public function start() {
        try {
            $this->backup_id = date('Y-m-d-His') . '-' . substr(md5(time()), 0, 8);
            $this->backup_dir = S3AB_BACKUP_DIR . $this->backup_id . '/';
            
            // 建立備份目錄
            wp_mkdir_p($this->backup_dir);
            
            $this->log('開始備份: ' . $this->backup_id);
            
            $settings = get_option('s3ab_settings');
            
            // 1. 備份資料庫
            if ($settings['backup_database']) {
                $this->backup_database();
            }
            
            // 2. 備份檔案
            if ($settings['backup_files']) {
                $this->backup_files();
            }
            
            // 3. 建立 metadata
            $this->create_metadata();
            
            // 4. 上傳到 S3
            $this->upload_to_s3();
            
            // 5. 清理本地檔案
            if ($settings['delete_local']) {
                $this->cleanup_local();
            }
            
            $this->log('備份完成');
            
            return array(
                'success' => true,
                'backup_id' => $this->backup_id,
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
    
    private function backup_database() {
        global $wpdb;
        
        $this->log('開始備份資料庫...');
        
        $sql_file = $this->backup_dir . 'database.sql';
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        
        $sql = "-- WordPress Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // 取得建表語句
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_N);
            $sql .= "\n-- Table: {$table_name}\n";
            $sql .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
            $sql .= $create_table[1] . ";\n\n";
            
            // 取得資料
            $rows = $wpdb->get_results("SELECT * FROM `{$table_name}`", ARRAY_A);
            
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $wpdb->_escape($value) . "'";
                        }
                    }
                    $sql .= "INSERT INTO `{$table_name}` VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }
        
        file_put_contents($sql_file, $sql);
        
        // 壓縮資料庫
        if (function_exists('gzencode')) {
            $gz_file = $sql_file . '.gz';
            file_put_contents($gz_file, gzencode(file_get_contents($sql_file), 9));
            unlink($sql_file);
            
            $size = size_format(filesize($gz_file));
            $this->log("資料庫備份完成: {$size}");
        } else {
            $size = size_format(filesize($sql_file));
            $this->log("資料庫備份完成 (未壓縮): {$size}");
        }
    }
    
    private function backup_files() {
        $this->log('開始備份檔案...');
        
        // 使用 ZipArchive 備份檔案
        if (class_exists('ZipArchive')) {
            $this->backup_files_zip();
        } else {
            throw new Exception('ZipArchive 擴充功能未安裝');
        }
    }
    
    private function backup_files_zip() {
        $zip_file = $this->backup_dir . 'files.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zip_file, ZipArchive::CREATE) !== true) {
            throw new Exception('無法建立 ZIP 檔案');
        }
        
        // 要備份的目錄
        $paths = array(
            WP_CONTENT_DIR . '/themes',
            WP_CONTENT_DIR . '/plugins',
            WP_CONTENT_DIR . '/uploads',
        );
        
        // 排除的檔案
        $exclude_patterns = array(
            '/cache/',
            '/backup/',
            '/updraft/',
            '/wflogs/',
            '/wpvividbackups/',
            '.log',
        );
        
        $file_count = 0;
        
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen(WP_CONTENT_DIR) + 1);
                
                // 檢查是否要排除
                $should_exclude = false;
                foreach ($exclude_patterns as $pattern) {
                    if (strpos($file_path, $pattern) !== false) {
                        $should_exclude = true;
                        break;
                    }
                }
                
                if ($should_exclude) {
                    continue;
                }
                
                if ($file->isFile()) {
                    $zip->addFile($file_path, $relative_path);
                    $file_count++;
                    
                    if ($file_count % 100 === 0) {
                        $this->log("已加入 {$file_count} 個檔案...");
                    }
                }
            }
        }
        
        // 加入重要檔案
        $zip->addFile(ABSPATH . 'wp-config.php', 'wp-config.php');
        if (file_exists(ABSPATH . '.htaccess')) {
            $zip->addFile(ABSPATH . '.htaccess', '.htaccess');
        }
        
        $zip->close();
        
        $size = size_format(filesize($zip_file));
        $this->log("檔案備份完成: {$file_count} 個檔案, {$size}");
    }
    
    private function create_metadata() {
        $metadata = array(
            'backup_id' => $this->backup_id,
            'site_url' => get_site_url(),
            'site_name' => get_bloginfo('name'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $GLOBALS['wpdb']->db_version(),
            'backup_time' => current_time('mysql'),
            'timestamp' => time(),
            'files' => array(),
        );
        
        // 列出所有備份檔案
        $files = scandir($this->backup_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $metadata['files'][] = array(
                'name' => $file,
                'size' => filesize($this->backup_dir . $file),
            );
        }
        
        file_put_contents(
            $this->backup_dir . 'metadata.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        $this->log('建立 metadata 完成');
    }
    
    private function upload_to_s3() {
        $this->log('上傳到 S3...');
        
        $s3 = new S3AB_S3_Uploader();
        $result = $s3->upload_backup($this->backup_id, $this->backup_dir);
        
        if (!$result['success']) {
            throw new Exception('S3 上傳失敗: ' . $result['message']);
        }
        
        $this->log('S3 上傳完成');
    }
    
    private function cleanup_local() {
        $this->log('清理本地檔案...');
        
        $this->delete_directory($this->backup_dir);
        
        $this->log('本地清理完成');
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
        
        error_log('[S3 Auto Backup] ' . $message);
    }
}
