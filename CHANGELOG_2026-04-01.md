# 2026-04-01 變更詳情

## 1) Admin Channel / CSV 匯出頁優化

- 調整 Channel 管理頁「假資料限定值」placeholder，補上輸入規則與範例（一次輸入一個值後按 Enter）。
- 修正選單相關 tooltip 行為，避免操作時出現不必要提示。
- Admin CSV 匯出頁維持既有流程，並針對 Influx 匯入排查時的欄位與訊息呈現做整理。

## 2) Admin 路由登入檢查行為調整

- `/admin` 首頁保持可匿名瀏覽。
- `/admin/*` 其他管理路由改為進入前檢查登入狀態。
- 導航改為「點擊前」先判斷是否需要登入，未登入時先開啟登入流程，不再先切頁再擋。

主要檔案：

- resources/js/admin.js
- resources/js/apps/admin/router.js
- resources/js/apps/admin/AdminApp.vue

## 3) CSV 匯入 Influx 可觀測性與錯誤訊息強化

- 匯入服務新增結構化報告：
  - `tasks_selected` / `tasks_processed` / `tasks_imported` / `tasks_skipped`
  - `imported_rows`
  - `skip_reasons`
  - `error_samples`
- 指令輸出會列出 skip reasons 與 task 範例錯誤，便於快速定位。
- 針對連線逾時、HTTP 失敗回應、資料不可用等情境分開歸類，並在必要時以 failure code 結束指令。

主要檔案：

- app/Services/CsvExport/CsvExportTaskInfluxSyncService.php
- app/Console/Commands/ImportCsvExportsToInfluxCommand.php
- tests/Unit/CsvExportTaskInfluxSyncServiceTest.php

## 4) Influx 寫入改為 InfluxDB 3 原生 API

- 匯入寫入端點從 v2 相容 `/api/v2/write` 切換到 v3 `/api/v3/write_lp`。
- 驗證方式改為 `Authorization: Bearer <token>`。
- 寫入參數改為 v3 模型：
  - `db=<INFLUXDB_DATABASE>`
  - `precision=second`
  - `accept_partial=false`
- 保留既有 CSV -> line protocol 的轉換邏輯與 row 遞增匯入策略。

## 5) 設定與文件同步

- Influx 設定改以 `INFLUXDB_DATABASE` 為主。
- 移除已不再使用的全域 `INFLUXDB_MEASUREMENT` 設定依賴。
- `.env.example` 更新為 InfluxDB 3 設定範本（避免放入真實 token）。
- README 更新：
  - InfluxDB 3 `write_lp` 模式說明
  - CLI 查詢與排查重點
  - 前端 `import.meta.env` 僅可讀取 `VITE_` 前綴變數

主要檔案：

- config/services.php
- .env.example
- README.md

## 6) 驗證與格式化

- 已執行：
  - `php artisan test --compact tests/Unit/CsvExportTaskInfluxSyncServiceTest.php`
  - `vendor/bin/pint --dirty --format agent`
- 結果：測試通過，格式化通過。
