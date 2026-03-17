<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\Permission\ManagerService;
use Database\Seeders\GenUserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ManagerService $managerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GenUserPermission::class);
        $this->managerService = app(ManagerService::class);
    }

    public function test_can_create_team_with_initial_role(): void
    {
        $response = $this->managerService->createTeam([
            'name' => 'Ops Team',
            'role' => [
                'name' => 'ops-lead',
                'is_leader' => true,
            ],
        ])->getResponse();

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Team created successfully', $payload['message']);
        $this->assertSame('Ops Team', $payload['data']['name']);
        $this->assertDatabaseHas('teams', ['name' => 'Ops Team']);

        $team = Team::where('name', 'Ops Team')->firstOrFail();
        $this->assertDatabaseHas('roles', [
            'name' => 'ops-lead',
            'team_id' => $team->id,
            'is_leader' => true,
        ]);
    }

    public function test_can_update_team_role_permissions(): void
    {
        $role = Role::where('name', 'member')->firstOrFail();
        $permissionIds = Permission::query()->take(2)->pluck('id')->all();

        $response = $this->managerService->updateTeamRolePermissions($role->id, $permissionIds)->getResponse();

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Role permissions updated successfully', $payload['message']);

        $updatedPermissionIds = Role::findOrFail($role->id)->permissions->pluck('id')->all();
        $this->assertEqualsCanonicalizing($permissionIds, $updatedPermissionIds);
    }

    public function test_can_remove_team_member_without_deleting_user(): void
    {
        $user = User::where('email', 'editor@example.com')->firstOrFail();
        $team = Team::where('name', 'First Team')->firstOrFail();

        $response = $this->managerService->removeTeamMember($user->id, $team->id)->getResponse();

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Team member removed successfully', $payload['message']);

        setPermissionsTeamId($team->id);

        $this->assertNotNull(User::find($user->id));
        $this->assertSame(0, $user->fresh()->roles()->where('team_id', $team->id)->count());
    }
}
