# 2026-03-27 變更詳情

## 1) Google OAuth 命名重整

- OAuth service 與 controller 由 Drive 專用命名，調整為較通用的 Google OAuth 命名。
- 新增：
  - app/Services/Google/Oauth/GoogleOAuthService.php
  - app/Http/Controllers/Google/Oauth/GoogleOAuthController.php
- 移除舊命名檔案：
  - app/Services/Google/Drive/GoogleDriveOAuthService.php
  - app/Http/Controllers/Google/Drive/DriveOAuthController.php

## 2) OAuth 路由與 API 調整

- 新 OAuth API（staff + sanctum）：
  - GET /api/google/oauth/authorize-url
  - GET /api/google/oauth/status
  - DELETE /api/google/oauth/disconnect
- 保留舊 API 路由別名（/api/google/drive/oauth/*）做相容。
- Web callback 新增：
  - /auth/google/oauth/callback
- 舊 callback 保留相容：
  - /auth/google/drive/callback

## 3) RabbitMQ Queue Driver 接入

- 安裝套件：
  - vladimir-yuldashev/laravel-queue-rabbitmq
- 擴充 queue 連線設定：
  - config/queue.php 新增 rabbitmq 連線與 management 設定
- 新增環境變數：
  - RABBITMQ_* 基本連線
  - RABBITMQ_MANAGEMENT_* 管理 API 連線

## 4) CSV 匯出（Queue 練習功能）

- 新增資料表與模型：
  - csv_export_tasks
  - app/Models/CsvExportTask.php
- 任務欄位包含：
  - status、columns、total_rows、generated_rows、interval_seconds、queue_name、last_error 等
- 新增假資料 service：
  - app/Services/Export/CsvExportFakeDataService.php
- 新增 job：
  - app/Jobs/GenerateCsvExportRowJob.php
  - 每次處理 1 行，並延遲 5 秒重排下一顆 job 直到完成

## 5) CSV 匯出 API（staff only）

- 新增：
  - GET /api/admin/csv-exports
  - POST /api/admin/csv-exports
  - GET /api/admin/csv-exports/{id}
  - GET /api/admin/csv-exports/{id}/download
- 建立任務時：
  - 先寫入 CSV header
  - 檔名格式為 yyyymmdd_HHMMSS.csv
  - dispatch 第一顆 queue job

## 6) Queue 狀態 API 與前端進度條

- 新增 API：
  - GET /api/admin/queue/stats
- 回傳指標：
  - messages_ready
  - messages_unacknowledged
  - messages_total
  - consumers
  - drain_progress_percentage
- Admin CSV 匯出頁新增：
  - Queue 狀態文字資訊
  - Queue 進度條（每 5 秒輪詢更新）

## 7) 前端頁面調整

- 新增 Admin CSV 匯出頁：
  - resources/js/apps/admin/pages/AdminCsvExportPage.vue
- 新增導航入口：
  - Admin Navbar Queue -> CSV 匯出
  - Admin 首頁快捷按鈕 CSV 匯出

## 8) 測試與驗證

- 主要測試檔：
  - tests/Feature/CsvExportApiTest.php
  - tests/Unit/GenerateCsvExportRowJobTest.php
  - tests/Feature/GoogleDriveUploadApiTest.php（OAuth API 路徑同步調整）
- 驗證命令：
  - vendor/bin/pint --dirty --format agent
  - php artisan test --compact tests/Feature/CsvExportApiTest.php tests/Unit/GenerateCsvExportRowJobTest.php
  - php artisan test --compact tests/Feature/GoogleDriveUploadApiTest.php
  - npm run build
