<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PermissionService;
use Database\Seeders\GenUserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissionService = new PermissionService();
        
        // 運行權限相關的 seeder
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_can_get_all_permissions()
    {
        $result = $this->permissionService->getAllPermissions();
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertIsArray($data['data']);
        $this->assertGreaterThan(0, count($data['data']));
    }

    /** @test */
    public function test_can_get_permission_data()
    {
        $permission = Permission::first();
        
        $result = $this->permissionService->getPermissionData($permission->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertEquals($permission->id, $data['data']['id']);
        $this->assertEquals($permission->name, $data['data']['name']);
    }

    /** @test */
    public function test_returns_404_for_nonexistent_permission()
    {
        $result = $this->permissionService->getPermissionData(99999);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Permission not found', $data['message']);
    }

    /** @test */
    public function test_can_create_permission()
    {
        $permissionData = ['name' => 'test.permission'];
        
        $result = $this->permissionService->createPermission($permissionData);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Permission created successfully', $data['message']);
        $this->assertEquals('test.permission', $data['data']['name']);
        
        // 驗證權限確實被創建
        $permission = Permission::where('name', 'test.permission')->first();
        $this->assertNotNull($permission);
    }

    /** @test */
    public function test_can_create_multiple_permissions()
    {
        $permissionsData = [
            'bulk.permission.one',
            'bulk.permission.two',
            'bulk.permission.three'
        ];
        
        $result = $this->permissionService->createPermissions($permissionsData);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Permissions created successfully', $data['message']);
        $this->assertCount(3, $data['data']);
        
        // 驗證所有權限都被創建
        foreach ($permissionsData as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            $this->assertNotNull($permission);
        }
    }

    /** @test */
    public function test_can_update_permission()
    {
        $permission = Permission::first();
        $updateData = ['name' => 'updated.permission.name'];
        
        $result = $this->permissionService->updatePermission($permission->id, $updateData);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Permission updated successfully', $data['message']);
        $this->assertEquals('updated.permission.name', $data['data']['name']);
        
        // 驗證數據庫中的數據確實被更新
        $permission->refresh();
        $this->assertEquals('updated.permission.name', $permission->name);
    }

    /** @test */
    public function test_can_delete_permission()
    {
        $permission = Permission::create(['name' => 'deletable.permission']);
        
        $result = $this->permissionService->deletePermission($permission->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Permission deleted successfully', $data['message']);
        
        // 驗證權限確實被刪除
        $this->assertNull(Permission::find($permission->id));
    }

    /** @test */
    public function test_can_delete_permission_and_remove_from_roles()
    {
        $permission = Permission::create(['name' => 'deletetest.permission']);
        $role = Role::first();
        
        // 為角色分配這個權限
        $role->givePermissionTo($permission);
        $this->assertTrue($role->hasPermissionTo($permission));
        
        // 刪除權限
        $result = $this->permissionService->deletePermission($permission->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // 驗證權限被刪除且從角色中移除
        $this->assertNull(Permission::find($permission->id));
        $role->refresh();
        $this->assertFalse($role->hasPermissionTo('deletetest.permission'));
    }

    /** @test */
    public function test_can_get_permission_roles()
    {
        $permission = Permission::first();
        
        $result = $this->permissionService->getPermissionRoles($permission->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertArrayHasKey('permission', $data['data']);
        $this->assertArrayHasKey('roles', $data['data']);
        $this->assertEquals($permission->id, $data['data']['permission']['id']);
    }

    /** @test */
    public function test_can_get_permissions_by_module()
    {
        $result = $this->permissionService->getPermissionsByModule();
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertIsArray($data['data']);
        
        // 驗證是否按模組分組（如：user.view -> user 模組）
        foreach ($data['data'] as $module => $permissions) {
            $this->assertIsString($module);
            $this->assertIsArray($permissions);
            
            // 檢查該模組的權限是否都以該模組名稱開頭
            foreach ($permissions as $permission) {
                if ($module !== 'general') {
                    $this->assertStringStartsWith($module . '.', $permission['name']);
                }
            }
        }
    }

    /** @test */
    public function test_can_check_user_permissions()
    {
        $user = User::first();
        $permissionsToCheck = ['user.view', 'user.create', 'nonexistent.permission'];
        
        $result = $this->permissionService->checkUserPermissions($user->id, $permissionsToCheck);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertArrayHasKey('user', $data['data']);
        $this->assertArrayHasKey('permissions', $data['data']);
        $this->assertEquals($user->name, $data['data']['user']);
        
        // 檢查權限結果
        $permissions = $data['data']['permissions'];
        $this->assertArrayHasKey('user.view', $permissions);
        $this->assertArrayHasKey('user.create', $permissions);
        $this->assertArrayHasKey('nonexistent.permission', $permissions);
        
        // 檢查權限值
        $this->assertIsBool($permissions['user.view']);
        $this->assertIsBool($permissions['user.create']);
        $this->assertFalse($permissions['nonexistent.permission']);
    }

    /** @test */
    public function test_returns_404_for_user_permissions_of_nonexistent_user()
    {
        $result = $this->permissionService->checkUserPermissions(99999, ['user.view']);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('User not found', $data['message']);
    }

    /** @test */
    public function test_fails_update_nonexistent_permission()
    {
        $result = $this->permissionService->updatePermission(99999, ['name' => 'new.name']);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Permission not found', $data['message']);
    }

    /** @test */
    public function test_fails_delete_nonexistent_permission()
    {
        $result = $this->permissionService->deletePermission(99999);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Permission not found', $data['message']);
    }

    /** @test */
    public function test_check_admin_user_has_all_permissions()
    {
        $admin = User::where('email', 'a050949359@gmail.com')->first();
        
        if (!$admin) {
            $this->markTestSkipped('Admin user not found in test data');
        }
        
        $allPermissions = Permission::pluck('name')->toArray();
        
        $result = $this->permissionService->checkUserPermissions($admin->id, $allPermissions);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        
        // Admin 應該有所有權限
        foreach ($data['data']['permissions'] as $permission => $hasPermission) {
            $this->assertTrue($hasPermission, "Admin should have permission: {$permission}");
        }
    }
}