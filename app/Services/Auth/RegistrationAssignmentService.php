<?php

namespace App\Services\Auth;

use App\Models\Team;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RegistrationAssignmentService
{
    public function __construct(
        private VerificationEmailService $verificationEmailService,
    ) {}

    /**
     * @param  array{name:string,email:string,password:string}  $userAttributes
     * @return array{0: User, 1: Team, 2: Role}
     */
    public function createUserWithAssignment(array $userAttributes, string $context): array
    {
        $user = User::query()->create($userAttributes);

        [$teamName, $roleName] = $this->resolveInitialAssignment($context);

        $team = Team::query()->firstOrCreate(['name' => $teamName]);
        $role = Role::query()->firstOrCreate(
            [
                'team_id' => $team->id,
                'name' => $roleName,
                'guard_name' => config('auth.defaults.guard'),
            ],
            [
                'is_leader' => false,
            ],
        );

        setPermissionsTeamId($team->id);
        $user->assignRole($role);

        $this->verificationEmailService->sendTo($user);

        return [$user, $team, $role];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveInitialAssignment(string $context): array
    {
        return match ($context) {
            'staff_self_register', 'staff_invited_register' => ['Staff', 'staff'],
            'user_invited_register', 'user_self_register' => ['Users', 'user'],
            default => ['Users', 'user'],
        };
    }
}
