<?php

class S3AB_S3_Uploader {
    
    private $endpoint;
    private $bucket;
    private $access_key;
    private $secret_key;
    private $region;
    
    public function __construct() {
        $s3_settings = get_option('s3ab_s3_settings', array());
        
        $this->endpoint = isset($s3_settings['endpoint']) ? trim($s3_settings['endpoint']) : '';
        $this->bucket = isset($s3_settings['bucket']) ? trim($s3_settings['bucket']) : '';
        $this->access_key = isset($s3_settings['access_key']) ? trim($s3_settings['access_key']) : '';
        $this->secret_key = isset($s3_settings['secret_key']) ? trim($s3_settings['secret_key']) : '';
        
        // 從 endpoint 自動偵測 region，如果沒有則使用預設值
        $this->region = $this->detect_region_from_endpoint();
    }
    
    private function detect_region_from_endpoint() {
        if (empty($this->endpoint)) {
            return 'us-east-1'; // AWS S3 預設
        }
        
        // 嘗試從 endpoint 提取 region
        if (preg_match('/s3[\.-]([a-z0-9-]+)\.amazonaws\.com/', $this->endpoint, $matches)) {
            return $matches[1];
        }
        
        // 對於其他 S3-Compatible 服務，使用預設值
        return 'us-east-1';
    }
    
    public function test_connection() {
        try {
            if (empty($this->endpoint)) {
                return array(
                    'success' => false,
                    'message' => '請先設定 S3 Endpoint',
                );
            }
            
            if (empty($this->bucket)) {
                return array(
                    'success' => false,
                    'message' => '請先設定 Bucket 名稱',
                );
            }
            
            if (empty($this->access_key) || empty($this->secret_key)) {
                return array(
                    'success' => false,
                    'message' => '請先設定 Access Key 和 Secret Key',
                );
            }
            
            // 測試列出 bucket
            $result = $this->list_objects('', 1);
            
            return array(
                'success' => true,
                'message' => 'S3 連線成功! Endpoint: ' . $this->endpoint,
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'S3 連線失敗: ' . $e->getMessage(),
            );
        }
    }
    
    public function upload_backup($backup_id, $local_dir) {
        try {
            $files = scandir($local_dir);
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $local_file = $local_dir . $file;
                $s3_key = 'backups/' . get_option('siteurl') . '/' . $backup_id . '/' . $file;
                
                $this->put_object($local_file, $s3_key);
            }
            
            return array(
                'success' => true,
                'message' => '上傳完成',
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }
    
    public function list_backups() {
        $prefix = 'backups/' . get_option('siteurl') . '/';
        $objects = $this->list_objects($prefix);
        
        $backups = array();
        
        foreach ($objects as $object) {
            // 解析備份 ID
            if (preg_match('#/([0-9-]+)/metadata\.json$#', $object['Key'], $matches)) {
                $backup_id = $matches[1];
                
                // 取得 metadata
                $metadata_content = $this->get_object($object['Key']);
                $metadata = json_decode($metadata_content, true);
                
                $backups[] = array(
                    'id' => $backup_id,
                    'date' => $metadata['backup_time'],
                    'size' => $this->calculate_backup_size($backup_id),
                    'metadata' => $metadata,
                );
            }
        }
        
        return $backups;
    }
    
    public function download_backup($backup_id, $local_dir) {
        $prefix = 'backups/' . get_option('siteurl') . '/' . $backup_id . '/';
        $objects = $this->list_objects($prefix);
        
        wp_mkdir_p($local_dir);
        
        foreach ($objects as $object) {
            $filename = basename($object['Key']);
            $local_file = $local_dir . $filename;
            
            $content = $this->get_object($object['Key']);
            file_put_contents($local_file, $content);
        }
        
        return true;
    }
    
    public function delete_backup($backup_id) {
        $prefix = 'backups/' . get_option('siteurl') . '/' . $backup_id . '/';
        $objects = $this->list_objects($prefix);
        
        foreach ($objects as $object) {
            $this->delete_object($object['Key']);
        }
        
        return true;
    }
    
    // === AWS Signature V4 實作 ===
    
    private function put_object($local_file, $s3_key) {
        $content = file_get_contents($local_file);
        $content_type = mime_content_type($local_file);
        
        $url = $this->get_url($s3_key);
        $headers = $this->sign_request('PUT', $s3_key, $content, $content_type);
        
        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'headers' => $headers,
            'body' => $content,
            'timeout' => 300,
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            throw new Exception('上傳失敗: HTTP ' . $code);
        }
    }
    
