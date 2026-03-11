<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\GenUserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_team_users_relationship()
    {
        $team = Team::first();
        $users = $team->users;
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $users);
        
        // 檢查每個使用者是否為 User 模型實例
        foreach ($users as $user) {
            $this->assertInstanceOf(User::class, $user);
        }
    }

    /** @test */
    public function test_team_roles_relationship()
    {
        $team = Team::first();
        $roles = $team->roles;
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $roles);
        
        // 檢查每個角色是否為 Role 模型實例且屬於該團隊
        foreach ($roles as $role) {
            $this->assertInstanceOf(Role::class, $role);
            $this->assertEquals($team->id, $role->team_id);
        }
    }

    /** @test */
    public function test_roles_for_user_method()
    {
        $team = Team::first();
        $user = $team->users()->first();
        
        if (!$user) {
            $this->markTestSkipped('No users found in team');
        }
        
        $rolesQuery = $team->rolesForUser($user);
        
        // 這應該返回一個查詢建構器
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $rolesQuery);
        
        // 執行查詢並檢查結果
        $roles = $rolesQuery->get();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $roles);
        
        // 檢查返回的角色是否都屬於該團隊且與使用者相關
        foreach ($roles as $role) {
            $this->assertEquals($team->id, $role->team_id);
            
            // 檢查這個角色是否確實分配給了使用者
            setPermissionsTeamId($team);
            $this->assertTrue($user->hasRole($role->name));
        }
    }

    /** @test */
    public function test_users_with_roles_method()
    {
        $team = Team::first();
        $usersWithRoles = $team->usersWithRoles();
        
        // 這應該返回一個查詢建構器
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $usersWithRoles);
        
        // 執行查詢並檢查結果
        $users = $usersWithRoles->get();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $users);
        
        // 檢查每個使用者是否都預載了角色
        foreach ($users as $user) {
            $this->assertInstanceOf(User::class, $user);
            $this->assertTrue($user->relationLoaded('roles'));
            
            // 檢查載入的角色是否都屬於該團隊
            foreach ($user->roles as $role) {
                $this->assertEquals($team->id, $role->team_id);
            }
        }
    }

    /** @test */
    public function test_team_fillable_attributes()
    {
        $teamData = ['name' => 'New Test Team'];
        $team = Team::create($teamData);
        
        $this->assertInstanceOf(Team::class, $team);
        $this->assertEquals('New Test Team', $team->name);
        $this->assertDatabaseHas('teams', [
            'name' => 'New Test Team'
        ]);
    }

    /** @test */
    public function test_team_users_uses_correct_pivot_table()
    {
        $team = Team::first();
        
        // 檢查關聯是否使用正確的中介表
        $usersQuery = $team->users();
        $queryString = $usersQuery->toSql();
        
        $this->assertStringContains('model_has_roles', $queryString);
        $this->assertStringContains('model_type', $queryString);
    }

    /** @test */
    public function test_team_roles_relationship_integrity()
    {
        $team = Team::first();
        $roles = $team->roles;
        
        // 檢查角色確實屬於該團隊
        $roleTeamIds = $roles->pluck('team_id')->unique();
        $this->assertCount(1, $roleTeamIds);
        $this->assertEquals($team->id, $roleTeamIds->first());
    }

    /** @test */
    public function test_create_team_with_roles()
    {
        $team = Team::create(['name' => 'Team with Roles']);
        
        // 為團隊創建角色
        $leaderRole = Role::create([
            'name' => 'team-leader',
            'team_id' => $team->id,
            'is_leader' => true
        ]);
        
        $memberRole = Role::create([
            'name' => 'team-member',
            'team_id' => $team->id,
            'is_leader' => false
        ]);
        
        // 重新載入團隊以檢查關聯
        $team->refresh();
        $this->assertCount(2, $team->roles);
        
        // 檢查角色是否正確創建
        $teamRoles = $team->roles;
        $this->assertTrue($teamRoles->contains('name', 'team-leader'));
        $this->assertTrue($teamRoles->contains('name', 'team-member'));
        
        // 檢查 is_leader 欄位
        $leader = $teamRoles->where('name', 'team-leader')->first();
        $member = $teamRoles->where('name', 'team-member')->first();
        
        $this->assertTrue($leader->is_leader);
        $this->assertFalse($member->is_leader);
    }

    /** @test */
    public function test_team_deletion_cascade_to_roles()
    {
        $team = Team::create(['name' => 'Deletable Team']);
        
        // 為團隊創建角色
        $role = Role::create([
            'name' => 'deletable-role',
            'team_id' => $team->id,
            'is_leader' => false
        ]);
        
        $roleId = $role->id;
        
        // 刪除團隊
        $team->delete();
        
        // 角色應該仍然存在（需要手動處理級聯刪除）
        $this->assertNotNull(Role::find($roleId));
        
        // 手動刪除角色來模擬級聯刪除
        Role::where('team_id', $team->id)->delete();
        $this->assertNull(Role::find($roleId));
    }

    /** @test */
    public function test_roles_for_user_returns_empty_for_non_member()
    {
        $team = Team::first();
        $nonMemberUser = User::factory()->create();
        
        $roles = $team->rolesForUser($nonMemberUser)->get();
        
        $this->assertCount(0, $roles);
    }

    /** @test */
    public function test_users_with_roles_includes_role_information()
    {
        $team = Team::first();
        $users = $team->usersWithRoles()->get();
        
        if ($users->isEmpty()) {
            $this->markTestSkipped('No users with roles found in team');
        }
        
        foreach ($users as $user) {
            // 檢查是否預載了角色
            $this->assertTrue($user->relationLoaded('roles'));
            
            // 如果使用者有角色，檢查角色資訊
            if ($user->roles->isNotEmpty()) {
                foreach ($user->roles as $role) {
                    $this->assertNotNull($role->name);
                    $this->assertEquals($team->id, $role->team_id);
                    $this->assertIsBool($role->is_leader);
                }
            }
        }
    }

    /** @test */
    public function test_team_has_leader_roles()
    {
        $team = Team::first();
        $leaderRoles = $team->roles()->where('is_leader', true)->get();
        $memberRoles = $team->roles()->where('is_leader', false)->get();
        
        // 檢查團隊是否有領導者角色和成員角色
        if ($team->roles->isNotEmpty()) {
            $this->assertTrue(
                $leaderRoles->isNotEmpty() || $memberRoles->isNotEmpty(),
                'Team should have either leader roles or member roles'
            );
            
            // 如果有領導者角色，檢查 is_leader 欄位
            foreach ($leaderRoles as $role) {
                $this->assertTrue($role->is_leader);
            }
            
            foreach ($memberRoles as $role) {
                $this->assertFalse($role->is_leader);
            }
        }
    }

    /** @test */
    public function test_team_users_distinct()
    {
        $team = Team::first();
        $users = $team->users;
        
        // 檢查是否有重複的使用者（同一使用者在同一團隊中有多個角色的情況）
        $userIds = $users->pluck('id');
        $uniqueUserIds = $userIds->unique();
        
        $this->assertEquals($userIds->count(), $uniqueUserIds->count(), 
            'Team users should be distinct even if user has multiple roles');
    }

    /** @test */
    public function test_empty_team_methods()
    {
        $emptyTeam = Team::create(['name' => 'Empty Team']);
        
        // 檢查空團隊的方法是否正常工作
        $this->assertCount(0, $emptyTeam->users);
        $this->assertCount(0, $emptyTeam->roles);
        
        $testUser = User::factory()->create();
        $roles = $emptyTeam->rolesForUser($testUser)->get();
        $this->assertCount(0, $roles);
        
        $usersWithRoles = $emptyTeam->usersWithRoles()->get();
        $this->assertCount(0, $usersWithRoles);
    }
}