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
    
    <h2>備份日誌</h2>
    <div id="s3ab-logs" class="s3ab-logs-container">
        <div class="s3ab-logs-content" id="s3ab-logs-content">
            <p class="description">備份日誌將顯示在這裡</p>
        </div>
        <button class="button" id="s3ab-refresh-logs">重新整理日誌</button>
    </div>
    
    <h2>上傳備份檔案還原</h2>
    <div class="s3ab-upload-restore">
        <form id="s3ab-upload-form" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th><label for="s3ab-backup-file">選擇備份檔案</label></th>
                    <td>
                        <input type="file" name="backup_file" id="s3ab-backup-file" accept=".zip" required>
                        <p class="description">請上傳完整的備份 ZIP 檔案（包含 database.sql.gz 和 files.zip）</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">上傳並還原</button>
            </p>
        </form>
        <div id="s3ab-upload-progress" style="display:none;">
            <div class="s3ab-progress-bar">
                <div class="s3ab-progress-fill"></div>
            </div>
            <div class="s3ab-progress-message"></div>
        </div>
    </div>
</div>
