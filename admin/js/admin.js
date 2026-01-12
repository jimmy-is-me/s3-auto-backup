jQuery(document).ready(function($) {
    
    // 立即備份
    $('#s3ab-backup-now').on('click', function() {
        if (!confirm('確定要開始備份嗎?')) {
            return;
        }
        
        const $btn = $(this);
        const $progress = $('#s3ab-progress');
        const $progressFill = $('.s3ab-progress-fill');
        const $progressMsg = $('.s3ab-progress-message');
        
        $btn.prop('disabled', true);
        $progress.show();
        $progressFill.css('width', '10%');
        $progressMsg.text('準備開始備份...');
        
        $.ajax({
            url: s3abAjax.ajax_url,
            type: 'POST',
            data: {
                action: 's3ab_start_backup',
                nonce: s3abAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $progressFill.css('width', '100%');
                    $progressMsg.html('<strong>✅ 備份完成!</strong><br>' + 
                        '備份 ID: ' + response.data.backup_id);
                    
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    alert('備份失敗: ' + response.data);
                    $progress.hide();
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('發生錯誤,請稍後再試');
                $progress.hide();
                $btn.prop('disabled', false);
            },
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                // 模擬進度 (實際應該用 WebSocket 或輪詢)
                let progress = 10;
                const interval = setInterval(function() {
                    if (progress < 90) {
                        progress += 5;
                        $progressFill.css('width', progress + '%');
                        $progressMsg.text('備份中... ' + progress + '%');
                    }
                }, 500);
                
                xhr.addEventListener('loadend', function() {
                    clearInterval(interval);
                });
                
                return xhr;
            }
        });
    });
    
    // 還原備份
    $(document).on('click', '.s3ab-restore', function() {
        const backupId = $(this).data('backup-id');
        
        if (!confirm('⚠️ 警告:還原備份將會覆蓋現有資料!\n\n確定要還原備份 ' + backupId + ' 嗎?')) {
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).text('還原中...');
        
        $.ajax({
            url: s3abAjax.ajax_url,
            type: 'POST',
            data: {
                action: 's3ab_restore_backup',
                backup_id: backupId,
                nonce: s3abAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ 還原完成!即將重新載入頁面...');
                    location.reload();
                } else {
                    alert('還原失敗: ' + response.data);
                    $btn.prop('disabled', false).text('還原');
                }
            },
            error: function() {
                alert('發生錯誤,請稍後再試');
                $btn.prop('disabled', false).text('還原');
            }
        });
    });
    
    // 刪除備份
    $(document).on('click', '.s3ab-delete', function() {
        const backupId = $(this).data('backup-id');
        
        if (!confirm('確定要刪除備份 ' + backupId + ' 嗎?')) {
            return;
        }
        
        const $row = $(this).closest('tr');
        
        $.ajax({
            url: s3abAjax.ajax_url,
            type: 'POST',
            data: {
                action: 's3ab_delete_backup',
                backup_id: backupId,
                nonce: s3abAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    alert('刪除失敗: ' + response.data);
                }
            }
        });
    });
    
    // 重新整理清單
    $('#s3ab-refresh-list').on('click', function() {
        location.reload();
    });
});
