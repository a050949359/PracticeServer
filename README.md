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

### Ubuntu 安裝 PHP Extension（PHP 8.5 範例）

```bash
sudo apt-get update
sudo apt-get install -y php8.5-mbstring php8.5-mysql php8.5-redis php8.5-bcmath
```

安裝後可用以下指令確認：

```bash
php -m | grep -Ei "mbstring|pdo_mysql|redis|bcmath"
php -r "echo function_exists('bccomp') ? 'bccomp=ok' : 'bccomp=missing';"
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
- API 文件（l5-swagger / OpenAPI）

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