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
        if (!file_exists($local_file)) {
            throw new Exception('檔案不存在: ' . $local_file);
        }
        
        $content = file_get_contents($local_file);
        if ($content === false) {
            throw new Exception('無法讀取檔案: ' . $local_file);
        }
        
        $content_type = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($local_file);
            if ($detected) {
                $content_type = $detected;
            }
        }
        
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
        if ($code !== 200 && $code !== 201) {
            $body = wp_remote_retrieve_body($response);
            $error_msg = '上傳失敗: HTTP ' . $code;
            if (!empty($body)) {
                // 嘗試解析 XML 錯誤訊息
                $xml = @simplexml_load_string($body);
                if ($xml && isset($xml->Message)) {
                    $error_msg .= ' - ' . (string)$xml->Message;
                } else {
                    $error_msg .= ' - ' . substr(strip_tags($body), 0, 200);
                }
            }
            throw new Exception($error_msg);
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
        // 構建完整的 URL 以獲取 host 和路徑
        $full_url = $this->get_url($key);
        $parsed_url = parse_url($full_url);
        $host = $parsed_url['host'];
        
        // 構建 canonical URI
        // 從完整 URL 中提取路徑，移除 bucket 部分（如果存在）
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '/';
        
        // 如果路徑包含 bucket，移除它（因為 canonical URI 不應包含 bucket）
        $bucket_prefix = '/' . $this->bucket;
        if (strpos($path, $bucket_prefix) === 0) {
            $path = substr($path, strlen($bucket_prefix));
        }
        
        // 確保以 / 開頭
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }
        
        // URL 編碼 canonical URI（AWS S3 規範：除了 / 之外都要編碼）
        $canonical_uri = $this->uri_encode($path, false);
        
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        
        $payload_hash = hash('sha256', $payload);
        
        // 處理 query string
        $canonical_querystring = '';
        if (!empty($query_string)) {
            // 移除前導的 ?
            $query_string = ltrim($query_string, '?');
            // 解析並排序查詢參數
            parse_str($query_string, $params);
            ksort($params);
            $parts = array();
            foreach ($params as $k => $v) {
                $parts[] = $this->uri_encode($k, true) . '=' . $this->uri_encode($v, true);
            }
            $canonical_querystring = implode('&', $parts);
        }
        
        $canonical_headers = "host:{$host}\nx-amz-content-sha256:{$payload_hash}\nx-amz-date:{$timestamp}\n";
        $signed_headers = 'host;x-amz-content-sha256;x-amz-date';
        
        $canonical_request = "{$method}\n{$canonical_uri}\n{$canonical_querystring}\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
        
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
    
    /**
     * URI 編碼（AWS S3 規範）
     */
    private function uri_encode($uri, $encode_slash = false) {
        $encoded = '';
        for ($i = 0; $i < strlen($uri); $i++) {
            $char = $uri[$i];
            if (preg_match('/[A-Za-z0-9\-_.~]/', $char)) {
                $encoded .= $char;
            } elseif ($char === '/' && !$encode_slash) {
                $encoded .= '/';
            } else {
                $encoded .= '%' . strtoupper(sprintf('%02x', ord($char)));
            }
        }
        return $encoded;
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
