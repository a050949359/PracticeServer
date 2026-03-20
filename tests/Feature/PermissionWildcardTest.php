<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repository\Permission\PermissionRepository;
use Database\Seeders\GenUserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionWildcardTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionRepository $permissionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GenUserPermission::class);
        $this->permissionRepository = app(PermissionRepository::class);
    }

    /** @test */
    public function test_search_permissions_by_keyword(): void
    {
        $permissions = $this->permissionRepository->searchPermissionsByKeyword('user');

        $this->assertGreaterThan(0, $permissions->count());

        foreach ($permissions as $permission) {
            $this->assertStringContainsString('user', $permission->name);
        }
    }

    /** @test */
    public function test_search_permissions_by_wildcard(): void
    {
        $permissions = $this->permissionRepository->searchPermissionsByWildcard('user.*');

        $this->assertGreaterThan(0, $permissions->count());

        foreach ($permissions as $permission) {
            $this->assertStringStartsWith('user.', $permission->name);
        }
    }

    /** @test */
    public function test_search_permissions_with_view_action(): void
    {
        $permissions = $this->permissionRepository->searchPermissionsByWildcard('*.view');

        foreach ($permissions as $permission) {
            $this->assertStringEndsWith('.view', $permission->name);
        }
    }

    /** @test */
    public function test_advanced_permission_search(): void
    {
        $grouped = $this->permissionRepository->searchPermissionsAdvanced([
            'module' => 'user',
        ]);

        $this->assertGreaterThan(0, $grouped->flatten(1)->count());
        $this->assertArrayHasKey('user', $grouped->toArray());
    }

    /** @test */
    public function test_advanced_search_with_action_filter(): void
    {
        $grouped = $this->permissionRepository->searchPermissionsAdvanced([
            'action' => 'view',
        ]);

        foreach ($grouped->flatten(1) as $permission) {
            $this->assertStringEndsWith('.view', $permission->name);
        }
    }

    /** @test */
    public function test_check_user_wildcard_permissions(): void
    {
        $user = User::where('email', 'a050949359@gmail.com')->first();

        $this->assertNotNull($user);

        $result = $this->permissionRepository->checkUserPermissionPatterns($user->id, [
            'user.*',
            '*.view',
            'user.manage.*',
            'user.view',
        ]);

        $this->assertFalse($result['user.*']);
        $this->assertFalse($result['*.view']);
        $this->assertFalse($result['user.manage.*']);
        $this->assertFalse($result['user.view']);
    }

    /** @test */
    public function test_check_limited_user_wildcard_permissions(): void
    {
        $visitor = User::where('email', 'viewer@example.com')->first();

        $this->assertNotNull($visitor);

        $result = $this->permissionRepository->checkUserPermissionPatterns($visitor->id, [
            'user.*',
            '*.view',
            'user.create',
            'user.view',
        ]);

        $this->assertFalse($result['user.*']);
        $this->assertFalse($result['*.view']);
        $this->assertFalse($result['user.create']);
        $this->assertTrue($result['user.view']);
    }

    /** @test */
    public function test_get_permission_patterns(): void
    {
        $data = $this->permissionRepository->getPermissionPatterns();

        $this->assertArrayHasKey('available_modules', $data);
        $this->assertArrayHasKey('available_actions', $data);
        $this->assertArrayHasKey('suggested_patterns', $data);
        $this->assertArrayHasKey('example_patterns', $data);

        $this->assertContains('user', $data['available_modules']);
        $this->assertContains('view', $data['available_actions']);
        $this->assertContains('user.*', $data['suggested_patterns']);
    }

    /** @test */
    public function test_combined_keyword_and_wildcard_search(): void
    {
        $grouped = $this->permissionRepository->searchPermissionsAdvanced([
            'keyword' => 'manage',
            'pattern' => 'user.*',
        ]);

        foreach ($grouped->flatten(1) as $permission) {
            $this->assertStringContainsString('manage', $permission->name);
            $this->assertStringStartsWith('user.', $permission->name);
        }
    }
}
