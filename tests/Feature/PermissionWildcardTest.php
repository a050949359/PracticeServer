<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\PermissionService;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Database\Seeders\GenUserPermission;

class PermissionWildcardTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 執行權限 seeder
        $this->seed(GenUserPermission::class);
        
        $this->permissionService = new PermissionService();
    }

    /** @test */
    public function test_search_permissions_by_keyword()
    {
        // 搜尋包含 "user" 關鍵字的權限
        $result = $this->permissionService->searchPermissionsByKeyword('user');
        
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertGreaterThan(0, $data['count']);
        $this->assertStringContainsString('user', $data['keyword']);
        
        // 檢查找到的權限是否都包含關鍵字
        foreach ($data['found_permissions'] as $permission) {
            $this->assertStringContainsString('user', $permission['name']);
        }
    }

    /** @test */
    public function test_search_permissions_by_wildcard()
    {
        // 搜尋所有 user.* 權限
        $result = $this->permissionService->searchPermissionsByWildcard('user.*');
        
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertGreaterThan(0, $data['count']);
        
        // 檢查找到的權限是否都以 user. 開頭
        foreach ($data['matched_permissions'] as $permission) {
            $this->assertStringStartsWith('user.', $permission['name']);
        }
    }

    /** @test */
    public function test_search_permissions_with_view_action()
    {
        // 搜尋所有 *.view 權限
        $result = $this->permissionService->searchPermissionsByWildcard('*.view');
        
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        
        // 檢查找到的權限是否都以 .view 結尾
        foreach ($data['matched_permissions'] as $permission) {
            $this->assertStringEndsWith('.view', $permission['name']);
        }
    }

    /** @test */
    public function test_advanced_permission_search()
    {
        // 高級搜尋：搜尋 user 模組的權限
        $result = $this->permissionService->searchPermissionsAdvanced([
            'module' => 'user'
        ]);
        
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertGreaterThan(0, $data['total_found']);
        
        // 檢查按模組分組的結果
        $this->assertArrayHasKey('grouped_by_module', $data);
        $this->assertArrayHasKey('user', $data['grouped_by_module']);
    }

    /** @test */
    public function test_advanced_search_with_action_filter()
    {
        // 搜尋所有 "view" 動作的權限
        $result = $this->permissionService->searchPermissionsAdvanced([
            'action' => 'view'
        ]);
        
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        
        // 檢查找到的權限是否都以 .view 結尾
        foreach ($data['permissions'] as $permission) {
            $this->assertStringEndsWith('.view', $permission['name']);
        }
    }

    /** @test */
    public function test_check_user_wildcard_permissions()
    {
        $user = User::where('email', 'a050949359@gmail.com')->first();
        $this->assertNotNull($user); // Admin user from seeder
        
        // 檢查 Admin 使用者的 wildcard 權限
        $result = $this->permissionService->checkUserWildcardPermissions($user->id, [
            'user.*',
            '*.view',
            'user.manage.*'
        ]);
        
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        
        // Admin 應該有所有權限
        $this->assertTrue($data['wildcard_permissions']['user.*']);
        $this->assertTrue($data['wildcard_permissions']['*.view']);
        $this->assertTrue($data['wildcard_permissions']['user.manage.*']);
    }

    /** @test */
    public function test_check_limited_user_wildcard_permissions()
    {
        $visitor = User::where('email', 'visitor@example.com')->first();
        $this->assertNotNull($visitor); // Visitor user from seeder
        
        // 檢查 Visitor 使用者的 wildcard 權限
        $result = $this->permissionService->checkUserWildcardPermissions($visitor->id, [
            'user.*',
            '*.view',
            'user.create'
        ]);
        
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        
        // Visitor 只能 view，不能有其他權限
        $this->assertFalse($data['wildcard_permissions']['user.*']);
        $this->assertTrue($data['wildcard_permissions']['*.view']); // 包含 user.view
        $this->assertFalse($data['wildcard_permissions']['user.create']);
    }

    /** @test */
    public function test_get_permission_patterns()
    {
        $result = $this->permissionService->getPermissionPatterns();
        
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        
        $this->assertArrayHasKey('available_modules', $data);
        $this->assertArrayHasKey('available_actions', $data);
        $this->assertArrayHasKey('suggested_patterns', $data);
        $this->assertArrayHasKey('example_patterns', $data);
        
        // 檢查是否包含預期的模組和動作
        $this->assertContains('user', $data['available_modules']);
        $this->assertContains('view', $data['available_actions']);
        $this->assertContains('user.*', $data['suggested_patterns']);
    }

    /** @test */
    public function test_combined_keyword_and_wildcard_search()
    {
        // 結合關鍵字和 wildcard 模式搜尋
        $result = $this->permissionService->searchPermissionsAdvanced([
            'keyword' => 'manage',
            'pattern' => 'user.*'
        ]);
        
        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        
        // 檢查結果是否同時滿足關鍵字和模式條件
        foreach ($data['permissions'] as $permission) {
            $this->assertStringContainsString('manage', $permission['name']);
            $this->assertStringStartsWith('user.', $permission['name']);
        }
    }
}