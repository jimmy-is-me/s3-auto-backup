<?php
if (!defined('ABSPATH')) exit;

// 儲存設定
if (isset($_POST['s3ab_save_settings'])) {
    check_admin_referer('s3ab_settings');
    
    // S3 設定
    update_option('s3ab_s3_settings', array(
        'endpoint' => sanitize_text_field($_POST['s3_endpoint']),
        'bucket' => sanitize_text_field($_POST['s3_bucket']),
        'access_key' => sanitize_text_field($_POST['s3_access_key']),
        'secret_key' => sanitize_text_field($_POST['s3_secret_key']),
        'region' => sanitize_text_field($_POST['s3_region']),
    ));
    
    // 備份設定
    update_option('s3ab_settings', array(
        'schedule_enabled' => isset($_POST['schedule_enabled']),
        'schedule_frequency' => sanitize_text_field($_POST['schedule_frequency']),
        'backup_files' => isset($_POST['backup_files']),
        'backup_database' => isset($_POST['backup_database']),
        'delete_local' => isset($_POST['delete_local']),
        'retention_days' => intval($_POST['retention_days']),
    ));
    
    echo '<div class="notice notice-success"><p>設定已儲存</p></div>';
}

$s3_settings = get_option('s3ab_s3_settings', array());
$settings = get_option('s3ab_settings', array());
?>

<div class="wrap">
    <h1>S3 備份設定</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('s3ab_settings'); ?>
        
        <h2>S3 儲存設定</h2>
        <table class="form-table">
            <tr>
                <th>S3 Endpoint</th>
                <td>
                    <input type="text" name="s3_endpoint" value="<?php echo esc_attr($s3_settings['endpoint'] ?? ''); ?>" class="regular-text" placeholder="https://s3.ap-southeast-1.amazonaws.com">
                    <p class="description">留空則使用 AWS S3,或填入 S3-Compatible 服務的 endpoint</p>
                </td>
            </tr>
            <tr>
                <th>Bucket 名稱 *</th>
                <td>
                    <input type="text" name="s3_bucket" value="<?php echo esc_attr($s3_settings['bucket'] ?? ''); ?>" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th>Access Key *</th>
                <td>
                    <input type="text" name="s3_access_key" value="<?php echo esc_attr($s3_settings['access_key'] ?? ''); ?>" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th>Secret Key *</th>
                <td>
                    <input type="password" name="s3_secret_key" value="<?php echo esc_attr($s3_settings['secret_key'] ?? ''); ?>" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th>Region</th>
                <td>
                    <input type="text" name="s3_region" value="<?php echo esc_attr($s3_settings['region'] ?? 'us-east-1'); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        
        <p>
            <button type="button" class="button" id="s3ab-test-connection">測試連線</button>
            <span id="s3ab-test-result"></span>
        </p>
        
        <h2>備份設定</h2>
        <table class="form-table">
            <tr>
                <th>啟用自動備份</th>
                <td>
                    <label>
                        <input type="checkbox" name="schedule_enabled" value="1" <?php checked($settings['schedule_enabled'] ?? false); ?>>
                        啟用排程自動備份
                    </label>
                </td>
            </tr>
            <tr>
                <th>備份頻率</th>
                <td>
                    <select name="schedule_frequency">
                        <option value="daily" <?php selected($settings['schedule_frequency'] ?? 'daily', 'daily'); ?>>每天</option>
                        <option value="twicedaily" <?php selected($settings['schedule_frequency'] ?? 'daily', 'twicedaily'); ?>>每 12 小時</option>
                        <option value="hourly" <?php selected($settings['schedule_frequency'] ?? 'daily', 'hourly'); ?>>每小時</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>備份內容</th>
                <td>
                    <label>
                        <input type="checkbox" name="backup_files" value="1" <?php checked($settings['backup_files'] ?? true); ?>>
                        備份檔案 (themes, plugins, uploads)
                    </label><br>
                    <label>
                        <input type="checkbox" name="backup_database" value="1" <?php checked($settings['backup_database'] ?? true); ?>>
                        備份資料庫
                    </label>
                </td>
            </tr>
            <tr>
                <th>備份後刪除本地檔案</th>
                <td>
                    <label>
                        <input type="checkbox" name="delete_local" value="1" <?php checked($settings['delete_local'] ?? true); ?>>
                        上傳到 S3 後刪除本地備份 (節省空間)
                    </label>
                </td>
            </tr>
            <tr>
                <th>保留天數</th>
                <td>
                    <input type="number" name="retention_days" value="<?php echo esc_attr($settings['retention_days'] ?? 7); ?>" min="1" max="365">
                    <p class="description">自動刪除超過指定天數的舊備份</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="s3ab_save_settings" class="button button-primary" value="儲存設定">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#s3ab-test-connection').on('click', function() {
        const $btn = $(this);
        const $result = $('#s3ab-test-result');
        
        $btn.prop('disabled', true).text('測試中...');
        $result.html('<span class="spinner is-active" style="float:none;"></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 's3ab_test_connection',
                nonce: '<?php echo wp_create_nonce('s3ab_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">✅ ' + response.data + '</span>');
                } else {
                    $result.html('<span style="color:red;">❌ ' + response.data + '</span>');
                }
                $btn.prop('disabled', false).text('測試連線');
            }
        });
    });
});
</script>
