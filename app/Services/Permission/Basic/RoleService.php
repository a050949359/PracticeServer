<?php

namespace App\Services\Permission\Basic;

use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class RoleService
{
    /**
     * 取得所有角色
     */
    public function getAllRoles(): Collection
    {
        return Role::with(['permissions', 'users'])->get();
    }

    /**
     * 取得單一角色資料
     */
    public function getRoleData($roleId): ?Role
    {
        return Role::with(['permissions', 'users', 'team'])->find($roleId);
    }

    /**
     * 創建新角色
     */
    public static function createRole(array $roleData): bool
    {
        try {
            $team = Team::find($roleData['team_id']);
            if (!$team) {
                throw new \Exception('Team not found');
            }

            if ($team->hasLeaderRole()) {
                throw new \Exception('Team already has a leader role');
            }

            Role::create([
                'name' => $roleData['name'],
                'team_id' => $roleData['team_id'],
                'is_leader' => $roleData['is_leader'],
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 更新角色資料-名字
     */
    public function updateRole($roleId, $roleData): bool
    {
        try {
            $role = Role::find($roleId);
            if (!$role) {
                throw new \Exception('Role not found');
            }

            $role->name = $roleData['name'];
            if ($role->isDirty()) {
                $role->save();
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 刪除角色
     */
    public function deleteRole($roleId): bool
    {
        try {
            $role = Role::find($roleId);
            if (!$role) {
                throw new \Exception('Role not found');
            }

            $hasUsers = $role->users()->exists();
            if ($hasUsers) {
                throw new \Exception('Cannot delete role with existing users');
            }

            DB::transaction(function () use ($role): void {
                // 移除角色的所有權限
                $role->revokePermissionTo($role->permissions);
                
                // 刪除角色
                $role->delete();
            });
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 為角色分配權限
     */
    public function assignPermissions($roleId, $permissionIds): bool
    {
        try {
            $role = Role::find($roleId);
            if (!$role) {
                throw new \Exception('Role not found');
            }

            $permissions = Permission::whereIn('id', $permissionIds)->get();
            if ($permissions->count() !== count($permissionIds)) {
                throw new \Exception('One or more permissions not found');
            }
            
            $role->syncPermissions($permissions);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}