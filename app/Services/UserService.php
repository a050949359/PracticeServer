<?php

namespace App\Services;

use App\Models\User;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class UserService extends Service
{
    /**
     * 檢查團隊中是否已有 leader，並處理衝突
     * 
     * @param Team $team
     * @param Role $role
     * @param User|null $excludeUser 排除檢查的使用者（用於更新現有使用者角色時）
     * @return array|null 返回 null 表示沒有衝突，返回陣列表示有衝突詳情
     */
    protected function checkLeaderConflict($team, $role, $excludeUser = null)
    {
        // 如果要分配的角色不是 leader，無需檢查
        if (!$role->is_leader) {
            return null;
        }

        // 如果團隊沒有領導者，無衝突
        if (!$team->hasLeader()) {
            return null;
        }

        setPermissionsTeamId($team);
        
        // 取得所有領導者
        $leaders = $team->getLeaders();
        $conflictUsers = [];
        
        foreach ($leaders as $leader) {
            // 排除指定的使用者（用於更新場景）
            if (!$excludeUser || $leader->id !== $excludeUser->id) {
                // 取得該領導者在此團隊中的 leader 角色資訊
                $leaderRoles = $leader->roles()->where('team_id', $team->id)->where('is_leader', true)->get();
                
                foreach ($leaderRoles as $leaderRole) {
                    $conflictUsers[] = [
                        'user_id' => $leader->id,
                        'user_name' => $leader->name,
                        'role_id' => $leaderRole->id,
                        'role_name' => $leaderRole->name,
                    ];
                }
            }
        }

        return empty($conflictUsers) ? null : [
            'has_conflict' => true,
            'existing_leaders' => $conflictUsers,
            'team_name' => $team->name,
            'new_role_name' => $role->name,
        ];
    }

    public function getAllUsers()
    {
        $users = User::all();

        $this->generateResponse($users);

        return $this;
    }

    public function getUserData($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            $this->generateResponse(null, 'User not found', 404);
            return $this;
        }

        $this->generateResponse($user);

        return $this;
    }

    /**
     * 建立使用者並分配團隊和角色
     * 
     * @param array $userData 使用者基本資料 ['name', 'email', 'password']
     * @param int $teamId 團隊ID
     * @param string|int $roleName 角色名稱或ID
     * @param bool $forceLeaderReplace 是否強制替換現有 leader
     * @return $this
     */
    public function createUserWithTeamAndRole($userData, $teamId, $roleName, $forceLeaderReplace = false)
    {
        try {
            DB::beginTransaction();
            
            $user = User::create($userData);
            
            $team = Team::find($teamId);
            if (!$team) {
                $this->generateResponse(null, 'Team not found', 404);
                DB::rollBack();
                return $this;
            }
            
            $role = is_numeric($roleName) 
                ? Role::where('id', $roleName)->where('team_id', $teamId)->first()
                : Role::where('name', $roleName)->where('team_id', $teamId)->first();
                
            if (!$role) {
                $this->generateResponse(null, 'Role not found in specified team', 404);
                DB::rollBack();
                return $this;
            }
            
            // 檢查是否有 leader 衝突
            $leaderConflict = $this->checkLeaderConflict($team, $role);
            if ($leaderConflict) {
                if ($forceLeaderReplace) {
                    // 強制替換：移除現有 leader 的角色
                    foreach ($leaderConflict['existing_leaders'] as $existingLeader) {
                        $existingUser = User::find($existingLeader['user_id']);
                        $existingRole = Role::find($existingLeader['role_id']);
                        if ($existingUser && $existingRole) {
                            setPermissionsTeamId($team);
                            $existingUser->removeRole($existingRole);
                        }
                    }
                } else {
                    $this->generateResponse(null, 
                        "Cannot assign leader role '{$role->name}'. Team '{$team->name}' already has leader(s): " . 
                        implode(', ', array_column($leaderConflict['existing_leaders'], 'user_name')) . 
                        ". Use forceLeaderReplace=true to replace existing leader(s).", 
                        409);
                    DB::rollBack();
                    return $this;
                }
            }
            
            setPermissionsTeamId($team);

            $user->assignRole($role);
            
            DB::commit();
            
            $responseData = [
                'user' => $user->name,
                'team' => $team->name,
                'role' => $role->name,
            ];
            
            // 如果有替換 leader，添加相關資訊
            if ($forceLeaderReplace && $leaderConflict) {
                $responseData['replaced_leaders'] = array_column($leaderConflict['existing_leaders'], 'user_name');
                $responseData['message'] = 'User created and assigned to team role. Previous leader(s) were replaced.';
            }
            
            $this->generateResponse($responseData);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->generateResponse(null, 'Failed to create user: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }
    
    /**
     * 為現有使用者分配團隊角色
     * 
     * @param int $userId
     * @param int $teamId
     * @param string|int $roleName
     * @param bool $forceLeaderReplace 是否強制替換現有 leader
     * @return $this
     */
    public function assignUserToTeamRole($userId, $teamId, $roleName, $forceLeaderReplace = false)
    {
        try {
            DB::beginTransaction();
            
            // 1. 取得使用者
            $user = User::find($userId);
            if (!$user) {
                $this->generateResponse(null, 'User not found', 404);
                DB::rollBack();
                return $this;
            }
            
            // 2. 取得團隊
            $team = Team::find($teamId);
            if (!$team) {
                $this->generateResponse(null, 'Team not found', 404);
                DB::rollBack();
                return $this;
            }
            
            // 3. 取得角色
            $role = is_numeric($roleName)
                ? Role::where('id', $roleName)->where('team_id', $teamId)->first()
                : Role::where('name', $roleName)->where('team_id', $teamId)->first();
                
            if (!$role) {
                $this->generateResponse(null, 'Role not found in specified team', 404);
                DB::rollBack();
                return $this;
            }
            
            // 檢查是否有 leader 衝突（排除當前使用者）
            $leaderConflict = $this->checkLeaderConflict($team, $role, $user);
            if ($leaderConflict) {
                if ($forceLeaderReplace) {
                    // 強制替換：移除現有 leader 的角色
                    foreach ($leaderConflict['existing_leaders'] as $existingLeader) {
                        $existingUser = User::find($existingLeader['user_id']);
                        $existingRole = Role::find($existingLeader['role_id']);
                        if ($existingUser && $existingRole) {
                            $existingUser->removeRole($existingRole);
                        }
                    }
                } else {
                    $this->generateResponse(null, 
                        "Cannot assign leader role '{$role->name}'. Team '{$team->name}' already has leader(s): " . 
                        implode(', ', array_column($leaderConflict['existing_leaders'], 'user_name')) . 
                        ". Use forceLeaderReplace=true to replace existing leader(s).", 
                        409);
                    DB::rollBack();
                    return $this;
                }
            }
            
            // 4. 檢查使用者是否已在該團隊中有角色（若有則更新角色）
            setPermissionsTeamId($team);
            $existingRoles = $user->roles()->where('team_id', $teamId)->get();
            $isUpdate = false;
            
            if ($existingRoles->isNotEmpty()) {
                // 移除現有角色
                foreach ($existingRoles as $existingRole) {
                    $user->removeRole($existingRole);
                }
                $isUpdate = true;
            }
            
            // 5. 分配新角色
            $user->assignRole($role);
            
            // 5. 載入更新後的資料
            $user->load(['roles.permissions', 'roles.team']);
            
            DB::commit();
            
            $responseData = [
                'user' => $user,
                'team' => $team,
                'role' => $role,
                'action' => $isUpdate ? 'updated' : 'assigned',
                'message' => $isUpdate 
                    ? 'User role successfully updated in team' 
                    : 'User successfully assigned to team role'
            ];
            
            // 如果有替換 leader，添加相關資訊
            if ($forceLeaderReplace && $leaderConflict) {
                $responseData['replaced_leaders'] = array_column($leaderConflict['existing_leaders'], 'user_name');
                $responseData['message'] .= '. Previous leader(s) were replaced.';
            }
            
            $this->generateResponse($responseData);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->generateResponse(null, 'Failed to assign user to team role: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }
    
    /**
     * 取得使用者在所有團隊中的角色
     * 
     * @param int $userId
     * @return $this
     */
    public function getUserTeamRoles($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            $this->generateResponse(null, 'User not found', 404);
            return $this;
        }
        
        // 取得所有團隊並檢查用戶角色
        $teams = Team::all();
        $userTeamRoles = [];
        
        foreach ($teams as $team) {
            setPermissionsTeamId($team);
            
            // 重新載入角色以取得當前團隊的角色
            $user->load('roles');
            
            if ($user->roles->isNotEmpty()) {
                $userTeamRoles[] = [
                    'team' => $team,
                    'roles' => $user->roles,
                    'is_leader' => $user->roles->where('is_leader', true)->isNotEmpty(),
                    'permissions' => $user->getAllPermissions()->pluck('name')
                ];
            }
        }
        
        $this->generateResponse([
            'user' => $user,
            'team_roles' => $userTeamRoles
        ]);
        
        return $this;
    }

    public function updateUser($userId, $data)
    {
        // Logic to update an existing user in the database
        $user = User::find($userId);

        if (!$user) {
            $this->generateResponse(null, 'User not found', 404);
            return $this;
        }

        $user->update($data);

        $this->generateResponse($user);

        return $this;
    }

    public function deleteUser($userId)
    {
        // Logic to delete a user from the database
        $user = User::find($userId);

        if (!$user) {
            $this->generateResponse(null, 'User not found', 404);
            return $this;
        }

        $user->delete();
        $this->generateResponse(null);
        return $this;
    }
}
