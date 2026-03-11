<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use DB;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * Get the team id for multi-tenancy
     */
    public function getTeamId()
    {
        // 如果使用者屬於多個團隊，返回第一個團隊的 ID
        // 在實際使用中，你可能需要根據當前上下文來決定使用哪個團隊
        return $this->teams()->first()?->id;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The teams that the user belongs to.
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'model_has_roles', 'model_id', 'team_id')
            ->where('model_type', static::class);
            // ->withTimestamps();
    }

    /**
     * Check if user belongs to a specific team.
     */
    public function belongsToTeam(Team|int $team): bool|null
    {
        if (is_int($team) && !Team::query()->where('id', $team)->exists()) {
            return null;
        }

        $teamId = $team instanceof Team ? $team->id : $team;
        return $this->teams()->where('team_id', $teamId)->exists();
    }
    
    /**
     * Get roles for a specific team.
     */
    public function getRolesForTeam(Team|int $team)
    {
        if (is_int($team) && !Team::query()->where('id', $team)->exists()) {
            return null;
        }

        setPermissionsTeamId($team);
        return $this->roles->first();
    }
}
