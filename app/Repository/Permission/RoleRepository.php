<?php

namespace App\Repository\Permission;

use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRepository
{
    private $rules = [
        'id' => [
            'id' => 'required|integer',
        ],
        'create' => [
            'name' => 'required|string|max:255',
            'team_id' => 'required|integer',
            'is_leader' => 'required|boolean',
        ],
        'update' => [
            'name' => 'required|string|max:255',
        ],
    ];

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
        $this->validate(['id' => $roleId], ['id']);

        return Role::with(['permissions', 'users', 'team'])->find($roleId);
    }

    /**
     * 創建新角色
     */
    public function createRole(int $teamId, array $roleData): Role
    {
        $this->validate($roleData, ['create']);

        $team = Team::find($teamId);
        if (! $team) {
            throw new \DomainException('Team not found');
        }

        if ($roleData['is_leader'] && $team->hasLeaderRole()) {
            throw new \DomainException('Team already has a leader role');
        }

        return Role::create([
            'name' => $roleData['name'],
            'team_id' => $teamId,
            'is_leader' => $roleData['is_leader'],
        ]);
    }

    /**
     * 更新角色資料-名字
     */
    public function updateRole($roleId, $roleData): void
    {
        $data = array_merge(['id' => $roleId], $roleData);
        $this->validate($data, ['id', 'update']);

        $role = Role::find($roleId);
        if (! $role) {
            throw new \DomainException('Role not found');
        }

        $role->name = $roleData['name'];
        if ($role->isDirty()) {
            $role->save();
        }
    }

    /**
     * 刪除角色
     */
    public function deleteRole($roleId): void
    {
        $this->validate(['id' => $roleId], ['id']);

        $role = Role::find($roleId);
        if (! $role) {
            throw new \DomainException('Role not found');
        }

        $hasUsers = $role->users()->exists();
        if ($hasUsers) {
            throw new \DomainException('Cannot delete role with existing users');
        }

        DB::transaction(function () use ($role): void {
            $role->revokePermissionTo($role->permissions);
            $role->delete();
        });
    }

    /**
     * 為角色分配權限
     */
    public function assignPermissions($roleId, $permissionIds): void
    {
        $this->validate(['id' => $roleId], ['id']);

        $role = Role::find($roleId);
        if (! $role) {
            throw new \DomainException('Role not found');
        }

        $permissions = Permission::whereIn('id', $permissionIds)->get();
        if ($permissions->count() !== count($permissionIds)) {
            throw new \DomainException('One or more permissions not found');
        }

        $role->syncPermissions($permissions);
    }

    private function validate(array $data, array $actions = ['create']): void
    {
        $rules = [];
        foreach ($actions as $action) {
            if (! isset($this->rules[$action])) {
                throw new \Exception("Validation rules for action '{$action}' not defined");
            }

            $rules = array_merge($rules, $this->rules[$action]);
        }

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new \Exception('Validation failed: '.implode(', ', $validator->errors()->all()));
        }
    }
}
