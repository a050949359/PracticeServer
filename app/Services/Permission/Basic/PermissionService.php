<?php

namespace App\Services\Permission\Basic;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class PermissionService
{
    /**
     * 取得所有權限
     */
    public function getAllPermissions(): Collection
    {
        return Permission::with('roles')->get();
    }

    /**
     * 取得單一權限資料
     */
    public function getPermissionData($permissionId): ?Permission
    {
        return Permission::with(['roles.users'])->find($permissionId);
    }

    /**
     * 創建新權限
     */
    public function createPermission($permissionData): bool
    {
        try {
            $permission = Permission::create($permissionData);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 批量創建權限
     */
    public function createPermissions($permissionsData): bool
    {
        try {
            DB::transaction(function () use ($permissionsData): void {
                foreach ($permissionsData as $permissionName) {
                    Permission::create(['name' => $permissionName]);
                }
            });

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 更新權限資料
     */
    public function updatePermission($permissionId, $permissionData): bool
    {
        try {
            $permission = Permission::find($permissionId);
            if (!$permission) {
                throw new \Exception('Permission not found');
            }

            $permission->update($permissionData);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 刪除權限
     */
    public function deletePermission($permissionId): bool
    {
        try {
            $permission = Permission::find($permissionId);
            if (!$permission) {
                throw new \Exception('Permission not found');
            }

            DB::transaction(function () use ($permission): void {
                // 從所有角色中移除此權限
                $roles = $permission->roles;
                foreach ($roles as $role) {
                    $role->revokePermissionTo($permission);
                }
                
                // 刪除權限
                $permission->delete();
            });
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 權限分組 - 按模組分類
     */
    public function getPermissionsByModule(): array
    {
        $permissions = Permission::all();
        
        $permissionsByModule = [];
        
        foreach ($permissions as $permission) {
            // 根據權限名稱的前綴進行分組（例如：user.view -> user 模組）
            $parts = explode('.', $permission->name);
            $module = $parts[0] ?? 'general';
            
            if (!isset($permissionsByModule[$module])) {
                $permissionsByModule[$module] = [];
            }
            
            $permissionsByModule[$module][] = $permission;
        }

        return $permissionsByModule;
    }

    /**
     * 透過關鍵字搜尋權限
     */
    public function searchPermissionsByKeyword($keyword): Collection
    {
        return Permission::where('name', 'LIKE', "%{$keyword}%")
            ->with('roles')
            ->get();
    }

    /**
     * 使用 wildcard 搜尋權限
     * 支援模式: user.*, *.view, user.manage.*
     */
    public function searchPermissionsByWildcard($pattern): Collection
    {
        $sqlPattern = str_replace('*', '%', $pattern);

        return Permission::where('name', 'LIKE', $sqlPattern)
            ->with('roles')
            ->get();
    }

    /**
     * 高級搜尋 - 結合關鍵字和分類
     */
    public function searchPermissionsAdvanced($filters = []): Collection
    {
        $query = Permission::with('roles');

        // 關鍵字搜尋
        if (!empty($filters['keyword'])) {
            $query->where('name', 'LIKE', "%{$filters['keyword']}%");
        }

        // 模組篩選 (基於權限名稱的前綴)
        if (!empty($filters['module'])) {
            $query->where('name', 'LIKE', $filters['module'] . '.%');
        }

        // 動作篩選 (基於權限名稱的後綴)
        if (!empty($filters['action'])) {
            $query->where('name', 'LIKE', '%.' . $filters['action']);
        }

        // wildcard 模式
        if (!empty($filters['pattern'])) {
            $sqlPattern = str_replace('*', '%', $filters['pattern']);
            $query->where('name', 'LIKE', $sqlPattern);
        }

        $permissions = $query->get();

        // 按模組分組結果
        return $permissions->groupBy(function ($permission) {
            $parts = explode('.', $permission->name);
            return $parts[0] ?? 'general';
        });
    }

    /**
     * 檢查使用者的 wildcard 權限
     */
    public function checkUserWildcardPermissions($userId, $patterns): array
    {
        $user = User::find($userId);
        if (!$user) {
            return [];
        }

        $results = [];
        
        foreach ($patterns as $pattern) {
            $results[$pattern] = $user->can($pattern);
        }
        
        return $results;
    }

    /**
     * 取得所有可用的權限模式 (用於建議)
     */
    public function getPermissionPatterns()
    {
        try {
            $permissions = Permission::all();
            
            $modules = [];
            $actions = [];
            $patterns = [];
            
            foreach ($permissions as $permission) {
                $parts = explode('.', $permission->name);
                
                if (count($parts) >= 2) {
                    $module = $parts[0];
                    $action = $parts[count($parts) - 1];
                    
                    $modules[] = $module;
                    $actions[] = $action;
                    
                    // 生成可能的 wildcard 模式
                    $patterns[] = $module . '.*';
                    $patterns[] = '*.' . $action;
                }
            }
            
             return [
                'available_modules' => array_unique($modules),
                'available_actions' => array_unique($actions),
                'suggested_patterns' => array_unique($patterns),
                'example_patterns' => [
                    'user.*',         // 所有 user 模組權限
                    '*.view',         // 所有 view 權限
                    'user.manage.*',  // 所有 user.manage 相關權限
                    '*'               // 所有權限 (超級管理員)
                ]
            ];
        } catch (\Exception $e) {
            return [
                'available_modules' => [],
                'available_actions' => [],
                'suggested_patterns' => [],
                'example_patterns' => []
            ];
        }
    }
}