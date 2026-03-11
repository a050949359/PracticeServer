<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class TeamService extends Service
{
    /**
     * 取得所有團隊
     */
    public function getAllTeams()
    {
        $teams = Team::query()->get();
        $this->generateResponse($teams);
        return $this;
    }

    /**
     * 取得單一團隊資料
     */
    public function getTeamData($teamId)
    {
        $team = Team::with(['roles', 'users'])->find($teamId);
        
        if (!$team) {
            $this->generateResponse(null, 'Team not found', 404);
            return $this;
        }

        $this->generateResponse($team);
        return $this;
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
    public function createTeam($teamData)
    {
        try {
            DB::beginTransaction();

            $team = Team::create([
                'name' => $teamData['team'],
            ]);

            RoleService::createRole([
                'name' => $teamData['role']['name'],
                'team_id' => $team->id,
                'is_leader' => $teamData['role']['is_leader'],
            ]);

            DB::commit();

            $this->generateResponse();
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to create team: ' . $e->getMessage(), 500);
            DB::rollBack();
        }
        
        return $this;
    }

    /**
     * 更新團隊資料
     */
    public function updateTeam($teamId, $teamData)
    {
        $team = Team::find($teamId);
        
        if (!$team) {
            $this->generateResponse(null, 'Team not found', 404);
            return $this;
        }

        try {
            $team->name = $teamData['name'];
            if ($team->isDirty()) {
                $team->save();
            }
            
            $this->generateResponse($team, 'Team updated successfully');
        } catch (\Exception $e) {
            $this->generateResponse(null, 'Failed to update team: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 刪除團隊
     */
    public function deleteTeam($teamId)
    {
        $team = Team::find($teamId);
        
        if (!$team) {
            $this->generateResponse(null, 'Team not found', 404);
            return $this;
        }

        try {
            DB::beginTransaction();
            
            // 檢查是否有使用者屬於此團隊
            $hasUsers = $team->users()->exists();
            if ($hasUsers) {
                $this->generateResponse(null, 'Cannot delete team with existing users', 409);
                DB::rollBack();
                return $this;
            }
            
            // 刪除團隊相關的角色
            $team->roles()->delete();
            
            // 刪除團隊
            $team->delete();
            
            DB::commit();
            $this->generateResponse(null, 'Team deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->generateResponse(null, 'Failed to delete team: ' . $e->getMessage(), 500);
        }
        
        return $this;
    }

    /**
     * 取得團隊成員
     */
    public function getTeamMembers($teamId)
    {
        $team = Team::find($teamId);
        
        if (!Team::query()->where('id', $teamId)->exists()) {
            $this->generateResponse(null, 'Team not found', 404);
            return $this;
        }

        $team = Team::with('roles.users')->find($teamId)->makeHidden(['created_at', 'updated_at']);
        $team->roles->makeHidden(['guard_name', 'created_at', 'updated_at', 'team_id']);
        foreach ($team->roles as $role) {
            $role->users->makeHidden(['email', 'email_verified_at', 'created_at', 'updated_at', 'pivot']);
        }
        
        $this->generateResponse([
            $team->toArray(),
        ]);
        
        return $this;
    }

    /**
     * 取得團隊角色
     */
    public function getTeamRoles($teamId)
    {
        $team = Team::find($teamId)->makeHidden(['created_at', 'updated_at']);
        
        if (!$team) {
            $this->generateResponse(null, 'Team not found', 404);
            return $this;
        }

        $team->roles->makeHidden(['guard_name', 'created_at', 'updated_at', 'team_id']);
        
        $this->generateResponse([
            $team->toArray()
        ]);
        
        return $this;
    }
}