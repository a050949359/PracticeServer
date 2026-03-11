<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

class Team extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * Get all roles that belong to this team.
     */
    public function roles()
    {   
        return $this->hasMany(Role::class, 'team_id');
    }

    /**
     * Check if the team has any leaders.
     */
    public function hasLeader(): bool
    {
        // 直接檢查是否存在有用戶的 leader 角色
        return $this->roles()
            ->where('is_leader', true)
            ->whereHas('users')
            ->exists();
    }

    public function hasLeaderRole(): bool
    {
        return $this->roles()
            ->where('is_leader', true)
            ->exists();
    }

    /**
     * Get the leader of this team (assumes only one leader per team).
     * Returns the single leader user or null if no leader exists.
     */
    public function getLeader(): ?User
    {
        return $this->roles()
            ->where('is_leader', true)
            ->with('users')
            ->first()?->users
            ->first() ?? null;
    }

    public function hasMembers()
    {
        return $this->roles()
            ->where('is_leader', false)
            ->whereHas('users')
            ->exists();
    }

    public function getMembers()
    {
        return $this->roles()
            ->where('is_leader', false)
            ->with('users')
            ->first()?->users ?? null;
    }
}
