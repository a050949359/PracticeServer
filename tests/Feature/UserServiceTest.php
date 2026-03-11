<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\GenUserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = new UserService();
        
        // 運行權限相關的 seeder
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_can_get_all_users()
    {
        $result = $this->userService->getAllUsers();
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertIsArray($data['data']);
    }

    /** @test */
    public function test_can_get_user_data()
    {
        $user = User::first();
        
        $result = $this->userService->getUserData($user->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertEquals($user->id, $data['data']['id']);
    }

    /** @test */
    public function test_returns_404_for_nonexistent_user()
    {
        $result = $this->userService->getUserData(99999);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('User not found', $data['message']);
    }

    /** @test */
    public function test_can_create_user_with_team_and_role()
    {
        $team = Team::first();
        $role = $team->roles()->first();
        
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ];
        
        $result = $this->userService->createUserWithTeamAndRole($userData, $team->id, $role->name);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test User', $data['data']['user']);
        $this->assertEquals($team->name, $data['data']['team']);
        $this->assertEquals($role->name, $data['data']['role']);
        
        // 驗證用戶確實被創建並分配角色
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        
        setPermissionsTeamId($team);
        $this->assertTrue($user->hasRole($role->name));
    }

    /** @test */
    public function test_can_assign_user_to_team_role()
    {
        $user = User::first();
        $team = Team::where('name', 'First Team')->first();
        $role = $team->roles()->where('name', 'member')->first();
        
        $result = $this->userService->assignUserToTeamRole($user->id, $team->id, $role->name);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('action', $data['data']);
        $this->assertContains($data['data']['action'], ['assigned', 'updated']);
    }

    /** @test */
    public function test_can_update_existing_user_role_in_team()
    {
        $user = User::first();
        $team = Team::where('name', 'First Team')->first();
        $memberRole = $team->roles()->where('name', 'member')->first();
        $leaderRole = $team->roles()->where('name', 'leader')->first();
        
        // 先分配 member 角色
        setPermissionsTeamId($team);
        $user->assignRole($memberRole);
        
        // 然後更新為 leader 角色
        $result = $this->userService->assignUserToTeamRole($user->id, $team->id, $leaderRole->name);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('updated', $data['data']['action']);
        
        // 驗證角色確實被更新
        setPermissionsTeamId($team);
        $user->refresh()->load('roles');
        $this->assertTrue($user->hasRole($leaderRole->name));
        $this->assertFalse($user->hasRole($memberRole->name));
    }

    /** @test */
    public function test_can_get_user_team_roles()
    {
        $user = User::first();
        
        $result = $this->userService->getUserTeamRoles($user->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertArrayHasKey('user', $data['data']);
        $this->assertArrayHasKey('team_roles', $data['data']);
        $this->assertIsArray($data['data']['team_roles']);
    }

    /** @test */
    public function test_can_update_user()
    {
        $user = User::first();
        $updateData = ['name' => 'Updated Name'];
        
        $result = $this->userService->updateUser($user->id, $updateData);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Name', $data['data']['name']);
        
        // 驗證數據庫中的數據確實被更新
        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
    }

    /** @test */
    public function test_can_delete_user()
    {
        $user = User::factory()->create();
        
        $result = $this->userService->deleteUser($user->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // 驗證用戶確實被刪除
        $this->assertNull(User::find($user->id));
    }

    /** @test */
    public function test_fails_to_create_user_with_invalid_team()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ];
        
        $result = $this->userService->createUserWithTeamAndRole($userData, 99999, 'member');
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Team not found', $data['message']);
    }

    /** @test */
    public function test_fails_to_assign_nonexistent_role()
    {
        $user = User::first();
        $team = Team::first();
        
        $result = $this->userService->assignUserToTeamRole($user->id, $team->id, 'nonexistent-role');
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Role not found in specified team', $data['message']);
    }
}