<?php

namespace App\Traits;

use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HasTeamRole Trait
 * 
 * 提供便利的團隊權限管理方法，簡化 Spatie Permission 在 teams 模式下的使用
 * 
 * 使用方式：在 User model 中 use 這個 trait
 * class User extends Authenticatable {
 *     use HasTeamRole;
 * }
 */
trait HasTeamRole
{
    /**
     * 在指定團隊中為用戶分配角色
     * 
     * @param string|Role $role 角色名稱或 Role 實例
     * @param Team|int $team 團隊實例或 ID
     * @return self
     */
    public function assignRoleInTeam($role, $team): self
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        
        // 設定團隊上下文
        setPermissionsTeamId($teamId);
        
        // 分配角色
        $this->assignRole($role);
        
        return $this;
    }

    /**
     * 在指定團隊中移除用戶角色
     * 
     * @param string|Role $role 角色名稱或 Role 實例
     * @param Team|int $team 團隊實例或 ID
     * @return self
     */
    public function removeRoleInTeam($role, $team): self
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        
        // 設定團隊上下文
        setPermissionsTeamId($teamId);
        
        // 移除角色
        $this->removeRole($role);
        
        return $this;
    }

    /**
     * 同步用戶在指定團隊中的角色
     * 
     * @param array $roles 角色數組
     * @param Team|int $team 團隊實例或 ID
     * @return self
     */
    public function syncRolesInTeam(array $roles, $team): self
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        
        // 設定團隊上下文
        setPermissionsTeamId($teamId);
        
        // 同步角色
        $this->syncRoles($roles);
        
        return $this;
    }

    /**
     * 檢查用戶在指定團隊中是否有特定角色
     * 
     * @param string|Role $role 角色名稱或 Role 實例
     * @param Team|int $team 團隊實例或 ID
     * @return bool
     */
    public function hasRoleInTeam($role, $team): bool
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        
        // 設定團隊上下文
        setPermissionsTeamId($teamId);
        
        // 重新載入關係以反映當前團隊上下文
        $this->load('roles');
        
        return $this->hasRole($role);
    }

    /**
     * 檢查用戶在指定團隊中是否有任意一個角色
     * 
     * @param array $roles 角色數組
     * @param Team|int $team 團隊實例或 ID
     * @return bool
     */
    public function hasAnyRoleInTeam(array $roles, $team): bool
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        
        setPermissionsTeamId($teamId);
        $this->load('roles');
        
        return $this->hasAnyRole($roles);
    }

    /**
     * 檢查用戶在指定團隊中是否有所有指定角色
     * 
     * @param array $roles 角色數組
     * @param Team|int $team 團隊實例或 ID
     * @return bool
     */
    public function hasAllRolesInTeam(array $roles, $team): bool
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        
        setPermissionsTeamId($teamId);
        $this->load('roles');
        
        return $this->hasAllRoles($roles);
    }

    /**
     * 檢查用戶在指定團隊中是否有特定權限
     * 
     * @param string|Permission $permission 權限名稱或 Permission 實例
     * @param Team|int $team 團隊實例或 ID
     * @return bool
     */
    public function canInTeam($permission, $team): bool
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        
        setPermissionsTeamId($teamId);
        
        return $this->can($permission);
    }

    /**
     * 獲取用戶在指定團隊中的所有角色
     * 
     * @param Team|int $team 團隊實例或 ID
     * @return Collection
     */
    public function getRolesInTeam($team): Collection
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        
        setPermissionsTeamId($teamId);
        $this->load('roles');
        
        return $this->roles;
    }

    /**
     * 獲取用戶在指定團隊中的所有權限
     * 
     * @param Team|int $team 團隊實例或 ID
     * @return Collection
     */
    public function getPermissionsInTeam($team): Collection
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        
        setPermissionsTeamId($teamId);
        
        return $this->getAllPermissions();
    }

    /**
     * 獲取用戶在所有團隊中的角色
     * 
     * @return array 格式: [['team_id' => 1, 'team_name' => 'Team 1', 'roles' => ['admin', 'leader']], ...]
     */
    public function getRolesAcrossAllTeams(): array
    {
        $teamsAndRoles = [];
        
        // 獲取所有團隊（可根據業務邏輯調整）
        $teams = Team::all();
        
        foreach ($teams as $team) {
            setPermissionsTeamId($team->id);
            $this->load('roles');
            
            $roles = $this->roles->pluck('name')->toArray();
            
            if (!empty($roles)) {
                $teamsAndRoles[] = [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'roles' => $roles
                ];
            }
        }
        
        return $teamsAndRoles;
    }

    /**
     * 檢查用戶是否在指定團隊中是領導者
     * 
     * @param Team|int $team 團隊實例或 ID
     * @return bool
     */
    public function isLeaderInTeam($team): bool
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        
        setPermissionsTeamId($teamId);
        $this->load('roles');
        
        // 檢查是否有標記為 is_leader=true 的角色
        return $this->roles()->where('is_leader', true)->exists();
    }

    /**
     * 在團隊間轉移角色
     * 
     * @param string|Role $role 角色名稱或實例
     * @param Team|int $fromTeam 來源團隊
     * @param Team|int $toTeam 目標團隊
     * @return self
     */
    public function transferRoleBetweenTeams($role, $fromTeam, $toTeam): self
    {
        $fromTeamId = $fromTeam instanceof Team ? $fromTeam->id : $fromTeam;
        $toTeamId = $toTeam instanceof Team ? $toTeam->id : $toTeam;
        
        // 從來源團隊移除角色
        setPermissionsTeamId($fromTeamId);
        $this->removeRole($role);
        
        // 在目標團隊分配角色
        setPermissionsTeamId($toTeamId);
        $this->assignRole($role);
        
        return $this;
    }

    /**
     * 複製角色到其他團隊
     * 
     * @param string|Role $role 角色名稱或實例
     * @param Team|int $fromTeam 來源團隊
     * @param Team|int $toTeam 目標團隊
     * @return self
     */
    public function copyRoleToTeam($role, $fromTeam, $toTeam): self
    {
        $toTeamId = $toTeam instanceof Team ? $toTeam->id : $toTeam;
        
        // 在目標團隊分配角色（不移除原有角色）
        setPermissionsTeamId($toTeamId);
        $this->assignRole($role);
        
        return $this;
    }

    /**
     * 獲取用戶所屬的所有團隊 ID
     * 
     * @return array
     */
    public function getTeamIds(): array
    {
        // 查詢 model_has_roles 表中該用戶的所有 team_id
        $teamIds = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', get_class($this))
            ->where('model_id', $this->id)
            ->whereNotNull('team_id')
            ->distinct()
            ->pluck('team_id')
            ->toArray();
        
        return $teamIds;
    }

    /**
     * 獲取用戶所屬的所有團隊
     * 
     * @return Collection
     */
    public function getTeams(): Collection
    {
        $teamIds = $this->getTeamIds();
        
        return Team::whereIn('id', $teamIds)->get();
    }

    /**
     * 檢查用戶是否屬於指定團隊
     * 
     * @param Team|int $team 團隊實例或 ID
     * @return bool
     */
    public function belongsToTeam($team): bool
    {
        $teamId = $team instanceof Team ? $team->id : $team;
        
        return in_array($teamId, $this->getTeamIds());
    }

    /**
     * 在執行操作時保持原始團隊上下文
     * 
     * @param Team|int $team 臨時切換的團隊
     * @param callable $callback 在指定團隊上下文中執行的回調
     * @return mixed
     */
    public function withTeamContext($team, callable $callback)
    {
        // 備份當前團隊上下文
        $originalTeamId = getPermissionsTeamId();
        
        try {
            // 切換到指定團隊
            $teamId = $team instanceof Team ? $team->id : $team;
            setPermissionsTeamId($teamId);
            
            // 執行回調
            return $callback($this);
        } finally {
            // 恢復原始團隊上下文
            setPermissionsTeamId($originalTeamId);
        }
    }

    /**
     * 批量在多個團隊中分配相同角色
     * 
     * @param string|Role $role 角色名稱或實例
     * @param array $teams 團隊 ID 數組
     * @return self
     */
    public function assignRoleInMultipleTeams($role, array $teams): self
    {
        foreach ($teams as $team) {
            $this->assignRoleInTeam($role, $team);
        }
        
        return $this;
    }

    /**
     * 批量從多個團隊中移除相同角色
     * 
     * @param string|Role $role 角色名稱或實例
     * @param array $teams 團隊 ID 數組
     * @return self
     */
    public function removeRoleFromMultipleTeams($role, array $teams): self
    {
        foreach ($teams as $team) {
            $this->removeRoleInTeam($role, $team);
        }
        
        return $this;
    }

    /**
     * 檢查用戶在任意團隊中是否有指定角色
     * 
     * @param string|Role $role 角色名稱或實例
     * @return bool
     */
    public function hasRoleInAnyTeam($role): bool
    {
        $teams = $this->getTeamIds();
        
        foreach ($teams as $teamId) {
            if ($this->hasRoleInTeam($role, $teamId)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 獲取用戶在所有團隊中有指定角色的團隊列表
     * 
     * @param string|Role $role 角色名稱或實例
     * @return Collection
     */
    public function getTeamsWithRole($role): Collection
    {
        $teamsWithRole = [];
        $teams = $this->getTeams();
        
        foreach ($teams as $team) {
            if ($this->hasRoleInTeam($role, $team->id)) {
                $teamsWithRole[] = $team;
            }
        }
        
        return collect($teamsWithRole);
    }
}