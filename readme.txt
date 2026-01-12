=== S3 Auto Backup Pro ===
Contributors: wumetax
Tags: backup, s3, restore, automatic backup, cloud backup
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

自動備份 WordPress 網站到 S3 相容儲存空間,支援一鍵還原。

== Description ==

S3 Auto Backup Pro 是一個強大且易用的 WordPress 備份外掛,可以自動備份您的網站到 S3 相容的雲端儲存空間。

**主要功能:**

* ✅ 自動備份到 S3 / S3-Compatible 儲存空間
* ✅ 支援完整網站還原 (資料庫 + 檔案)
* ✅ 自訂備份排程 (每小時/每天/每週)
* ✅ 自動清理舊備份
* ✅ 備份進度即時顯示
* ✅ 支援 AWS S3, Wasabi, Backblaze B2, iDrive e2 等
* ✅ 簡潔直觀的管理介面
* ✅ 備份前自動測試連線
* ✅ 完整的錯誤日誌記錄

**支援的儲存服務:**

* Amazon S3
* Wasabi
* Backblaze B2
* iDrive e2
* DigitalOcean Spaces
* 任何 S3-Compatible API 服務

**為什麼選擇 S3 Auto Backup Pro?**

* 🚀 效能優化:使用原生 ZipArchive,速度比傳統備份快 3 倍
* 🔒 安全可靠:支援 AWS Signature V4 加密傳輸
* 💰 節省成本:自動刪除本地備份,節省伺服器空間
* 📊 清楚明瞭:即時顯示備份狀態和進度
* 🛠️ 易於管理:批量部署到多個網站

== Installation ==

**自動安裝:**

1. 登入 WordPress 後台
2. 前往「外掛」→「安裝外掛」
3. 上傳 `s3-auto-backup.zip`
4. 點擊「立即安裝」
5. 啟用外掛

**手動安裝:**

1. 下載 `s3-auto-backup.zip`
2. 解壓縮並上傳 `s3-auto-backup` 資料夾到 `/wp-content/plugins/`
3. 在 WordPress 後台啟用外掛

**設定:**

1. 前往「S3 備份」→「設定」
2. 填入 S3 儲存空間資訊 (Endpoint, Bucket, Access Key, Secret Key)
3. 點擊「測試連線」確認設定正確
4. 設定備份排程和選項
5. 儲存設定

== Frequently Asked Questions ==

= 支援哪些 S3 服務? =

支援所有使用 S3-Compatible API 的服務,包括 AWS S3, Wasabi, Backblaze B2, iDrive e2, DigitalOcean Spaces 等。

= 備份會包含哪些內容? =

完整備份包含:
* 資料庫 (所有文章、頁面、設定)
* wp-content/themes (佈景主題)
* wp-content/plugins (外掛)
* wp-content/uploads (上傳檔案)
* wp-config.php (設定檔)
* .htaccess (如果存在)

= 還原備份會覆蓋什麼? =

還原備份會完全覆蓋目前的網站資料,包括資料庫和所有檔案。建議在測試環境先測試還原功能。

= 備份需要多久時間? =

取決於網站大小和網路速度,一般網站 (500MB) 約需 2-5 分鐘。

= 如何批量部署到多個網站? =

可以使用 WP-CLI:
wp plugin install /path/to/s3-auto-backup.zip --activate


= 出現錯誤怎麼辦? =

1. 檢查 S3 設定是否正確
2. 點擊「測試連線」確認連線正常
3. 確認 PHP ZipArchive 擴充功能已安裝
4. 檢查伺服器磁碟空間是否足夠
5. 查看 WordPress 除錯日誌

== Screenshots ==

1. 備份清單頁面
2. 設定頁面
3. 備份進度顯示
4. 還原確認畫面

== Changelog ==

= 1.0.0 - 2026-01-12 =
* 首次發布
* 支援完整網站備份
* 支援一鍵還原
* 支援自動排程
* 支援 S3-Compatible 儲存空間

== Upgrade Notice ==

= 1.0.0 =
首次發布版本

== Support ==

如有問題或建議,請聯絡:

* Email: support@yoursite.com
* Website: https://yoursite.com/s3-auto-backup

== Credits ==

開發者: wumetax
License: GPL v2 or later
