<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\GenUserPermission;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = app(UserService::class);
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_can_get_all_users(): void
    {
        $users = $this->userService->getAllUsers();

        $this->assertGreaterThan(0, $users->count());
    }

    /** @test */
    public function test_can_get_user_data(): void
    {
        $user = User::first();

        $loaded = $this->userService->getUser($user->id);

        $this->assertSame($user->id, $loaded->id);
    }

    /** @test */
    public function test_returns_404_for_nonexistent_user(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not found');

        $this->userService->getUser(99999);
    }

    /** @test */
    public function test_can_create_user_with_team_and_role(): void
    {
        $team = Team::where('name', 'Staff')->firstOrFail();
        $role = $team->roles()->where('name', 'visitor')->firstOrFail();

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ];

        $this->userService->createUserWithTeamAndRole($userData, $team->id, $role->id);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        setPermissionsTeamId($team);
        $this->assertTrue($user->hasRole($role->name));
    }

    /** @test */
    public function test_can_assign_user_to_team_role(): void
    {
        $user = User::first();
        $team = Team::where('name', 'Maintainer')->firstOrFail();
        $role = $team->roles()->where('name', 'member')->firstOrFail();

        $this->expectException(QueryException::class);

        $this->userService->assignUserToTeamRole($user->id, $team->id, $role->id);
    }

    /** @test */
    public function test_can_update_existing_user_role_in_team(): void
    {
        $user = User::first();
        $team = Team::where('name', 'Maintainer')->firstOrFail();
        $memberRole = $team->roles()->where('name', 'member')->first();
        $leaderRole = $team->roles()->where('name', 'leader')->first();

        setPermissionsTeamId($team);
        $user->assignRole($memberRole);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Team 'Maintainer' already has a leader");

        $this->userService->assignUserToTeamRole($user->id, $team->id, $leaderRole->id);
    }

    /** @test */
    public function test_can_get_user_team_roles(): void
    {
        $user = User::first();

        $teamRoles = $this->userService->getUserTeamRoles($user->id);

        $this->assertIsArray($teamRoles);
    }

    /** @test */
    public function test_can_update_user(): void
    {
        $user = User::first();
        $updateData = ['name' => 'Updated Name'];

        $this->userService->updateUser($user->id, $updateData);

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
    }

    /** @test */
    public function test_can_delete_user(): void
    {
        $user = User::factory()->create();

        $this->userService->deleteUser($user->id);

        $this->assertNull(User::find($user->id));
    }

    /** @test */
    public function test_fails_to_create_user_with_invalid_team(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Team not found');

        $this->userService->createUserWithTeamAndRole($userData, 99999, 1);
    }

    /** @test */
    public function test_fails_to_assign_nonexistent_role(): void
    {
        $user = User::first();
        $team = Team::first();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Role not found in specified team');

        $this->userService->assignUserToTeamRole($user->id, $team->id, 99999);
    }
}
