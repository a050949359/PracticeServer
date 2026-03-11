<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\RoleService;
use Database\Seeders\GenUserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RoleService $roleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleService = new RoleService();
        
        // 運行權限相關的 seeder
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_can_get_all_roles()
    {
        $result = $this->roleService->getAllRoles();
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertIsArray($data['data']);
        $this->assertGreaterThan(0, count($data['data']));
    }

    /** @test */
    public function test_can_get_role_data()
    {
        $role = Role::first();
        
        $result = $this->roleService->getRoleData($role->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertEquals($role->id, $data['data']['id']);
        $this->assertEquals($role->name, $data['data']['name']);
    }

    /** @test */
    public function test_returns_404_for_nonexistent_role()
    {
        $result = $this->roleService->getRoleData(99999);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Role not found', $data['message']);
    }

    /** @test */
    public function test_can_create_role()
    {
        $team = Team::first();
        $roleData = [
            'name' => 'new-test-role',
            'team_id' => $team->id,
            'is_leader' => false
        ];
        
        $result = $this->roleService->createRole($roleData);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Role created successfully', $data['message']);
        $this->assertEquals('new-test-role', $data['data']['name']);
        $this->assertEquals($team->id, $data['data']['team_id']);
        
        // 驗證角色確實被創建
        $role = Role::where('name', 'new-test-role')->where('team_id', $team->id)->first();
        $this->assertNotNull($role);
    }

    /** @test */
    public function test_cannot_create_role_with_invalid_team()
    {
        $roleData = [
            'name' => 'test-role',
            'team_id' => 99999,
            'is_leader' => false
        ];
        
        $result = $this->roleService->createRole($roleData);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Team not found', $data['message']);
    }

    /** @test */
    public function test_can_update_role()
    {
        $role = Role::first();
        $updateData = [
            'name' => 'updated-role-name',
            'is_leader' => true
        ];
        
        $result = $this->roleService->updateRole($role->id, $updateData);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Role updated successfully', $data['message']);
        $this->assertEquals('updated-role-name', $data['data']['name']);
        $this->assertEquals(1, $data['data']['is_leader']);
        
        // 驗證數據庫中的數據確實被更新
        $role->refresh();
        $this->assertEquals('updated-role-name', $role->name);
        $this->assertTrue($role->is_leader);
    }

    /** @test */
    public function test_can_delete_role_without_users()
    {
        $team = Team::first();
        $role = Role::create([
            'name' => 'deletable-role',
            'team_id' => $team->id,
            'is_leader' => false
        ]);
        
        $result = $this->roleService->deleteRole($role->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Role deleted successfully', $data['message']);
        
        // 驗證角色確實被刪除
        $this->assertNull(Role::find($role->id));
    }

    /** @test */
    public function test_cannot_delete_role_with_users()
    {
        $role = Role::whereHas('users')->first();
        
        if (!$role) {
            $this->markTestSkipped('No roles with users found in test data');
        }
        
        $result = $this->roleService->deleteRole($role->id);
        $response = $result->getResponse();
        
        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Cannot delete role with existing users', $data['message']);
        
        // 驗證角色沒有被刪除
        $this->assertNotNull(Role::find($role->id));
    }

    /** @test */
    public function test_can_assign_permissions_to_role()
    {
        $role = Role::first();
        $permissions = Permission::take(2)->pluck('id')->toArray();
        
        $result = $this->roleService->assignPermissions($role->id, $permissions);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Permissions assigned successfully', $data['message']);
        $this->assertArrayHasKey('role', $data['data']);
        $this->assertArrayHasKey('permissions', $data['data']);
        $this->assertCount(2, $data['data']['permissions']);
        
        // 驗證權限確實被分配
        $role->refresh();
        $this->assertCount(2, $role->permissions);
    }

    /** @test */
    public function test_cannot_assign_nonexistent_permissions()
    {
        $role = Role::first();
        $permissions = [99999, 99998];
        
        $result = $this->roleService->assignPermissions($role->id, $permissions);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('One or more permissions not found', $data['message']);
    }

    /** @test */
    public function test_can_get_role_permissions()
    {
        $role = Role::first();
        
        $result = $this->roleService->getRolePermissions($role->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertArrayHasKey('role', $data['data']);
        $this->assertArrayHasKey('permissions', $data['data']);
        $this->assertEquals($role->id, $data['data']['role']['id']);
    }

    /** @test */
    public function test_can_get_team_roles()
    {
        $team = Team::first();
        
        $result = $this->roleService->getTeamRoles($team->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertArrayHasKey('team', $data['data']);
        $this->assertArrayHasKey('roles', $data['data']);
        $this->assertEquals($team->id, $data['data']['team']['id']);
        $this->assertIsArray($data['data']['roles']);
    }

    /** @test */
    public function test_returns_404_for_team_roles_of_nonexistent_team()
    {
        $result = $this->roleService->getTeamRoles(99999);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Team not found', $data['message']);
    }

    /** @test */
    public function test_sync_permissions_replaces_existing()
    {
        $role = Role::first();
        $firstPermissions = Permission::take(2)->pluck('id')->toArray();
        $secondPermissions = Permission::skip(2)->take(2)->pluck('id')->toArray();
        
        // 先分配第一組權限
        $this->roleService->assignPermissions($role->id, $firstPermissions);
        $role->refresh();
        $this->assertCount(2, $role->permissions);
        
        // 分配第二組權限（應該替換原有的）
        $result = $this->roleService->assignPermissions($role->id, $secondPermissions);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // 驗證權限被替換
        $role->refresh();
        $this->assertCount(2, $role->permissions);
        $this->assertEquals($secondPermissions, $role->permissions->pluck('id')->sort()->values()->toArray());
    }

    /** @test */
    public function test_fails_update_nonexistent_role()
    {
        $result = $this->roleService->updateRole(99999, ['name' => 'new-name']);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Role not found', $data['message']);
    }
}