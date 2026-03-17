<?php

namespace App\Repository\Permission;

use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TeamRepository
{
    private $rules = [
        'id' => [
            'id' => 'required|integer',
        ],
        'create' => [
            'name' => 'required|string|max:255',
        ],
        'update' => [
            'name' => 'required|string|max:255',
        ],
    ];

    /**
     * 取得所有團隊
     */
    public function getAllTeams(): Collection
    {
        return Team::with('roles')->get();
    }

    /**
     * 取得單一團隊資料
     */
    public function getTeamData($teamId): ?Team
    {
        $this->validate(['id' => $teamId], ['id']);

        return Team::with('roles')->find($teamId);
    }

    public function createTeam($teamData): Team
    {
        $this->validate($teamData, ['create']);

        return Team::create($teamData);
    }

    /**
     * 更新團隊資料
     */
    public function updateTeam($teamId, $teamData): void
    {
        $this->validate(array_merge(['id' => $teamId], $teamData), ['id', 'update']);

        $team = Team::find($teamId);
        if (! $team) {
            throw new \DomainException('Team not found');
        }

        $team->name = $teamData['name'];
        if ($team->isDirty()) {
            $team->save();
        }
    }

    /**
     * 刪除團隊
     */
    public function deleteTeam($teamId): void
    {
        $this->validate(['id' => $teamId], ['id']);

        $team = Team::find($teamId);
        if (! $team) {
            throw new \DomainException('Team not found');
        }

        // 檢查是否有使用者屬於此團隊
        $hasUsers = $team->users()->exists();
        if ($hasUsers) {
            throw new \DomainException('Cannot delete team with members');
        }

        DB::transaction(function () use ($team): void {
            $team->roles()->delete();
            $team->delete();
        });
    }

    public function getAllTeamsMembers(): Collection
    {
        $teams = Team::with('roles.users')
            ->get()
            ->makeHidden(['created_at', 'updated_at']);
        $teams->roles->makeHidden(['guard_name', 'created_at', 'updated_at', 'team_id']);
        
        foreach ($teams->roles as $role) {
            $role->users->makeHidden(['email', 'email_verified_at', 'created_at', 'updated_at', 'pivot']);
        }

        return $teams;
    }

    /**
     * 取得團隊成員
     */
    public function getTeamMembers($teamId): Team
    {
        $this->validate(['id' => $teamId], ['id']);

        $team = Team::with('roles.users')->find($teamId);
        if (!$team) {
            throw new \DomainException('Team not found');
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
    public function getTeamRoles($teamId): Team
    {
        $this->validate(['id' => $teamId], ['id']);

        $team = Team::with('roles')->find($teamId);
        if (! $team) {
            throw new \DomainException('Team not found');
        }

        $team->makeHidden(['created_at', 'updated_at']);
        $team->roles->makeHidden(['guard_name', 'created_at', 'updated_at', 'team_id']);

        return $team;
    }

    public function validate(array $data, array $actions): void
    {
        $rule = [];
        foreach ($actions as $action) {
            if (! isset($this->rules[$action])) {
                throw new \Exception("Validation rules for action '{$action}' not defined");
            }

            $rule = array_merge($rule, $this->rules[$action]);
        }

        $validator = Validator::make($data, $rule);
        if ($validator->fails()) {
            throw new \Exception('Validation failed: '.implode(', ', $validator->errors()->all()));
        }
    }
}
