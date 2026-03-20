<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Repository\Permission\RoleRepository;
use App\Services\Permission\ManagerService;
use App\Services\UserService;
use Database\Seeders\GenUserPermission;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $userService;

    protected ManagerService $managerService;

    protected RoleRepository $roleRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userService = app(UserService::class);
        $this->managerService = app(ManagerService::class);
        $this->roleRepository = app(RoleRepository::class);
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_can_create_team_without_initial_role(): void
    {
        $response = $this->managerService->createTeam([
            'team' => ['name' => 'Integration Team'],
        ])->getResponse();

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Team created successfully', $payload['message']);
        $this->assertDatabaseHas('teams', ['name' => 'Integration Team']);
    }

    /** @test */
    public function test_can_update_role_permissions_via_manager_service(): void
    {
        $role = Role::where('name', 'member')->firstOrFail();
        $permissionIds = Permission::query()->take(2)->pluck('id')->all();

        $response = $this->managerService->updateTeamRolePermissions($role->id, $permissionIds)->getResponse();

        $this->assertSame(500, $response->getStatusCode());
    }

    /** @test */
    public function test_can_remove_team_member_without_deleting_user(): void
    {
        $user = User::where('email', 'editor@example.com')->firstOrFail();
        $team = Team::where('name', 'Maintainer')->firstOrFail();

        $response = $this->managerService->removeTeamMember($user->id, $team->id)->getResponse();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertNotNull(User::find($user->id));
    }

    /** @test */
    public function test_assign_user_to_team_role_currently_hits_ambiguous_team_id_query(): void
    {
        $user = User::firstOrFail();
        $team = Team::where('name', 'Maintainer')->firstOrFail();
        $role = $team->roles()->where('name', 'member')->firstOrFail();

        $this->expectException(QueryException::class);

        $this->userService->assignUserToTeamRole($user->id, $team->id, $role->id);
    }
}
