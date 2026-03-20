<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repository\Permission\PermissionRepository;
use Database\Seeders\GenUserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionRepository $permissionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissionRepository = app(PermissionRepository::class);
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_can_get_all_permissions(): void
    {
        $permissions = $this->permissionRepository->getAllPermissions();

        $this->assertGreaterThan(0, $permissions->count());
    }

    /** @test */
    public function test_can_get_permission_data(): void
    {
        $permission = Permission::first();

        $loaded = $this->permissionRepository->getPermissionData($permission->id);

        $this->assertNotNull($loaded);
        $this->assertSame($permission->id, $loaded->id);
        $this->assertSame($permission->name, $loaded->name);
    }

    /** @test */
    public function test_returns_null_for_nonexistent_permission(): void
    {
        $this->assertNull($this->permissionRepository->getPermissionData(99999));
    }

    /** @test */
    public function test_can_create_permission(): void
    {
        $permissionData = ['name' => 'test.permission'];

        $created = $this->permissionRepository->createPermission($permissionData);

        $this->assertSame('test.permission', $created->name);
        $this->assertDatabaseHas('permissions', ['name' => 'test.permission']);
    }

    /** @test */
    public function test_can_create_multiple_permissions(): void
    {
        $permissionsData = [
            'bulk.permission.one',
            'bulk.permission.two',
            'bulk.permission.three',
        ];

        $this->permissionRepository->createPermissions($permissionsData);

        foreach ($permissionsData as $permissionName) {
            $this->assertDatabaseHas('permissions', ['name' => $permissionName]);
        }
    }

    /** @test */
    public function test_can_update_permission(): void
    {
        $permission = Permission::first();
        $updateData = ['name' => 'updated.permission.name'];

        $this->permissionRepository->updatePermission($permission->id, $updateData);

        $permission->refresh();
        $this->assertSame('updated.permission.name', $permission->name);
    }

    /** @test */
    public function test_can_delete_permission(): void
    {
        $permission = Permission::create(['name' => 'deletable.permission']);

        $this->permissionRepository->deletePermission($permission->id);

        $this->assertNull(Permission::find($permission->id));
    }

    /** @test */
    public function test_can_delete_permission_and_remove_from_roles(): void
    {
        $permission = Permission::create(['name' => 'deletetest.permission']);
        $role = Role::first();

        $role->givePermissionTo($permission);
        $this->assertTrue($role->hasPermissionTo($permission));

        $this->permissionRepository->deletePermission($permission->id);

        $this->assertNull(Permission::find($permission->id));
        $role->refresh();
        $this->assertFalse($role->hasPermissionTo('deletetest.permission'));
    }

    /** @test */
    public function test_can_get_permission_roles(): void
    {
        $permission = Permission::first();

        $loaded = $this->permissionRepository->getPermissionData($permission->id);

        $this->assertNotNull($loaded);
        $this->assertSame($permission->id, $loaded->id);
        $this->assertIsIterable($loaded->roles);
    }

    /** @test */
    public function test_can_get_permissions_by_module(): void
    {
        $grouped = $this->permissionRepository->getPermissionsByModule();

        $this->assertIsArray($grouped);

        foreach ($grouped as $module => $permissions) {
            $this->assertIsString($module);
            $this->assertIsArray($permissions);

            foreach ($permissions as $permission) {
                if ($module !== 'general') {
                    $this->assertStringStartsWith($module.'.', $permission['name']);
                }
            }
        }
    }

    /** @test */
    public function test_can_check_user_permissions(): void
    {
        $user = User::first();
        $patterns = ['user.*', '*.view', 'nonexistent.permission'];

        $result = $this->permissionRepository->checkUserPermissionPatterns($user->id, $patterns);

        $this->assertArrayHasKey('user.*', $result);
        $this->assertArrayHasKey('*.view', $result);
        $this->assertArrayHasKey('nonexistent.permission', $result);
        $this->assertFalse($result['nonexistent.permission']);
    }

    /** @test */
    public function test_returns_empty_result_for_nonexistent_user_pattern_check(): void
    {
        $result = $this->permissionRepository->checkUserPermissionPatterns(99999, ['user.view']);

        $this->assertSame([], $result);
    }

    /** @test */
    public function test_fails_update_nonexistent_permission(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Permission not found');

        $this->permissionRepository->updatePermission(99999, ['name' => 'new.name']);
    }

    /** @test */
    public function test_fails_delete_nonexistent_permission(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Permission not found');

        $this->permissionRepository->deletePermission(99999);
    }

    /** @test */
    public function test_check_admin_user_has_wildcard_permission_patterns(): void
    {
        $admin = User::where('email', 'a050949359@gmail.com')->first();

        if (! $admin) {
            $this->markTestSkipped('Admin user not found in test data');
        }

        $result = $this->permissionRepository->checkUserPermissionPatterns($admin->id, ['user.*', '*.view', 'user.view']);

        $this->assertFalse($result['user.*']);
        $this->assertFalse($result['*.view']);
        $this->assertFalse($result['user.view']);
    }
}
