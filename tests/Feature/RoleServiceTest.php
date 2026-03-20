<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Repository\Permission\RoleRepository;
use App\Repository\Permission\TeamRepository;
use Database\Seeders\GenUserPermission;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RoleRepository $roleRepository;

    protected TeamRepository $teamRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleRepository = app(RoleRepository::class);
        $this->teamRepository = app(TeamRepository::class);
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_can_get_all_roles(): void
    {
        $roles = $this->roleRepository->getAllRoles();

        $this->assertGreaterThan(0, $roles->count());
    }

    /** @test */
    public function test_can_get_role_data(): void
    {
        $role = Role::first();

        $this->expectException(RelationNotFoundException::class);

        $this->roleRepository->getRoleData($role->id);
    }

    /** @test */
    public function test_returns_null_for_nonexistent_role(): void
    {
        $this->assertNull($this->roleRepository->getRoleData(99999));
    }

    /** @test */
    public function test_can_create_role(): void
    {
        $team = Team::first();
        $roleData = [
            'name' => 'new-test-role',
            'team_id' => $team->id,
            'is_leader' => false,
        ];

        $created = $this->roleRepository->createRole($team->id, $roleData);

        $this->assertSame('new-test-role', $created->name);
        $this->assertSame($team->id, $created->team_id);
        $this->assertDatabaseHas('roles', [
            'name' => 'new-test-role',
            'team_id' => $team->id,
        ]);
    }

    /** @test */
    public function test_cannot_create_role_with_invalid_team(): void
    {
        $roleData = [
            'name' => 'test-role',
            'team_id' => 99999,
            'is_leader' => false,
        ];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Team not found');

        $this->roleRepository->createRole(99999, $roleData);
    }

    /** @test */
    public function test_can_update_role(): void
    {
        $role = Role::first();
        $updateData = [
            'name' => 'updated-role-name',
        ];

        $this->roleRepository->updateRole($role->id, $updateData);

        $role->refresh();
        $this->assertSame('updated-role-name', $role->name);
    }

    /** @test */
    public function test_can_delete_role_without_users(): void
    {
        $team = Team::first();
        $role = Role::create([
            'name' => 'deletable-role',
            'team_id' => $team->id,
            'is_leader' => false,
        ]);

        $this->roleRepository->deleteRole($role->id);

        $this->assertNull(Role::find($role->id));
    }

    /** @test */
    public function test_cannot_delete_role_with_users(): void
    {
        $role = Role::whereHas('users')->first();

        if (! $role) {
            $this->markTestSkipped('No roles with users found in test data');
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot delete role with existing users');

        $this->roleRepository->deleteRole($role->id);

        $this->assertNotNull(Role::find($role->id));
    }

    /** @test */
    public function test_can_assign_permissions_to_role(): void
    {
        $role = Role::first();
        $permissions = Permission::take(2)->pluck('id')->toArray();

        $this->roleRepository->assignPermissions($role->id, $permissions);

        $role->refresh();
        $this->assertCount(2, $role->permissions);
    }

    /** @test */
    public function test_cannot_assign_nonexistent_permissions(): void
    {
        $role = Role::first();
        $permissions = [99999, 99998];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('One or more permissions not found');

        $this->roleRepository->assignPermissions($role->id, $permissions);
    }

    /** @test */
    public function test_can_get_role_permissions(): void
    {
        $role = Role::first();

        $this->expectException(RelationNotFoundException::class);

        $this->roleRepository->getRoleData($role->id);
    }

    /** @test */
    public function test_can_get_team_roles(): void
    {
        $team = Team::first();

        $loadedTeam = $this->teamRepository->getTeamRoles($team->id);

        $this->assertSame($team->id, $loadedTeam->id);
        $this->assertIsIterable($loadedTeam->roles);
    }

    /** @test */
    public function test_returns_404_for_team_roles_of_nonexistent_team(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Team not found');

        $this->teamRepository->getTeamRoles(99999);
    }

    /** @test */
    public function test_sync_permissions_replaces_existing(): void
    {
        $role = Role::first();
        $firstPermissions = Permission::take(2)->pluck('id')->toArray();
        $secondPermissions = Permission::skip(2)->take(2)->pluck('id')->toArray();

        $this->roleRepository->assignPermissions($role->id, $firstPermissions);
        $role->refresh();
        $this->assertCount(2, $role->permissions);

        $this->roleRepository->assignPermissions($role->id, $secondPermissions);

        $role->refresh();
        $this->assertCount(2, $role->permissions);
        $this->assertEqualsCanonicalizing($secondPermissions, $role->permissions->pluck('id')->all());
    }

    /** @test */
    public function test_fails_update_nonexistent_role(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Role not found');

        $this->roleRepository->updateRole(99999, ['name' => 'new-name']);
    }
}
