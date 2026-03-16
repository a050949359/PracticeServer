<?php

namespace App\Services\Permission\Basic;

use App\Models\Team;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class TeamService
{
    /**
     * 取得所有團隊
     */
    public function getAllTeams()
    {
        return Team::with(['roles', 'users'])->get();
    }

    /**
     * 取得單一團隊資料
     */
    public function getTeamData($teamId)
    {
        return Team::with(['roles', 'users'])->find($teamId);
    }

    /**
     * 創建新團隊
     * [
     *   "team" => "name",
     *   "role" => [[
     *       "name" => "role_name",
     *       "is_leader" => true/false
     *     ],
     *   ]
     * ]
     */
    public function createTeam($teamData): bool
    {
        try {
            DB::transaction(function () use ($teamData): void {
                $team = Team::create([
                    'name' => $teamData['team'],
                ]);

                RoleService::createRole([
                    'name' => $teamData['role']['name'],
                    'team_id' => $team->id,
                    'is_leader' => $teamData['role']['is_leader'],
                ]);
            });

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 更新團隊資料
     */
    public function updateTeam($teamId, $teamData): bool
    {
        $team = Team::find($teamId);
        
        if (!$team) {
            return false;
        }

        try {
            $team->name = $teamData['name'];
            if ($team->isDirty()) {
                $team->save();
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 刪除團隊
     */
    public function deleteTeam($teamId): bool
    {
        try {
            DB::transaction(function () use ($teamId): void {
                $team = Team::find($teamId);
                if (!$team) {
                    throw new \Exception('Team not found');
                }

                // 檢查是否有使用者屬於此團隊
                $hasUsers = $team->users()->exists();
                if ($hasUsers) {
                    throw new \Exception('Cannot delete team with members');
                }

                // 刪除團隊相關的角色
                $team->roles()->delete();
                
                // 刪除團隊
                $team->delete();
            });

            return true;
        } catch (\Throwable  $throw) {
            return false;
        }
    }

    /**
     * 取得團隊成員
     */
    public function getTeamMembers($teamId): null|Team
    {
        $team = Team::with('roles.users')->find($teamId);
        if (!$team) {
            return null;
        }

        $team = $team->makeHidden(['created_at', 'updated_at']);
        $team->roles->makeHidden(['guard_name', 'created_at', 'updated_at', 'team_id']);
        foreach ($team->roles as $role) {
            $role->users->makeHidden(['email', 'email_verified_at', 'created_at', 'updated_at', 'pivot']);
        }
        
        return $team;
    }

    /**
     * 取得團隊角色
     */
    public function getTeamRoles($teamId): null|Team
    {
        $team = Team::find($teamId);
        if (!$team) {
            return null;
        }

        $team->makeHidden(['created_at', 'updated_at']);
        $team->roles->makeHidden(['guard_name', 'created_at', 'updated_at', 'team_id']);
        
        return $team;
    }

    /**
     * 檢查用戶在特定團隊中的角色
     */
    public function checkRoleInTeam(User $user, $roleName, Team $team): bool
    {
        // 設定團隊上下文
        setPermissionsTeamId($team->id);
        
        return $user->hasRole($roleName);
    }

    /**
     * 獲取用戶在所有團隊中的角色
     */
    public function getRolesAcrossTeams(User $user): null|array
    {
        $userTeamRoles = [];
        
        // 獲取用戶所屬的所有團隊
        $teams = Team::all(); 
        
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
        
        return $userTeamRoles;
    }

    /**
     * 創建團隊專屬角色
     */
    public function createRoleInTeam($roleName, Team $team, array $permissions = []): bool
    {
        try {
            $role = Role::create([
                'name' => $roleName,
                'guard_name' => 'web',
                'team_id' => $team->id
            ]);
            
            // 如果提供了權限，則分配給角色
            if (!empty($permissions)) {
                $role->givePermissionTo($permissions);
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 檢查用戶在團隊中是否有任意一個角色
     */
    public function hasRoleInTeam(User $user, array $roleNames, Team $team): bool
    {
        setPermissionsTeamId($team->id);

        return $user->hasAnyRole($roleNames);
    }

    public function deleteRoleInTeam($roleId): bool
    {
        try {
            $role = Role::find($roleId);
            if (!$role) {
                throw new \Exception('Role not found');
            }

            $role->delete();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 檢查用戶在特定團隊中的權限
     */
    public function canInTeam(User $user, Team $team, $permissionName): bool
    {
        // 設定團隊上下文
        setPermissionsTeamId($team->id);
        
        return $user->can($permissionName);
    }
}