<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\TeamService;
use App\Services\UserService;
use Database\Seeders\GenUserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $userService;
    protected TeamService $teamService;
    protected RoleService $roleService;
    protected PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = new UserService();
        $this->teamService = new TeamService();
        $this->roleService = new RoleService();
        $this->permissionService = new PermissionService();
        
        // 運行權限相關的 seeder
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_complete_user_team_role_workflow()
    {
        // 1. 創建新團隊
        $teamResult = $this->teamService->createTeam(['name' => 'Integration Test Team']);
        $this->assertEquals(200, $teamResult->getResponse()->getStatusCode());
        
        $team = Team::where('name', 'Integration Test Team')->first();
        $this->assertNotNull($team);

        // 2. 為團隊創建角色
        $roleData = [
            'name' => 'integration-manager',
            'team_id' => $team->id,
            'is_leader' => true
        ];
        $roleResult = $this->roleService->createRole($roleData);
        $this->assertEquals(200, $roleResult->getResponse()->getStatusCode());
        
        $role = Role::where('name', 'integration-manager')->where('team_id', $team->id)->first();
        $this->assertNotNull($role);

        // 3. 為角色分配權限
        $permissions = Permission::take(3)->pluck('id')->toArray();
        $permissionResult = $this->roleService->assignPermissions($role->id, $permissions);
        $this->assertEquals(200, $permissionResult->getResponse()->getStatusCode());

        // 4. 創建使用者並分配到團隊角色
        $userData = [
            'name' => 'Integration Test User',
            'email' => 'integration@test.com',
            'password' => bcrypt('password')
        ];
        $userResult = $this->userService->createUserWithTeamAndRole($userData, $team->id, $role->name);
        $this->assertEquals(200, $userResult->getResponse()->getStatusCode());
        
        $user = User::where('email', 'integration@test.com')->first();
        $this->assertNotNull($user);

        // 5. 驗證使用者權限
        $checkResult = $this->permissionService->checkUserPermissions(
            $user->id, 
            Permission::take(3)->pluck('name')->toArray()
        );
        $checkData = json_decode($checkResult->getResponse()->getContent(), true);
        
        // 使用者應該有被分配的權限
        foreach ($checkData['data']['permissions'] as $permission => $hasPermission) {
            $this->assertTrue($hasPermission, "User should have permission: {$permission}");
        }
    }

    /** @test */
    public function test_user_role_update_workflow()
    {
        $user = User::first();
        $team = Team::first();
        
        // 取得團隊的兩個不同角色
        $memberRole = $team->roles()->where('name', 'member')->first();
        $leaderRole = $team->roles()->where('name', 'leader')->first();
        
        if (!$memberRole || !$leaderRole) {
            $this->markTestSkipped('Required roles not found in test data');
        }

        // 1. 分配member角色
        $assignResult = $this->userService->assignUserToTeamRole($user->id, $team->id, $memberRole->name);
        $assignData = json_decode($assignResult->getResponse()->getContent(), true);
        $this->assertEquals(200, $assignResult->getResponse()->getStatusCode());
        
        // 驗證角色分配
        setPermissionsTeamId($team);
        $user->refresh()->load('roles');
        $this->assertTrue($user->hasRole($memberRole->name));

        // 2. 更新為leader角色
        $updateResult = $this->userService->assignUserToTeamRole($user->id, $team->id, $leaderRole->name);
        $updateData = json_decode($updateResult->getResponse()->getContent(), true);
        $this->assertEquals(200, $updateResult->getResponse()->getStatusCode());
        $this->assertEquals('updated', $updateData['data']['action']);
        
        // 驗證角色更新
        setPermissionsTeamId($team);
        $user->refresh()->load('roles');
        $this->assertTrue($user->hasRole($leaderRole->name));
        $this->assertFalse($user->hasRole($memberRole->name));
    }

    /** @test */
    public function test_multi_team_user_scenario()
    {
        $user = User::first();
        $firstTeam = Team::where('name', 'First Team')->first();
        $visitorTeam = Team::where('name', 'Visitor Team')->first();
        
        if (!$firstTeam || !$visitorTeam) {
            $this->markTestSkipped('Required teams not found in test data');
        }

        $memberRole = $firstTeam->roles()->where('name', 'member')->first();
        $visitorRole = $visitorTeam->roles()->where('name', 'visitor')->first();

        // 1. 在第一個團隊分配member角色
        $firstAssign = $this->userService->assignUserToTeamRole($user->id, $firstTeam->id, $memberRole->name);
        $this->assertEquals(200, $firstAssign->getResponse()->getStatusCode());

        // 2. 在第二個團隊分配visitor角色
        $secondAssign = $this->userService->assignUserToTeamRole($user->id, $visitorTeam->id, $visitorRole->name);
        $this->assertEquals(200, $secondAssign->getResponse()->getStatusCode());

        // 3. 檢查使用者在所有團隊的角色
        $rolesResult = $this->userService->getUserTeamRoles($user->id);
        $rolesData = json_decode($rolesResult->getResponse()->getContent(), true);
        
        $this->assertEquals(200, $rolesResult->getResponse()->getStatusCode());
        $this->assertArrayHasKey('team_roles', $rolesData['data']);
        
        // 應該有兩個團隊的角色記錄
        $teamRoles = $rolesData['data']['team_roles'];
        $this->assertGreaterThanOrEqual(2, count($teamRoles));
    }

    /** @test */
    public function test_permission_inheritance_through_roles()
    {
        $team = Team::first();
        $role = $team->roles()->first();
        
        // 取得角色的權限
        $rolePermResult = $this->roleService->getRolePermissions($role->id);
        $rolePermData = json_decode($rolePermResult->getResponse()->getContent(), true);
        $rolePermissions = collect($rolePermData['data']['permissions'])->pluck('name')->toArray();

        // 找一個有這個角色的使用者
        setPermissionsTeamId($team);
        $user = $role->users()->first();
        
        if (!$user) {
            $this->markTestSkipped('No users found with the test role');
        }

        // 檢查使用者是否繼承了角色的權限
        $userPermResult = $this->permissionService->checkUserPermissions($user->id, $rolePermissions);
        $userPermData = json_decode($userPermResult->getResponse()->getContent(), true);

        // 使用者應該有角色的所有權限
        foreach ($userPermData['data']['permissions'] as $permission => $hasPermission) {
            if (in_array($permission, $rolePermissions)) {
                $this->assertTrue($hasPermission, "User should inherit permission from role: {$permission}");
            }
        }
    }

    /** @test */
    public function test_team_deletion_cascade()
    {
        // 創建測試團隊、角色和使用者
        $team = Team::create(['name' => 'Cascade Test Team']);
        $role = Role::create([
            'name' => 'cascade-role',
            'team_id' => $team->id,
            'is_leader' => false
        ]);
        
        $user = User::factory()->create();
        
        // 為使用者分配角色
        setPermissionsTeamId($team);
        $user->assignRole($role);
        
        // 驗證關聯已建立
        $this->assertTrue($user->hasRole($role->name));
        
        // 嘗試刪除有使用者的團隊（應該失敗）
        $deleteResult = $this->teamService->deleteTeam($team->id);
        $this->assertEquals(409, $deleteResult->getResponse()->getStatusCode());
        
        // 移除使用者的角色
        $user->removeRole($role);
        
        // 現在刪除團隊應該成功
        $deleteResult = $this->teamService->deleteTeam($team->id);
        $this->assertEquals(200, $deleteResult->getResponse()->getStatusCode());
        
        // 驗證團隊和角色都被刪除
        $this->assertNull(Team::find($team->id));
        $this->assertNull(Role::find($role->id));
    }

    /** @test */
    public function test_single_role_per_team_constraint()
    {
        $user = User::first();
        $team = Team::first();
        $roles = $team->roles()->take(2)->get();
        
        if (count($roles) < 2) {
            $this->markTestSkipped('Need at least 2 roles in team for this test');
        }

        // 分配第一個角色
        $firstAssign = $this->userService->assignUserToTeamRole($user->id, $team->id, $roles[0]->name);
        $this->assertEquals(200, $firstAssign->getResponse()->getStatusCode());

        // 分配第二個角色（應該替換第一個）
        $secondAssign = $this->userService->assignUserToTeamRole($user->id, $team->id, $roles[1]->name);
        $secondData = json_decode($secondAssign->getResponse()->getContent(), true);
        $this->assertEquals(200, $secondAssign->getResponse()->getStatusCode());
        $this->assertEquals('updated', $secondData['data']['action']);

        // 驗證只有第二個角色
        setPermissionsTeamId($team);
        $user->refresh()->load('roles');
        $this->assertTrue($user->hasRole($roles[1]->name));
        $this->assertFalse($user->hasRole($roles[0]->name));
        
        // 驗證使用者在該團隊只有一個角色
        $userRoles = $user->roles()->where('team_id', $team->id)->get();
        $this->assertCount(1, $userRoles);
    }

    /** @test */
    public function test_leader_vs_member_permissions()
    {
        $team = Team::where('name', 'First Team')->first();
        $leaderRole = $team->roles()->where('is_leader', true)->first();
        $memberRole = $team->roles()->where('is_leader', false)->first();
        
        if (!$leaderRole || !$memberRole) {
            $this->markTestSkipped('Required leader and member roles not found');
        }

        // 創建測試使用者
        $leader = User::factory()->create(['email' => 'leader.test@example.com']);
        $member = User::factory()->create(['email' => 'member.test@example.com']);
        
        // 分配角色
        $this->userService->assignUserToTeamRole($leader->id, $team->id, $leaderRole->name);
        $this->userService->assignUserToTeamRole($member->id, $team->id, $memberRole->name);
        
        // 檢查權限差異
        $leaderPermResult = $this->permissionService->checkUserPermissions(
            $leader->id, 
            ['user.manage.team', 'user.view', 'user.create']
        );
        $memberPermResult = $this->permissionService->checkUserPermissions(
            $member->id, 
            ['user.manage.team', 'user.view', 'user.create']
        );
        
        $leaderPermissions = json_decode($leaderPermResult->getResponse()->getContent(), true)['data']['permissions'];
        $memberPermissions = json_decode($memberPermResult->getResponse()->getContent(), true)['data']['permissions'];
        
        // Leader 應該有團隊管理權限，Member 不應該有
        if (isset($leaderPermissions['user.manage.team']) && isset($memberPermissions['user.manage.team'])) {
            $this->assertTrue($leaderPermissions['user.manage.team'], 'Leader should have team management permission');
            $this->assertFalse($memberPermissions['user.manage.team'], 'Member should not have team management permission');
        }
    }
}