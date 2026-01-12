# S3 Auto Backup Pro - WordPress 外掛

一個功能完整的 WordPress 備份外掛，可以自動將您的網站備份到 S3 相容的雲端儲存服務。

## ✨ 主要功能

- ✅ **可以上傳安裝** (不需要 SSH)
- ✅ **自動備份到 S3** (支援 AWS S3、DigitalOcean Spaces、Wasabi 等)
- ✅ **支援一鍵還原網站**
- ✅ **完整的管理介面**
- ✅ **排程自動備份**
- ✅ **資料庫和檔案完整備份**

## 📋 系統需求

- WordPress 5.0 或更高版本
- PHP 7.4 或更高版本
- ZipArchive 擴充功能
- S3 相容的雲端儲存服務帳號

## 🚀 安裝方式

### 方法 1: 透過 WordPress 管理介面上傳

1. 下載此專案的 ZIP 檔案
2. 在 WordPress 管理後台，前往「外掛 > 安裝外掛 > 上傳外掛」
3. 選擇 ZIP 檔案並上傳
4. 啟用外掛

### 方法 2: 透過 Git 安裝

```bash
cd wp-content/plugins
git clone https://github.com/jimmy-is-me/s3-auto-backup.git
```

## ⚙️ 設定步驟

1. 前往「S3 備份 > 設定」頁面
2. 填入您的 S3 連線資訊：
   - **S3 Endpoint** (可選，留空則使用 AWS S3)
   - **Bucket 名稱** (必填)
   - **Access Key** (必填)
   - **Secret Key** (必填)
   - **Region** (AWS S3 區域)
3. 點擊「測試連線」確認設定正確
4. 設定備份選項（備份頻率、保留天數等）
5. 儲存設定

## 📦 備份內容

外掛會備份以下內容：

- **資料庫**: 完整的 WordPress 資料庫 SQL 備份（自動壓縮）
- **主題檔案**: 所有已安裝的主題
- **外掛檔案**: 所有已安裝的外掛
- **媒體庫**: 所有上傳的媒體檔案
- **設定檔案**: wp-config.php 和 .htaccess

## 🔄 使用方式

### 立即備份

1. 前往「S3 備份 > 備份紀錄」頁面
2. 點擊「立即備份」按鈕
3. 等待備份完成（進度條會顯示進度）

### 還原備份

1. 在備份清單中找到要還原的備份
2. 點擊「還原」按鈕
3. 確認還原操作（⚠️ 警告：這會覆蓋現有資料）
4. 等待還原完成

### 自動備份

1. 前往「S3 備份 > 設定」頁面
2. 勾選「啟用自動備份」
3. 選擇備份頻率（每小時、每 12 小時、每天）
4. 儲存設定

## 🔒 安全特性

- 備份目錄自動保護（.htaccess）
- 使用 AWS Signature V4 進行安全認證
- 權限檢查（僅管理員可操作）
- 備份檔案自動壓縮

## 🌐 支援的 S3 服務

- **AWS S3**
- **DigitalOcean Spaces**
- **Wasabi**
- **Backblaze B2**
- **iDrive e2**
- **其他 S3-Compatible 服務**

## 📝 注意事項

1. **還原操作不可逆**: 還原備份會覆蓋現有資料，請謹慎操作
2. **大型網站備份**: 大型網站（> 5GB）可能需要較長時間
3. **PHP 限制**: 確保 PHP 的 `max_execution_time` 和 `memory_limit` 設定足夠
4. **wp-config.php**: 還原時會保留當前的資料庫設定，避免覆蓋

## 🐛 疑難排解

### 備份失敗

- 檢查 S3 連線設定是否正確
- 確認 Bucket 權限設定
- 檢查 PHP 錯誤日誌

### 還原失敗

- 確認備份檔案完整
- 檢查資料庫連線
- 確認檔案權限

### ZipArchive 錯誤

- 聯繫主機商啟用 ZipArchive 擴充功能
- 或使用 `php -m | grep zip` 檢查是否已安裝

## 📄 授權

GPL v2 或更高版本

## 🤝 貢獻

歡迎提交 Issue 和 Pull Request！

## 📧 支援

如有問題，請在 [GitHub Issues](https://github.com/jimmy-is-me/s3-auto-backup/issues) 上提交問題。
