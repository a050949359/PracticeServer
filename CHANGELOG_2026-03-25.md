# 2026-03-25 變更詳情

## 1) 前端架構調整

- 前端入口改為 3 個獨立 entry：
  - resources/js/public.js
  - resources/js/admin.js
  - resources/js/register.js
- Public 區採用 vue-router，路由包含：
  - /
  - /google/vertex/chat
  - /google/vertex/image
  - /google/vertex/image/detect
- Navbar 新增 Vertex AI 下拉選單並可切換上述頁面。

## 2) Vertex Image / OCR API

- 新增影像 API：POST /api/google/vertex/image
- 新增 OCR API：POST /api/google/vertex/image/detect
- 新增 OCR 歷史 API：GET /api/google/vertex/image/detect/history

## 3) OCR 輸出規則

- 若 request types 包含文字偵測：
  - DOCUMENT_TEXT_DETECTION 或 TEXT_DETECTION
  - 回傳 data.text（文字內容）
- 若 request types 包含物件偵測：
  - OBJECT_LOCALIZATION
  - 回傳 data.objects（座標與信心分數）
- 可同時送多種 types，回傳可同時包含 text 與 objects。

## 4) OCR 持久化

每次辨識成功會：

- 儲存上傳圖檔到 public disk 的 vertex-ocr-images/
- 寫入 vertex_ocr_results 資料表：
  - image_name
  - image_path
  - mime_type
  - image_size
  - types
  - text
  - provider
  - raw_response

API 會回傳 record 區塊，包含 image_name、image_path、image_url，可用來對應圖檔與結果。

## 5) 主要檔案

### 後端

- app/Services/Google/Vertex/VertexImageService.php
- app/Services/Google/Vertex/VertexDetectService.php
- app/Http/Controllers/Google/Vertex/VertexImageController.php
- app/Http/Controllers/Google/Vertex/VertexDetectController.php
- app/Http/Requests/Google/Vertex/VertexImageRequest.php
- app/Http/Requests/Google/Vertex/VertexDetectRequest.php
- app/Models/VertexOcrResult.php
- database/migrations/2026_03_25_030355_create_vertex_ocr_results_table.php

### 前端

- resources/js/components/Google/VertexAI/VertexChatPanel.vue
- resources/js/components/Google/VertexAI/VertexImagePanel.vue
- resources/js/components/Google/VertexAI/VertexDetectPanel.vue
- resources/js/apps/public/router.js
- resources/js/apps/public/navbarConfig.js
- resources/js/components/AppNavbar.vue

### 測試

- tests/Feature/VertexImageApiTest.php
- tests/Feature/VertexDetectApiTest.php

## 6) 範例請求

### 只做文字偵測

```http
POST /api/google/vertex/image/detect
Content-Type: multipart/form-data

image=<file>
types[]=DOCUMENT_TEXT_DETECTION
```

### 只做物件偵測

```http
POST /api/google/vertex/image/detect
Content-Type: multipart/form-data

image=<file>
types[]=OBJECT_LOCALIZATION
```

### 同時做文字 + 物件

```http
POST /api/google/vertex/image/detect
Content-Type: multipart/form-data

image=<file>
types[]=DOCUMENT_TEXT_DETECTION
types[]=OBJECT_LOCALIZATION
```

## 7) 驗證指令

```bash
php artisan migrate
php artisan test --compact tests/Feature/VertexDetectApiTest.php
npm run build
```

## 8) 補充：GD Extension

- 若需處理影像相關功能或測試，請確認 PHP 已安裝 GD extension。
- Ubuntu (PHP 8.5) 可使用：

```bash
sudo apt-get update
sudo apt-get install -y php8.5-gd
php -r "echo extension_loaded('gd') ? 'gd=ok' : 'gd=missing';"
```
