# PracticeServer

Laravel 12 專案，包含 Sanctum 驗證、Invitation 流程、角色權限管理與 Swagger 文件。

## 版本資訊

- PHP: ^8.2（建議 8.5）
- Laravel Framework: ^12.0
- PHPUnit: ^11.5
- Vue: ^3.5
- Vite: ^7.0
- Tailwind CSS: ^4.0

## 系統需求

### 必要工具

- PHP 8.2+
- Composer 2+
- Node.js 20+
- npm 10+
- MySQL（若使用 MySQL 環境）
- Redis（若啟用快取/佇列）

### PHP Extension

- mbstring
- pdo_mysql
- redis
- bcmath
- gd

### Ubuntu 安裝 PHP Extension（PHP 8.5 範例）

```bash
sudo apt-get update
sudo apt-get install -y php8.5-mbstring php8.5-mysql php8.5-redis php8.5-bcmath php8.5-gd
```

安裝後可用以下指令確認：

```bash
php -m | grep -Ei "mbstring|pdo_mysql|redis|bcmath|gd"
php -r "echo function_exists('bccomp') ? 'bccomp=ok' : 'bccomp=missing';"
php -r "echo extension_loaded('gd') ? 'gd=ok' : 'gd=missing';"
```

## 完整安裝依賴套件步驟

### 1) 安裝後端套件

```bash
composer install
```

### 2) 建立環境檔

```bash
cp .env.example .env
```

### 3) 產生應用程式金鑰

```bash
php artisan key:generate
```

### 4) 設定資料庫

請先在 `.env` 設定 DB 連線（例如 `DB_CONNECTION`、`DB_HOST`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`）。

### 5) 執行 Migration

```bash
php artisan migrate
```

### 6) 安裝前端套件

```bash
npm install
```

### 7) 建置前端資產（正式或一次性）

```bash
npm run build
```

## Vertex AI 憑證設定

若要使用 Vertex AI 對話功能，需先建立 Google Cloud Service Account 金鑰檔，並放到 `storage/app/gcp-sa.json`。

### 1) 在 Google Cloud 建立 Service Account

- 進入 Google Cloud Console。
- 選擇對應專案。
- 前往 IAM 與管理 -> Service Accounts。
- 建立一個新的 Service Account。
- 依需求授予可呼叫 Vertex AI 的權限。

### 2) 產生 JSON 金鑰

- 進入剛建立的 Service Account。
- 在 Keys 頁籤新增金鑰。
- 選擇 JSON 格式下載。

### 3) 放到專案目錄

將下載的 JSON 檔案放到以下位置：

```bash
storage/app/gcp-sa.json
```

### 4) 設定 `.env`

請設定以下環境變數：

```dotenv
GOOGLE_APPLICATION_CREDENTIALS=/absolute/path/to/PracticeServer/storage/app/gcp-sa.json
VERTEX_AI_PROJECT_ID=your-gcp-project-id
VERTEX_AI_LOCATION=us-central1
VERTEX_AI_MODEL=gemini-2.0-flash-001
```

說明：

- `GOOGLE_APPLICATION_CREDENTIALS` 建議填絕對路徑，避免執行環境不同時找不到檔案。
- `VERTEX_AI_PROJECT_ID` 必須與 Service Account 所屬或可存取的 GCP 專案一致。
- `VERTEX_AI_LOCATION` 與 `VERTEX_AI_MODEL` 可依實際使用的 Vertex AI 區域與模型調整。

### 5) 安全注意事項

- 不要把 `storage/app/gcp-sa.json` 提交到 Git。
- 若金鑰疑似外流，請立即在 Google Cloud Console 廢止並重新產生。

## 執行方式

### 本機開發（建議）

```bash
composer run dev
```

此指令會同時啟動：

- Laravel 開發伺服器
- Queue Listener
- Log Viewer（Pail）
- Vite Dev Server

### 僅啟動 API 服務

```bash
php artisan serve
```

### 僅啟動前端開發伺服器

```bash
npm run dev
```

## 測試

```bash
php artisan test --compact
```

## 主要功能

- 使用者註冊/登入/登出
- 邀請註冊流程（Invitation）
- 使用 spatie/laravel-permission 的角色與權限管理
- Vertex AI 對話、影像分析、OCR（文字偵測與物件座標）與辨識歷史
- API 文件（l5-swagger / OpenAPI）
- 詳細技術變更與 API 範例請見 CHANGELOG_2026-03-25.md

## Docker 狀態

- 目前已提供簡易可啟動版本的 Dockerfile 與 docker-compose.yml。
- 目前屬於開發用基礎配置，尚未完成正式環境最佳化。
- 待完成項目：Secrets 管理、Nginx + PHP-FPM 架構、部署流程與安全強化。


## 重置專案 git 環境

```bash
git fetch origin
git checkout main
git reset --hard origin/main
git clean -fd
```