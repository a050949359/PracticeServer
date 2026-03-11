<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class PermissionService extends Service
{
    /**
     * 取得所有權限
     */
    public function getAllPermissions()
    {
        $permissions = Permission::with('roles')->get();
        $this->generateResponse($permissions);
        return $this;
    }

    /**
     * 取得單一權限資料
     */
    public function getPermissionData($permissionId)
    {
        $permission = Permission::with(['roles.users'])->find($permissionId);
        
        if (!$permission) {
            $this->generateResponse(null, 'Permission not found', 404);
            return $this;
        }

        $this->generateResponse($permission);
        return $this;
    }

    /**
     * 創建新權限
     */
    public function createPermission($permissionData)
    {
        try {
            $permission = Permission::create($permissionData);
            $this->generateResponse($permission, 'Permission created successfully');
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to create permission: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 批量創建權限
     */
    public function createPermissions($permissionsData)
    {
        try {
            DB::beginTransaction();
            
            $createdPermissions = [];
            foreach ($permissionsData as $permissionName) {
                $permission = Permission::create(['name' => $permissionName]);
                $createdPermissions[] = $permission;
            }
            
            DB::commit();
            $this->generateResponse($createdPermissions, 'Permissions created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->generateResponse(null, 'Failed to create permissions: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 更新權限資料
     */
    public function updatePermission($permissionId, $permissionData)
    {
        $permission = Permission::find($permissionId);
        
        if (!$permission) {
            $this->generateResponse(null, 'Permission not found', 404);
            return $this;
        }

        try {
            $permission->update($permissionData);
            $this->generateResponse($permission, 'Permission updated successfully');
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to update permission: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 刪除權限
     */
    public function deletePermission($permissionId)
    {
        $permission = Permission::find($permissionId);
        
        if (!$permission) {
            $this->generateResponse(null, 'Permission not found', 404);
            return $this;
        }

        try {
            DB::beginTransaction();
            
            // 從所有角色中移除此權限
            $roles = $permission->roles;
            foreach ($roles as $role) {
                $role->revokePermissionTo($permission);
            }
            
            // 刪除權限
            $permission->delete();
            
            DB::commit();
            $this->generateResponse(null, 'Permission deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->generateResponse(null, 'Failed to delete permission: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 取得權限的角色
     */
    public function getPermissionRoles($permissionId)
    {
        $permission = Permission::with('roles.users')->find($permissionId);
        
        if (!$permission) {
            $this->generateResponse(null, 'Permission not found', 404);
            return $this;
        }

        $this->generateResponse([
            'permission' => $permission,
            'roles' => $permission->roles
        ]);
        
        return $this;
    }

    /**
     * 權限分組 - 按模組分類
     */
    public function getPermissionsByModule()
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
        
        $this->generateResponse($permissionsByModule);
        return $this;
    }

    /**
     * 權限檢查 - 檢查使用者是否有特定權限
     */
    public function checkUserPermissions($userId, $permissionNames)
    {
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            $this->generateResponse(null, 'User not found', 404);
            return $this;
        }

        $results = [];
        
        foreach ($permissionNames as $permissionName) {
            $results[$permissionName] = $user->can($permissionName);
        }
        
        $this->generateResponse([
            'user' => $user->name,
            'permissions' => $results
        ]);
        
        return $this;
    }

    /**
     * 透過關鍵字搜尋權限
     */
    public function searchPermissionsByKeyword($keyword)
    {
        try {
            $permissions = Permission::where('name', 'LIKE', "%{$keyword}%")
                ->with('roles')
                ->get();

            $this->generateResponse([
                'keyword' => $keyword,
                'found_permissions' => $permissions,
                'count' => $permissions->count()
            ]);
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to search permissions: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 使用 wildcard 搜尋權限
     * 支援模式: user.*, *.view, user.manage.*
     */
    public function searchPermissionsByWildcard($pattern)
    {
        try {
            // 將 wildcard 模式轉換為 SQL LIKE 模式
            $sqlPattern = str_replace('*', '%', $pattern);
            
            $permissions = Permission::where('name', 'LIKE', $sqlPattern)
                ->with('roles')
                ->get();

            $this->generateResponse([
                'pattern' => $pattern,
                'sql_pattern' => $sqlPattern,
                'matched_permissions' => $permissions,
                'count' => $permissions->count()
            ]);
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to search permissions with wildcard: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 高級搜尋 - 結合關鍵字和分類
     */
    public function searchPermissionsAdvanced($filters = [])
    {
        try {
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
            $groupedPermissions = $permissions->groupBy(function ($permission) {
                $parts = explode('.', $permission->name);
                return $parts[0] ?? 'general';
            });

            $this->generateResponse([
                'filters' => $filters,
                'total_found' => $permissions->count(),
                'permissions' => $permissions,
                'grouped_by_module' => $groupedPermissions
            ]);
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to search permissions: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 檢查使用者的 wildcard 權限
     */
    public function checkUserWildcardPermissions($userId, $patterns)
    {
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            $this->generateResponse(null, 'User not found', 404);
            return $this;
        }

        $results = [];
        
        foreach ($patterns as $pattern) {
            $results[$pattern] = $user->can($pattern);
        }
        
        $this->generateResponse([
            'user' => $user->name,
            'wildcard_permissions' => $results
        ]);
        
        return $this;
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
            
            $this->generateResponse([
                'available_modules' => array_unique($modules),
                'available_actions' => array_unique($actions),
                'suggested_patterns' => array_unique($patterns),
                'example_patterns' => [
                    'user.*',         // 所有 user 模組權限
                    '*.view',         // 所有 view 權限
                    'user.manage.*',  // 所有 user.manage 相關權限
                    '*'               // 所有權限 (超級管理員)
                ]
            ]);
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to get permission patterns: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }
}