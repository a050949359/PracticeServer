# PracticeServer

Laravel 12 專案，包含 Sanctum 驗證、Invitation 流程、角色權限管理與 Swagger 文件。

## 主要功能

- 使用者註冊/登入/登出
- 邀請註冊流程（Invitation）
- 使用 spatie/laravel-permission 的角色與權限管理
- Google OAuth（使用者授權）
- Google Drive（staff）檔案上傳、列表、下載、刪除
- Vertex AI 對話、影像分析、OCR（文字偵測與物件座標）與辨識歷史
- RabbitMQ queue driver 與背景工作處理
- CSV 匯出任務（staff）：
  - 建立匯出任務 API
  - 檔名格式 yyyymmdd_HHMMSS.csv
  - 每 5 秒寫入 1 行假資料（queue job 鏈式執行）
  - 任務列表、單筆狀態、下載 API
  - 後端同步 Firestore（REST API）
  - 後端同步 InfluxDB（時序資料）
  - 前端 Firebase 即時監聽（失敗自動回退 polling）
- Queue 狀態監控（staff）：
  - API：/api/admin/queue/stats
  - 指標：ready / unacked / total / consumers
  - Admin CSV 匯出頁 queue 進度條
- API 文件（l5-swagger / OpenAPI）
- 詳細技術變更與 API 範例請見 CHANGELOG_2026-03-25.md、CHANGELOG_2026-03-27.md

## 版本資訊

- PHP: ^8.2（建議 8.3）
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
- RabbitMQ（若使用 AMQP 佇列）

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

### 2) 以 Linux 指令產生 JSON 金鑰（建議）

使用目前 gcloud 身分具有建立 Service Account Key 的 IAM 權限（例如 `iam.serviceAccountKeys.create`），可直接在專案根目錄執行：

```bash
mkdir -p storage/app
gcloud iam service-accounts keys create storage/app/gcp-sa.json \
  --iam-account=your-service-account@your-project.iam.gserviceaccount.com \
  --project=your-project-id
chmod 600 storage/app/gcp-sa.json
```

### 3) 若使用 Console 下載 JSON

若你是從 Google Cloud Console 下載 JSON 檔，請放到以下位置：

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

## RabbitMQ Queue 設定

專案已內建 RabbitMQ queue driver，可透過 `.env` 切換為 RabbitMQ：

```dotenv
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_QUEUE=default
```

若使用專案提供的 Docker Compose，可直接啟動 RabbitMQ：

```bash
docker compose up -d rabbitmq
```

RabbitMQ 管理介面預設為：

```text
http://localhost:15672
```

本機開發可繼續用 Laravel 的 worker：

```bash
php artisan queue:work rabbitmq --queue=default
```

若 `.env` 已設定 `QUEUE_CONNECTION=rabbitmq`，也可使用：

```bash
php artisan queue:work --queue=default
```

注意：指令是 `queue:work`，不是 `queue:worker`。

若要使用 RabbitMQ 套件提供的 consumer：

```bash
php artisan rabbitmq:consume --queue=default
```

## Firestore 同步與前端即時監聽

CSV 匯出任務會由後端同步到 Firestore（REST API），前端 Admin CSV 匯出頁可用 Firebase Web SDK 即時監聽任務進度。

### 指標來源分工

- Firestore：僅同步 task 進度資料（例如 `status`、`generated_rows`、`progress_percentage`）。
- Queue 數量與消費者指標：持續使用 RabbitMQ Management API（`/api/admin/queue/stats`）即時取得，不從 Firestore 讀取。

### 已知相容性問題（PHP 版本）

- 目前建議使用 PHP 8.3。
- 在 PHP 8.4/8.5 環境，Firestore 相關套件使用到的底層 library 可能觸發以下錯誤：

```text
Maximum call stack size of 8339456 bytes (zend.max_allowed_stack_size - zend.reserved_stack_size) reached. Infinite recursion
```

- 若遇到上述錯誤，請先降版到 PHP 8.3 再重試。

### 後端 Firestore（REST）

`.env` 需設定：

```dotenv
FIRESTORE_PROJECT_ID=ohya-project-02
FIRESTORE_CREDENTIALS=/absolute/path/to/storage/app/firebase-sa.json
FIRESTORE_DATABASE=(default)
FIRESTORE_TASK_COLLECTION=csv_export_tasks
FIRESTORE_SYNC_ENABLED=true
```

說明：

- `FIRESTORE_CREDENTIALS` 使用 Service Account JSON（後端用途）。
- 後端會將文件寫入 `csv_export_tasks/{task_id}`。

### 後端 InfluxDB（時序資料）

`.env` 需設定：

```dotenv
INFLUXDB_URL=http://127.0.0.1:8086
INFLUXDB_TOKEN=your-influxdb-token
INFLUXDB_ORG=your-org
INFLUXDB_BUCKET=csv_export_metrics
INFLUXDB_MEASUREMENT=csv_export_task_progress
INFLUXDB_SYNC_ENABLED=true
```

說明：

- `INFLUXDB_SYNC_ENABLED=true` 後，CSV 匯出任務進度會寫入 InfluxDB。
- 預設 measurement 為 `csv_export_task_progress`。
- 每個時間點會寫入 `total_rows`、`generated_rows`、`progress_percentage`、`interval_seconds` 等欄位。

### 前端 Firebase Realtime Listener

Vue 端僅能透過 `VITE_` 前綴讀取環境變數（`import.meta.env`）。

`.env` 需設定：

```dotenv
VITE_FIREBASE_API_KEY=
VITE_FIREBASE_AUTH_DOMAIN=
VITE_FIREBASE_PROJECT_ID=
VITE_FIREBASE_STORAGE_BUCKET=
VITE_FIREBASE_MESSAGING_SENDER_ID=
VITE_FIREBASE_APP_ID=
VITE_FIREBASE_DATABASE_ID=(default)
VITE_FIRESTORE_TASK_COLLECTION=csv_export_tasks
```

設定更新後請重啟前端開發伺服器：

```bash
npm run dev
```

### 權限錯誤排查

若頁面出現 `Missing or insufficient permissions.`，通常是 Firestore Security Rules 拒絕前端讀取，不是文件不存在。

- 文件不存在：`onSnapshot` 通常收到 `exists() === false`。
- 權限不足：會拋出 `PERMISSION_DENIED` / `Missing or insufficient permissions.`。

目前頁面只會對 `pending`、`processing` 任務掛載 listener，並在即時監聽失敗時自動回退到 polling。

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