    private function get_object($s3_key) {
        $url = $this->get_url($s3_key);
        $headers = $this->sign_request('GET', $s3_key);
        
        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 300,
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        return wp_remote_retrieve_body($response);
    }
    
    private function list_objects($prefix = '', $max_keys = 1000) {
        $url = $this->get_url('') . '?list-type=2&prefix=' . urlencode($prefix) . '&max-keys=' . $max_keys;
        $headers = $this->sign_request('GET', '', '', '', '?list-type=2&prefix=' . urlencode($prefix) . '&max-keys=' . $max_keys);
        
        $response = wp_remote_get($url, array(
            'headers' => $headers,
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $xml = wp_remote_retrieve_body($response);
        $doc = simplexml_load_string($xml);
        
        $objects = array();
        foreach ($doc->Contents as $content) {
            $objects[] = array(
                'Key' => (string)$content->Key,
                'Size' => (int)$content->Size,
                'LastModified' => (string)$content->LastModified,
            );
        }
        
        return $objects;
    }
    
    private function delete_object($s3_key) {
        $url = $this->get_url($s3_key);
        $headers = $this->sign_request('DELETE', $s3_key);
        
        $response = wp_remote_request($url, array(
            'method' => 'DELETE',
            'headers' => $headers,
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
    }
    
    private function get_url($key) {
        if (empty($this->endpoint)) {
            // 如果沒有 endpoint，使用 AWS S3 標準格式
            return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/" . ltrim($key, '/');
        }
        
        // 處理不同的 endpoint 格式
        $endpoint = rtrim($this->endpoint, '/');
        $key = ltrim($key, '/');
        
        // 檢查 endpoint 是否已經包含 bucket
        if (strpos($endpoint, $this->bucket) !== false) {
            // endpoint 已經包含 bucket（例如：https://bucket.nyc3.digitaloceanspaces.com）
            return $endpoint . '/' . $key;
        } else {
            // endpoint 不包含 bucket（例如：https://nyc3.digitaloceanspaces.com）
            return $endpoint . '/' . $this->bucket . '/' . $key;
        }
    }
    
    private function sign_request($method, $key, $payload = '', $content_type = 'application/octet-stream', $query_string = '') {
        $host = parse_url($this->get_url(''), PHP_URL_HOST);
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        
        $payload_hash = hash('sha256', $payload);
        
        $canonical_headers = "host:{$host}\nx-amz-content-sha256:{$payload_hash}\nx-amz-date:{$timestamp}\n";
        $signed_headers = 'host;x-amz-content-sha256;x-amz-date';
        
        $canonical_request = "{$method}\n/{$key}\n{$query_string}\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
        
        $scope = "{$date}/{$this->region}/s3/aws4_request";
        $string_to_sign = "AWS4-HMAC-SHA256\n{$timestamp}\n{$scope}\n" . hash('sha256', $canonical_request);
        
        $signing_key = $this->get_signing_key($date);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        
        $authorization = "AWS4-HMAC-SHA256 Credential={$this->access_key}/{$scope}, SignedHeaders={$signed_headers}, Signature={$signature}";
        
        return array(
            'Host' => $host,
            'X-Amz-Date' => $timestamp,
            'X-Amz-Content-SHA256' => $payload_hash,
            'Authorization' => $authorization,
            'Content-Type' => $content_type,
        );
    }
    
    private function get_signing_key($date) {
        $k_secret = 'AWS4' . $this->secret_key;
        $k_date = hash_hmac('sha256', $date, $k_secret, true);
        $k_region = hash_hmac('sha256', $this->region, $k_date, true);
        $k_service = hash_hmac('sha256', 's3', $k_region, true);
        return hash_hmac('sha256', 'aws4_request', $k_service, true);
    }
    
    private function calculate_backup_size($backup_id) {
        $prefix = 'backups/' . get_option('siteurl') . '/' . $backup_id . '/';
        $objects = $this->list_objects($prefix);
        
        $total_size = 0;
        foreach ($objects as $object) {
            $total_size += $object['Size'];
        }
        
        return size_format($total_size);
    }
}
