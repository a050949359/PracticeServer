# 權限管理系統測試指南

這是一套完整的 PHPUnit 測試，專為使用者、群組、權限、角色管理系統設計。

## 📁 測試文件結構

```
tests/
├── Feature/                          # 功能測試
│   ├── UserServiceTest.php          # 使用者服務測試
│   ├── TeamServiceTest.php          # 團隊服務測試  
│   ├── RoleServiceTest.php          # 角色服務測試
│   ├── PermissionServiceTest.php    # 權限服務測試
│   └── AuthorizationIntegrationTest.php # 權限整合測試
└── Unit/                            # 單元測試
    ├── UserModelTest.php            # 使用者模型測試
    └── TeamModelTest.php            # 團隊模型測試
```

## 🧪 測試類別說明

### Feature Tests (功能測試)

**UserServiceTest.php**
- ✅ 取得所有使用者
- ✅ 取得單一使用者資料  
- ✅ 創建使用者並分配團隊角色
- ✅ 分配使用者到團隊角色
- ✅ 更新使用者在團隊中的角色
- ✅ 取得使用者在所有團隊中的角色
- ✅ 更新和刪除使用者
- ✅ 錯誤處理（不存在的使用者、團隊、角色）

**TeamServiceTest.php**  
- ✅ 取得所有團隊
- ✅ 取得單一團隊資料
- ✅ 創建、更新、刪除團隊
- ✅ 取得團隊成員和角色
- ✅ 團隊刪除時的安全檢查
- ✅ 錯誤處理

**RoleServiceTest.php**
- ✅ 角色 CRUD 操作
- ✅ 為角色分配權限
- ✅ 取得角色權限
- ✅ 取得團隊角色
- ✅ 角色刪除時的安全檢查
- ✅ 權限同步機制

**PermissionServiceTest.php**
- ✅ 權限 CRUD 操作
- ✅ 批量創建權限
- ✅ 按模組分組權限
- ✅ 檢查使用者權限
- ✅ 權限刪除時的級聯處理

**AuthorizationIntegrationTest.php**
- ✅ 完整的使用者-團隊-角色工作流程
- ✅ 使用者角色更新流程
- ✅ 多團隊使用者場景
- ✅ 權限繼承測試
- ✅ 團隊刪除級聯測試
- ✅ 單一角色約束測試
- ✅ 領導者 vs 成員權限差異

### Unit Tests (單元測試)

**UserModelTest.php**
- ✅ 使用者團隊關聯
- ✅ getTeamId() 方法
- ✅ belongsToTeam() 方法
- ✅ getRolesForTeam() 方法
- ✅ teamsWithRoles() 方法
- ✅ Spatie Permission 特性
- ✅ 模型屬性和序列化

**TeamModelTest.php**
- ✅ 團隊使用者關聯
- ✅ 團隊角色關聯
- ✅ rolesForUser() 方法
- ✅ usersWithRoles() 方法
- ✅ 關聯完整性檢查
- ✅ 級聯刪除測試

## 🚀 運行測試

### 運行所有測試
```bash
php artisan test
```

### 運行特定測試類別
```bash
# 功能測試
php artisan test tests/Feature/

# 單元測試  
php artisan test tests/Unit/

# 特定測試文件
php artisan test tests/Feature/UserServiceTest.php
```

### 運行特定測試方法
```bash
php artisan test --filter=test_can_create_user_with_team_and_role
```

### 詳細輸出
```bash
php artisan test --verbose
```

### 測試覆蓋率（需要 Xdebug）
```bash
php artisan test --coverage
```

## 📊 測試覆蓋的功能

### ✅ 使用者管理
- [x] 使用者 CRUD 操作
- [x] 使用者角色分配
- [x] 使用者權限檢查
- [x] 多團隊使用者管理

### ✅ 團隊管理
- [x] 團隊 CRUD 操作
- [x] 團隊成員管理
- [x] 團隊角色管理
- [x] 團隊刪除安全性

### ✅ 角色管理
- [x] 角色 CRUD 操作
- [x] 角色權限分配
- [x] 領導者角色機制
- [x] 單一角色約束

### ✅ 權限管理
- [x] 權限 CRUD 操作
- [x] 權限模組分組
- [x] 權限檢查機制
- [x] 權限繼承

### ✅ 整合測試
- [x] 完整工作流程
- [x] 多租戶場景
- [x] 錯誤處理
- [x] 資料一致性

## 🔧 測試前準備

### 1. 確保資料庫配置
```bash
# 檢查 .env.testing 或 phpunit.xml 中的資料庫配置
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

### 2. 運行遷移和填充
測試會自動使用 `RefreshDatabase` 特性和 `GenUserPermission` 填充器。

### 3. 依賴套件
確保已安裝：
- Laravel Framework
- Spatie Permission 套件
- PHPUnit

## 📈 測試統計

- **總測試數量**: 80+ 個測試
- **測試覆蓋**: Service 層、Model 層、整合流程
- **斷言類型**: 功能測試、資料庫測試、關聯測試
- **錯誤場景**: 404、409、500 錯誤處理

## 🐛 測試失敗處理

### 常見問題

1. **資料庫錯誤**
   ```bash
   # 確保測試資料庫配置正確
   php artisan config:clear
   php artisan test
   ```

2. **權限相關錯誤**
   ```bash
   # 檢查 Spatie Permission 配置
   php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
   ```

3. **填充器錯誤**
   ```bash
   # 檢查 GenUserPermission 填充器是否存在且正確
   php artisan db:seed --class=GenUserPermission
   ```

## 📝 新增測試

### 新增 Service 測試
```php
/** @test */
public function test_new_service_method()
{
    // Arrange: 準備測試資料
    // Act: 執行要測試的方法
    // Assert: 驗證結果
}
```

### 新增模型測試
```php
/** @test */  
public function test_new_model_relationship()
{
    // 測試新的模型關聯或方法
}
```

這套測試確保您的權限管理系統的穩定性和正確性！