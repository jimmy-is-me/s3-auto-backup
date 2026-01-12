<?php
if (!defined('ABSPATH')) exit;

$backups = array();
$s3 = new S3AB_S3_Uploader();

try {
    $backups = $s3->list_backups();
} catch (Exception $e) {
    echo '<div class="notice notice-error"><p>無法載入備份清單: ' . esc_html($e->getMessage()) . '</p></div>';
}
?>

<div class="wrap s3ab-admin">
    <h1>S3 自動備份</h1>
    
    <div class="s3ab-actions">
        <button class="button button-primary button-hero" id="s3ab-backup-now">
            <span class="dashicons dashicons-backup"></span>
            立即備份
        </button>
        
        <button class="button button-secondary" id="s3ab-refresh-list">
            <span class="dashicons dashicons-update"></span>
            重新整理
        </button>
    </div>
    
    <div id="s3ab-progress" style="display:none;">
        <div class="s3ab-progress-bar">
            <div class="s3ab-progress-fill"></div>
        </div>
        <div class="s3ab-progress-message"></div>
    </div>
    
    <h2>備份清單</h2>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>備份 ID</th>
                <th>日期時間</th>
                <th>大小</th>
                <th>網站版本</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody id="s3ab-backup-list">
            <?php if (empty($backups)): ?>
                <tr>
                    <td colspan="5">尚無備份記錄</td>
                </tr>
            <?php else: ?>
                <?php foreach ($backups as $backup): ?>
                    <tr data-backup-id="<?php echo esc_attr($backup['id']); ?>">
                        <td><code><?php echo esc_html($backup['id']); ?></code></td>
                        <td><?php echo esc_html($backup['date']); ?></td>
                        <td><?php echo esc_html($backup['size']); ?></td>
                        <td>WP <?php echo esc_html($backup['metadata']['wp_version'] ?? 'N/A'); ?></td>
                        <td>
                            <button class="button s3ab-restore" data-backup-id="<?php echo esc_attr($backup['id']); ?>">
                                還原
                            </button>
                            <button class="button s3ab-delete" data-backup-id="<?php echo esc_attr($backup['id']); ?>">
                                刪除
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.s3ab-admin {
    max-width: 1200px;
}

.s3ab-actions {
    margin: 20px 0;
}

.s3ab-actions .button {
    margin-right: 10px;
}

.s3ab-actions .dashicons {
    line-height: 28px;
}

#s3ab-progress {
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.s3ab-progress-bar {
    height: 30px;
    background: #f0f0f1;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 10px;
}

.s3ab-progress-fill {
    height: 100%;
    background: #2271b1;
    transition: width 0.3s;
    width: 0%;
}

.s3ab-progress-message {
    font-size: 14px;
    color: #50575e;
}
</style>
