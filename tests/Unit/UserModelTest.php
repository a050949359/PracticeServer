<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\GenUserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_user_teams_relationship()
    {
        $user = User::first();
        $teams = $user->teams;
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $teams);
        
        // 檢查每個團隊是否為 Team 模型實例
        foreach ($teams as $team) {
            $this->assertInstanceOf(Team::class, $team);
        }
    }

    /** @test */
    public function test_get_team_id_method()
    {
        $user = User::first();
        $teamId = $user->getTeamId();
        
        if ($teamId) {
            $this->assertIsInt($teamId);
            $this->assertGreaterThan(0, $teamId);
            
            // 驗證這確實是使用者所屬的團隊
            $team = Team::find($teamId);
            $this->assertNotNull($team);
            $this->assertTrue($user->teams->contains($team));
        } else {
            // 如果沒有團隊，應該返回 null
            $this->assertNull($teamId);
        }
    }

    /** @test */
    public function test_belongs_to_team_method()
    {
        $user = User::first();
        $userTeams = $user->teams;
        
        if ($userTeams->isNotEmpty()) {
            $team = $userTeams->first();
            $this->assertTrue($user->belongsToTeam($team));
            
            // 測試不屬於的團隊
            $otherTeam = Team::where('id', '!=', $team->id)->first();
            if ($otherTeam && !$userTeams->contains($otherTeam)) {
                $this->assertFalse($user->belongsToTeam($otherTeam));
            }
        } else {
            $this->markTestSkipped('User has no teams assigned');
        }
    }

    /** @test */
    public function test_get_roles_for_team_method()
    {
        $user = User::first();
        $team = $user->teams->first();
        
        if (!$team) {
            $this->markTestSkipped('User has no teams assigned');
        }
        
        $roles = $user->getRolesForTeam($team);
        
        // 這應該返回一個查詢建構器
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $roles);
        
        // 執行查詢並檢查結果
        $roleResults = $roles->get();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $roleResults);
        
        // 檢查返回的角色是否都屬於指定團隊
        foreach ($roleResults as $role) {
            $this->assertEquals($team->id, $role->team_id);
        }
    }

    /** @test */
    public function test_teams_with_roles_method()
    {
        $user = User::first();
        $teamsWithRoles = $user->teamsWithRoles();
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $teamsWithRoles);
        
        // 檢查結構
        foreach ($teamsWithRoles as $teamData) {
            $this->assertIsArray($teamData);
            
            // 每個元素應該是一個以團隊名稱為鍵的陣列
            foreach ($teamData as $teamName => $roleInfo) {
                $this->assertIsString($teamName);
                $this->assertIsArray($roleInfo);
                
                // 檢查必要的鍵
                $this->assertArrayHasKey('team_id', $roleInfo);
                $this->assertArrayHasKey('role_id', $roleInfo);
                $this->assertArrayHasKey('role_name', $roleInfo);
                $this->assertArrayHasKey('is_leader', $roleInfo);
                
                // 檢查資料型別
                $this->assertIsInt($roleInfo['team_id']);
                $this->assertIsBool($roleInfo['is_leader']);
                
                // role_id 和 role_name 可能是 null（如果沒有角色）或具體值
                if (!is_null($roleInfo['role_id'])) {
                    $this->assertIsInt($roleInfo['role_id']);
                }
                if (!is_null($roleInfo['role_name'])) {
                    $this->assertIsString($roleInfo['role_name']);
                }
            }
        }
    }

    /** @test */
    public function test_user_factory()
    {
        $user = User::factory()->create();
        
        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email
        ]);
    }

    /** @test */
    public function test_user_fillable_attributes()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => bcrypt('password')
        ];
        
        $user = User::create($userData);
        
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('testuser@example.com', $user->email);
        $this->assertNotNull($user->password);
    }

    /** @test */
    public function test_password_is_hidden_in_serialization()
    {
        $user = User::first();
        $userArray = $user->toArray();
        
        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    /** @test */
    public function test_email_verified_at_is_cast_to_datetime()
    {
        $user = User::factory()->create([
            'email_verified_at' => now()
        ]);
        
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
    }

    /** @test */
    public function test_user_has_spatie_permission_traits()
    {
        $user = User::first();
        
        // 檢查是否有 Spatie Permission 套件的方法
        $this->assertTrue(method_exists($user, 'assignRole'));
        $this->assertTrue(method_exists($user, 'hasRole'));
        $this->assertTrue(method_exists($user, 'can'));
        $this->assertTrue(method_exists($user, 'hasPermissionTo'));
    }

    /** @test */
    public function test_user_role_assignment_and_check()
    {
        $user = User::first();
        $team = Team::first();
        $role = $team->roles()->first();
        
        if (!$role) {
            $this->markTestSkipped('No roles found for testing');
        }
        
        // 設定團隊上下文
        setPermissionsTeamId($team);
        
        // 分配角色
        $user->assignRole($role);
        
        // 檢查角色
        $this->assertTrue($user->hasRole($role->name));
        
        // 移除角色
        $user->removeRole($role);
        
        // 檢查角色已被移除
        $this->assertFalse($user->hasRole($role->name));
    }

    /** @test */
    public function test_user_permission_check_through_role()
    {
        $user = User::first();
        $team = Team::first();
        $role = $team->roles()->whereHas('permissions')->first();
        
        if (!$role || $role->permissions->isEmpty()) {
            $this->markTestSkipped('No role with permissions found for testing');
        }
        
        setPermissionsTeamId($team);
        $user->assignRole($role);
        
        // 檢查使用者是否通過角色獲得權限
        $permission = $role->permissions->first();
        $this->assertTrue($user->hasPermissionTo($permission->name));
    }

    /** @test */
    public function test_teams_relationship_uses_correct_table()
    {
        $user = User::first();
        
        // 檢查關聯是否使用正確的中介表
        $teamsQuery = $user->teams();
        $queryString = $teamsQuery->toSql();
        
        $this->assertStringContains('model_has_roles', $queryString);
        $this->assertStringContains('model_type', $queryString);
    }

    /** @test */
    public function test_teams_with_roles_only_returns_user_teams()
    {
        $user = User::first();
        $allTeams = Team::all();
        $userTeams = $user->teams;
        $teamsWithRoles = $user->teamsWithRoles();
        
        // teamsWithRoles 返回的團隊數量不應該超過使用者實際參與的團隊數量
        $this->assertLessThanOrEqual($userTeams->count(), $teamsWithRoles->count());
        
        // 如果使用者有團隊，檢查返回的團隊是否都是使用者的團隊
        if ($userTeams->isNotEmpty() && $teamsWithRoles->isNotEmpty()) {
            $userTeamIds = $userTeams->pluck('id')->toArray();
            
            foreach ($teamsWithRoles as $teamData) {
                foreach ($teamData as $teamName => $roleInfo) {
                    $this->assertContains($roleInfo['team_id'], $userTeamIds);
                }
            }
        }
    }
}