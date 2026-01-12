<?php

if (!defined('ABSPATH')) {
    exit;
}

class S3AB_Scheduler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 註冊自訂排程間隔
        add_filter('cron_schedules', array($this, 'add_custom_schedules'));
        
        // 監聽設定變更
        add_action('update_option_s3ab_settings', array($this, 'update_schedule'), 10, 2);
    }
    
    /**
     * 新增自訂排程間隔
     */
    public function add_custom_schedules($schedules) {
        // 每 4 小時
        $schedules['every_4_hours'] = array(
            'interval' => 4 * HOUR_IN_SECONDS,
            'display' => __('每 4 小時', 's3-auto-backup')
        );
        
        // 每 12 小時
        $schedules['every_12_hours'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __('每 12 小時', 's3-auto-backup')
        );
        
        // 每週
        $schedules['weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display' => __('每週', 's3-auto-backup')
        );
        
        return $schedules;
    }
    
    /**
     * 當設定更新時,重新排程
     */
    public function update_schedule($old_value, $new_value) {
        $this->clear_schedule();
        
        if (!empty($new_value['schedule_enabled'])) {
            $this->schedule_backup($new_value['schedule_frequency']);
        }
    }
    
    /**
     * 設定排程
     */
    public function schedule_backup($frequency = 'daily') {
        // 先清除舊排程
        $this->clear_schedule();
        
        // 設定新排程
        if (!wp_next_scheduled('s3ab_scheduled_backup')) {
            wp_schedule_event(time(), $frequency, 's3ab_scheduled_backup');
            
            error_log('[S3 Auto Backup] 已設定排程: ' . $frequency);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * 清除排程
     */
    public function clear_schedule() {
        $timestamp = wp_next_scheduled('s3ab_scheduled_backup');
        
        if ($timestamp) {
            wp_unschedule_event($timestamp, 's3ab_scheduled_backup');
            error_log('[S3 Auto Backup] 已清除排程');
        }
    }
    
    /**
     * 取得下次執行時間
     */
    public function get_next_run() {
        $timestamp = wp_next_scheduled('s3ab_scheduled_backup');
        
        if ($timestamp) {
            return array(
                'timestamp' => $timestamp,
                'datetime' => get_date_from_gmt(date('Y-m-d H:i:s', $timestamp), 'Y-m-d H:i:s'),
                'human' => human_time_diff($timestamp, current_time('timestamp')),
            );
        }
        
        return null;
    }
    
    /**
     * 手動執行排程 (for testing)
     */
    public function run_now() {
        do_action('s3ab_scheduled_backup');
    }
}

// 初始化排程器
S3AB_Scheduler::get_instance();
