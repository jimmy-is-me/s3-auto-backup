<?php
/**
 * Plugin Name: S3 Auto Backup Pro
 * Plugin URI: https://yoursite.com/s3-auto-backup
 * Description: 自動備份 WordPress 網站到 S3,支援一鍵還原
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * Text Domain: s3-auto-backup
 */

// 防止直接存取
if (!defined('ABSPATH')) {
    exit;
}

// 定義常數
define('S3AB_VERSION', '1.0.0');
define('S3AB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('S3AB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('S3AB_BACKUP_DIR', WP_CONTENT_DIR . '/s3-backups/');

// 載入依賴
require_once S3AB_PLUGIN_DIR . 'includes/class-backup.php';
require_once S3AB_PLUGIN_DIR . 'includes/class-restore.php';
require_once S3AB_PLUGIN_DIR . 'includes/class-s3-uploader.php';
require_once S3AB_PLUGIN_DIR . 'includes/class-scheduler.php';

// 主類別
class S3_Auto_Backup {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 啟用外掛時的動作
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // 停用外掛時的動作
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // 載入管理介面
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 載入樣式和腳本
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX 處理
        add_action('wp_ajax_s3ab_start_backup', array($this, 'ajax_start_backup'));
        add_action('wp_ajax_s3ab_restore_backup', array($this, 'ajax_restore_backup'));
        add_action('wp_ajax_s3ab_list_backups', array($this, 'ajax_list_backups'));
        add_action('wp_ajax_s3ab_delete_backup', array($this, 'ajax_delete_backup'));
        add_action('wp_ajax_s3ab_test_connection', array($this, 'ajax_test_connection'));
        
        // WP-Cron 排程
        add_action('s3ab_scheduled_backup', array($this, 'run_scheduled_backup'));
    }
    
    public function activate() {
        // 建立備份目錄
        if (!file_exists(S3AB_BACKUP_DIR)) {
            wp_mkdir_p(S3AB_BACKUP_DIR);
            
            // 建立 .htaccess 保護
            file_put_contents(
                S3AB_BACKUP_DIR . '.htaccess',
                "deny from all\n"
            );
        }
        
        // 設定預設選項
        if (!get_option('s3ab_settings')) {
            update_option('s3ab_settings', array(
                'schedule_enabled' => false,
                'schedule_frequency' => 'daily',
                'backup_files' => true,
                'backup_database' => true,
                'delete_local' => true,
                'retention_days' => 7,
            ));
        }
        
        // 註冊排程
        if (!wp_next_scheduled('s3ab_scheduled_backup')) {
            wp_schedule_event(time(), 'daily', 's3ab_scheduled_backup');
        }
    }
    
    public function deactivate() {
        // 移除排程
        $timestamp = wp_next_scheduled('s3ab_scheduled_backup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 's3ab_scheduled_backup');
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'S3 備份',
            'S3 備份',
            'manage_options',
            's3-auto-backup',
            array($this, 'render_admin_page'),
            'dashicons-backup',
            80
        );
        
        add_submenu_page(
            's3-auto-backup',
            '備份紀錄',
            '備份紀錄',
            'manage_options',
            's3-auto-backup',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            's3-auto-backup',
            '設定',
            '設定',
            'manage_options',
            's3-auto-backup-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 's3-auto-backup') === false) {
            return;
        }
        
        wp_enqueue_style(
            's3ab-admin',
            S3AB_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            S3AB_VERSION
        );
        
        wp_enqueue_script(
            's3ab-admin',
            S3AB_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            S3AB_VERSION,
            true
        );
        
        wp_localize_script('s3ab-admin', 's3abAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('s3ab_nonce'),
        ));
    }
    
    public function render_admin_page() {
        include S3AB_PLUGIN_DIR . 'admin/admin-page.php';
    }
    
    public function render_settings_page() {
        include S3AB_PLUGIN_DIR . 'admin/settings-page.php';
    }
    
    // AJAX: 開始備份
    public function ajax_start_backup() {
        check_ajax_referer('s3ab_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $backup = new S3AB_Backup();
        $result = $backup->start();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    // AJAX: 列出備份
    public function ajax_list_backups() {
        check_ajax_referer('s3ab_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $s3 = new S3AB_S3_Uploader();
        $backups = $s3->list_backups();
        
        wp_send_json_success($backups);
    }
    
    // AJAX: 還原備份
    public function ajax_restore_backup() {
        check_ajax_referer('s3ab_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $backup_id = sanitize_text_field($_POST['backup_id']);
        
        $restore = new S3AB_Restore();
        $result = $restore->start($backup_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    // AJAX: 刪除備份
    public function ajax_delete_backup() {
        check_ajax_referer('s3ab_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $backup_id = sanitize_text_field($_POST['backup_id']);
        
        $s3 = new S3AB_S3_Uploader();
        $result = $s3->delete_backup($backup_id);
        
        if ($result) {
            wp_send_json_success('備份已刪除');
        } else {
            wp_send_json_error('刪除失敗');
        }
    }
    
    // AJAX: 測試 S3 連線
    public function ajax_test_connection() {
        check_ajax_referer('s3ab_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('權限不足');
        }
        
        $s3 = new S3AB_S3_Uploader();
        $result = $s3->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    // 執行排程備份
    public function run_scheduled_backup() {
        $settings = get_option('s3ab_settings');
        
        if (!$settings['schedule_enabled']) {
            return;
        }
        
        $backup = new S3AB_Backup();
        $backup->start();
    }
}

// 初始化外掛
S3_Auto_Backup::get_instance();
