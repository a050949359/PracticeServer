<?php

namespace App\Repository\Permission;

use App\Models\Team;
use App\Models\User;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class UserRepository
{
    public function getAllUsers(): Collection
    {
        return User::all();
    }

    public function getUser($userId): User
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found', 404);
        }

        return $user;
    }

    /**
     * 建立使用者並分配團隊和角色
     *
     * @param  array  $userData
     * @param  int  $teamId
     * @param  int  $roleId
     * @return void
     */
    public function createUserWithTeamAndRole($userData, $teamId, $roleId): void
    {
        DB::transaction(function () use ($userData, $teamId, $roleId) {
            $user = User::create($userData);

            $team = Team::find($teamId);
            if (! $team) {
                throw new \Exception('Team not found', 404);
            }

            $role = Role::where('id', $roleId)->where('team_id', $teamId)->first();
            if (! $role) {
                throw new \Exception('Role not found in specified team', 404);
            }

            // 如果角色是 leader，檢查 team 是否已有 leader
            if ($role->is_leader) {
                if ($team->hasLeader()) {
                    throw new \Exception("Team '{$team->name}' already has a leader", 409);
                }
            }

            setPermissionsTeamId($team);
            $user->assignRole($role);
        });
    }

    /**
     * 為現有使用者分配團隊角色
     *
     * @param  int  $userId
     * @param  int  $teamId
     * @param  int  $roleId
     * @return void
     */
    public function assignUserToTeamRole($userId, $teamId, $roleId): void
    {
        DB::transaction(function () use ($userId, $teamId, $roleId) {
            $user = User::find($userId);
            if (!$user) {
                throw new \Exception('User not found', 404);
            }

            $team = Team::find($teamId);
            if (!$team) {
                throw new \Exception('Team not found', 404);
            }

            $role = Role::where('id', $roleId)->where('team_id', $teamId)->first();
            if (!$role) {
                throw new \Exception('Role not found in specified team', 404);
            }

            // 如果角色是 leader，檢查 team 是否已有 leader
            if ($role->is_leader) {
                if ($team->hasLeader()) {
                    throw new \Exception("Team '{$team->name}' already has a leader", 409);
                }
            }

            setPermissionsTeamId($team);
            $existingRoles = $user->roles()->where('team_id', $teamId)->get();
            if ($existingRoles->isNotEmpty()) {
                foreach ($existingRoles as $existingRole) {
                    $user->removeRole($existingRole);
                }
            }

            $user->assignRole($role);
        });
    }

    public function removeUserFromTeam($userId, $teamId): void
    {
        DB::transaction(function () use ($userId, $teamId) {
            $user = User::find($userId);
            if (!$user) {
                throw new \Exception('User not found', 404);
            }

            $team = Team::find($teamId);
            if (!$team) {
                throw new \Exception('Team not found', 404);
            }

            setPermissionsTeamId($team);
        
            $roles = $user->roles()->where('team_id', $teamId)->get();
            foreach ($roles as $role) {
                $user->removeRole($role);
            }
        });
    }

    /**
     * 取得使用者在所有團隊中的角色
     *
     * @param  int  $userId
     * @return $this
     */
    public function getUserTeamRoles($userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found', 404);
        }

        $userTeamRoles = [];

        $teams = Team::all();
        foreach ($teams as $team) {
            setPermissionsTeamId($team);
            $user->load('roles');
            if ($user->roles->isNotEmpty()) {
                $userTeamRoles[] = [
                    'team' => $team,
                    'roles' => $user->roles,
                    'is_leader' => $user->roles->where('is_leader', true)->isNotEmpty(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ];
            }
        }

        return $userTeamRoles;
    }

    public function updateUser($userId, $data): void
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found', 404);
        }

        if (!$user->update($data)) {
            throw new \Exception('Failed to update user', 500);
        }
    }

    public function deleteUser($userId): void
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found', 404);
        }

        if (!$user->delete()) {
            throw new \Exception('Failed to delete user', 500);
        }
    }
}
