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
    
    // 載入日誌
    function loadLogs() {
        $.ajax({
            url: s3abAjax.ajax_url,
            type: 'POST',
            data: {
                action: 's3ab_get_logs',
                lines: 100,
                nonce: s3abAjax.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    let html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>時間</th><th>類型</th><th>訊息</th></tr></thead><tbody>';
                    response.data.forEach(function(log) {
                        const typeClass = log.type.toLowerCase();
                        html += '<tr class="log-' + typeClass + '">';
                        html += '<td>' + log.time + '</td>';
                        html += '<td><span class="log-type-' + typeClass + '">' + log.type + '</span></td>';
                        html += '<td>' + log.message + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                    $('#s3ab-logs-content').html(html);
                } else {
                    $('#s3ab-logs-content').html('<p class="description">尚無日誌記錄</p>');
                }
            }
        });
    }
    
    // 重新整理日誌
    $('#s3ab-refresh-logs').on('click', function() {
        loadLogs();
    });
    
    // 初始載入日誌
    loadLogs();
    
    // 上傳備份檔案還原
    $('#s3ab-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('⚠️ 警告: 還原備份將會覆蓋現有資料!\n\n確定要上傳並還原備份檔案嗎?')) {
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', 's3ab_upload_restore');
        formData.append('nonce', s3abAjax.nonce);
        
        const $progress = $('#s3ab-upload-progress');
        const $progressFill = $progress.find('.s3ab-progress-fill');
        const $progressMsg = $progress.find('.s3ab-progress-message');
        
        $progress.show();
        $progressFill.css('width', '10%');
        $progressMsg.html('<strong>上傳檔案中...</strong>');
        
        $.ajax({
            url: s3abAjax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        $progressFill.css('width', percentComplete + '%');
                        $progressMsg.html('<strong>上傳中... ' + Math.round(percentComplete) + '%</strong>');
                    }
                });
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $progressFill.css('width', '100%');
                    $progressMsg.html('<strong style="color:green;">✅ 還原完成!</strong>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $progressMsg.html('<strong style="color:red;">❌ 還原失敗: ' + response.data + '</strong>');
                }
            },
            error: function() {
                $progressMsg.html('<strong style="color:red;">❌ 發生錯誤，請稍後再試</strong>');
            }
        });
    });
});
