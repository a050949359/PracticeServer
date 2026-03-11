<?php

namespace App\Services;

use App\Models\User;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * Teams 模式下權限管理的正確使用方式
 */
class TeamsPermissionService extends Service
{
    /**
     * 在指定團隊中為用戶分配角色
     * 
     * 重要：Teams 模式下必須明確指定團隊上下文
     */
    public function assignRoleInTeam(User $user, $roleName, Team $team)
    {
        try {
            // 【關鍵步驟】設定團隊上下文
            setPermissionsTeamId($team->id);
            
            // 現在角色分配會自動綁定到指定團隊
            $user->assignRole($roleName);
            
            $this->generateResponse([
                'user' => $user->name,
                'role' => $roleName,
                'team' => $team->name,
                'message' => 'Role assigned successfully in team context'
            ]);
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to assign role: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 檢查用戶在特定團隊中的角色
     */
    public function checkUserRoleInTeam(User $user, $roleName, Team $team)
    {
        // 設定團隊上下文
        setPermissionsTeamId($team->id);
        
        $hasRole = $user->hasRole($roleName);
        
        $this->generateResponse([
            'user' => $user->name,
            'role' => $roleName,
            'team' => $team->name,
            'has_role' => $hasRole
        ]);
        
        return $this;
    }

    /**
     * 檢查用戶在特定團隊中的權限
     */
    public function checkUserPermissionInTeam(User $user, $permissionName, Team $team)
    {
        // 設定團隊上下文
        setPermissionsTeamId($team->id);
        
        $canDo = $user->can($permissionName);
        
        $this->generateResponse([
            'user' => $user->name,
            'permission' => $permissionName,
            'team' => $team->name,
            'can_do' => $canDo
        ]);
        
        return $this;
    }

    /**
     * 獲取用戶在所有團隊中的角色
     */
    public function getUserRolesAcrossTeams(User $user)
    {
        $userTeamRoles = [];
        
        // 獲取用戶所屬的所有團隊
        $teams = Team::all(); // 或根據業務邏輯獲取相關團隊
        
        foreach ($teams as $team) {
            setPermissionsTeamId($team->id);
            
            // 重新載入角色關係以反映當前團隊上下文
            $user->load('roles');
            $roles = $user->roles->pluck('name')->toArray();
            
            if (!empty($roles)) {
                $userTeamRoles[] = [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'roles' => $roles
                ];
            }
        }
        
        $this->generateResponse([
            'user' => $user->name,
            'teams_and_roles' => $userTeamRoles
        ]);
        
        return $this;
    }

    /**
     * 在團隊間轉移用戶角色
     */
    public function transferUserRoleBetweenTeams(User $user, $roleName, Team $fromTeam, Team $toTeam)
    {
        try {
            DB::beginTransaction();
            
            // 從原團隊移除角色
            setPermissionsTeamId($fromTeam->id);
            $user->removeRole($roleName);
            
            // 在新團隊分配角色
            setPermissionsTeamId($toTeam->id);
            $user->assignRole($roleName);
            
            DB::commit();
            
            $this->generateResponse([
                'user' => $user->name,
                'role' => $roleName,
                'from_team' => $fromTeam->name,
                'to_team' => $toTeam->name,
                'message' => 'Role transferred successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->generateResponse(null, 'Failed to transfer role: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 創建團隊專屬角色
     */
    public function createTeamRole($roleName, Team $team, array $permissions = [])
    {
        try {
            // 在 Teams 模式下創建角色必須指定 team_id
            $role = Role::create([
                'name' => $roleName,
                'guard_name' => 'web',
                'team_id' => $team->id
            ]);
            
            // 如果提供了權限，則分配給角色
            if (!empty($permissions)) {
                $role->givePermissionTo($permissions);
            }
            
            $this->generateResponse([
                'role' => $role->name,
                'team' => $team->name,
                'permissions_count' => count($permissions),
                'message' => 'Team role created successfully'
            ]);
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to create team role: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 獲取團隊中特定角色的所有用戶
     */
    public function getUsersByRoleInTeam($roleName, Team $team)
    {
        // 設定團隊上下文
        setPermissionsTeamId($team->id);
        
        // 查詢該團隊中擁有特定角色的用戶
        $users = User::role($roleName)->get();
        
        $this->generateResponse([
            'team' => $team->name,
            'role' => $roleName,
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ];
            }),
            'count' => $users->count()
        ]);
        
        return $this;
    }

    /**
     * 批量同步用戶在團隊中的角色
     */
    public function syncUserRolesInTeam(User $user, array $roleNames, Team $team)
    {
        try {
            // 設定團隊上下文
            setPermissionsTeamId($team->id);
            
            // 同步角色（會移除其他角色，只保留指定的角色）
            $user->syncRoles($roleNames);
            
            $this->generateResponse([
                'user' => $user->name,
                'team' => $team->name,
                'new_roles' => $roleNames,
                'message' => 'User roles synced successfully in team'
            ]);
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to sync roles: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 檢查用戶在團隊中是否有任意一個角色
     */
    public function userHasAnyRoleInTeam(User $user, array $roleNames, Team $team)
    {
        setPermissionsTeamId($team->id);
        $hasAnyRole = $user->hasAnyRole($roleNames);
        
        $this->generateResponse([
            'user' => $user->name,
            'team' => $team->name,
            'roles_to_check' => $roleNames,
            'has_any_role' => $hasAnyRole
        ]);
        
        return $this;
    }

    /**
     * 檢查用戶在團隊中是否擁有所有指定角色
     */
    public function userHasAllRolesInTeam(User $user, array $roleNames, Team $team)
    {
        setPermissionsTeamId($team->id);
        $hasAllRoles = $user->hasAllRoles($roleNames);
        
        $this->generateResponse([
            'user' => $user->name,
            'team' => $team->name,
            'roles_to_check' => $roleNames,
            'has_all_roles' => $hasAllRoles
        ]);
        
        return $this;
    }

    /**
     * 獲取團隊的所有可用角色
     */
    public function getTeamRoles(Team $team)
    {
        // 獲取屬於該團隊的所有角色
        $roles = Role::where('team_id', $team->id)
            ->with('permissions')
            ->get();
        
        $this->generateResponse([
            'team' => $team->name,
            'roles' => $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions_count' => $role->permissions->count(),
                    'permissions' => $role->permissions->pluck('name')
                ];
            })
        ]);
        
        return $this;
    }
}