<?php

namespace App\Services;

use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RoleService extends Service
{
    /**
     * 取得所有角色
     */
    public function getAllRoles()
    {
        $roles = Role::with(['permissions', 'users'])->get();
        $this->generateResponse($roles);
        return $this;
    }

    /**
     * 取得單一角色資料
     */
    public function getRoleData($roleId)
    {
        $role = Role::with(['permissions', 'users', 'team'])->find($roleId);
        
        if (!$role) {
            $this->generateResponse(null, 'Role not found', 404);
            return $this;
        }

        $this->generateResponse($role);
        return $this;
    }

    /**
     * 創建新角色
     */
    public static function createRole(array $roleData)
    {
        // 驗證團隊是否存在
        $team = Team::find($roleData['team_id']);
        if (!$team) {
            throw new \Exception('Team not found');
        }

        // 如果是 leader 角色，檢查該團隊是否已有 leader
        if ($team->hasLeaderRole()) {
            throw new \Exception('Team already has a leader role');
        }

        return Role::create([
            'name' => $roleData['name'],
            'team_id' => $roleData['team_id'],
            'is_leader' => $roleData['is_leader'],
        ]);
    }

    /**
     * 更新角色資料-名字
     */
    public function updateRole($roleId, $roleData)
    {
        $role = Role::find($roleId);
        
        if (!$role) {
            $this->generateResponse(null, 'Role not found', 404);
            return $this;
        }

        try {
            $role->name = $roleData['name'];
            if ($role->isDirty()) {
                $role->save();
            }

            $this->generateResponse($role, 'Role updated successfully');
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to update role: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 刪除角色
     */
    public function deleteRole($roleId)
    {
        $role = Role::find($roleId);
        
        if (!$role) {
            $this->generateResponse(null, 'Role not found', 404);
            return $this;
        }

        try {
            DB::beginTransaction();
            
            // 檢查是否有使用者使用此角色
            $hasUsers = $role->users()->exists();
            if ($hasUsers) {
                $this->generateResponse(null, 'Cannot delete role with existing users', 409);
                DB::rollBack();
                return $this;
            }
            
            // 移除角色的所有權限
            $role->revokePermissionTo($role->permissions);
            
            // 刪除角色
            $role->delete();
            
            DB::commit();
            $this->generateResponse(null, 'Role deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->generateResponse(null, 'Failed to delete role: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 為角色分配權限
     */
    public function assignPermissions($roleId, $permissionIds)
    {
        try {
            $role = Role::find($roleId);
            if (!$role) {
                $this->generateResponse(null, 'Role not found', 404);
                return $this;
            }

            $permissions = Permission::whereIn('id', $permissionIds)->get();
            if ($permissions->count() !== count($permissionIds)) {
                $this->generateResponse(null, 'One or more permissions not found', 404);
                return $this;
            }
            
            $role->syncPermissions($permissions);
            $this->generateResponse([
                'role' => $role,
                'permissions' => $permissions
            ], 'Permissions assigned successfully');
            
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to assign permissions: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }
}