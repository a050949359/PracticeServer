<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Database\Seeders\GenUserPermission;

class TeamsPermissionUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_methods_difference_without_teams_vs_with_teams()
    {
        $team1 = Team::find(1);
        $team2 = Team::find(2);
        $user = User::first();

        // =================== 重要：Teams 模式下必須設定團隊上下文 ===================
        
        // 【錯誤方式】- 不設定團隊上下文
        // $user->assignRole('admin'); // 這樣會出錯或無法正確運作
        
        // 【正確方式】- 必須先設定團隊上下文
        setPermissionsTeamId($team1->id);
        $user->assignRole('admin');
        
        // =================== 角色分配的差異 ===================
        
        // 在 Teams 模式下，同一使用者可以在不同團隊有不同角色
        setPermissionsTeamId($team1->id);
        $user->assignRole('admin');
        
        setPermissionsTeamId($team2->id);
        $user->assignRole('member');
        
        // 檢查角色時也必須設定團隊上下文
        setPermissionsTeamId($team1->id);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('member')); // 不同團隊的角色
        
        setPermissionsTeamId($team2->id);
        $this->assertTrue($user->hasRole('member'));
        $this->assertFalse($user->hasRole('admin')); // 不同團隊的角色
        
        // =================== 權限檢查的差異 ===================
        
        // 權限檢查也需要團隊上下文
        setPermissionsTeamId($team1->id);
        $canManageAll = $user->can('user.manage.all'); // 檢查 team1 的權限
        
        setPermissionsTeamId($team2->id);
        $canManageTeam = $user->can('user.manage.team'); // 檢查 team2 的權限
        
        echo "Team1 - can manage all: " . ($canManageAll ? 'Yes' : 'No') . "\n";
        echo "Team2 - can manage team: " . ($canManageTeam ? 'Yes' : 'No') . "\n";
    }

    /** @test */
    public function test_relationship_queries_in_teams_mode()
    {
        $team1 = Team::find(1);
        $team2 = Team::find(2);
        $user = User::first();
        
        // 在不同團隊中分配角色
        setPermissionsTeamId($team1->id);
        $user->assignRole('admin');
        
        setPermissionsTeamId($team2->id);
        $user->assignRole('member');
        
        // =================== 查詢關係的差異 ===================
        
        // 不設定團隊上下文 - 會看到所有團隊的角色
        $allRoles = $user->roles()->withoutGlobalScopes()->get();
        echo "All roles across teams: " . $allRoles->pluck('name')->implode(', ') . "\n";
        
        // 設定團隊上下文 - 只會看到當前團隊的角色
        setPermissionsTeamId($team1->id);
        $team1Roles = $user->roles()->get();
        echo "Team1 roles: " . $team1Roles->pluck('name')->implode(', ') . "\n";
        
        setPermissionsTeamId($team2->id);
        $team2Roles = $user->roles()->get();
        echo "Team2 roles: " . $team2Roles->pluck('name')->implode(', ') . "\n";
    }

    /** @test */
    public function test_role_creation_in_teams_mode()
    {
        $team1 = Team::find(1);
        $team2 = Team::find(2);
        
        // =================== 角色創建的差異 ===================
        
        // 非 Teams 模式：角色是全域的
        // Role::create(['name' => 'global-admin', 'guard_name' => 'web']);
        
        // Teams 模式：角色必須屬於特定團隊
        $team1AdminRole = Role::create([
            'name' => 'team-admin',
            'guard_name' => 'web',
            'team_id' => $team1->id
        ]);
        
        $team2AdminRole = Role::create([
            'name' => 'team-admin', // 同名角色可以存在於不同團隊
            'guard_name' => 'web',
            'team_id' => $team2->id
        ]);
        
        // 權限分配也需要考慮團隊
        $permission = Permission::first();
        $team1AdminRole->givePermissionTo($permission);
        $team2AdminRole->givePermissionTo($permission);
        
        $this->assertNotEquals($team1AdminRole->id, $team2AdminRole->id);
        echo "Team1 admin role ID: {$team1AdminRole->id}\n";
        echo "Team2 admin role ID: {$team2AdminRole->id}\n";
    }

    /** @test */
    public function test_user_role_assignment_patterns()
    {
        $user = User::first();
        $team1 = Team::find(1);
        $team2 = Team::find(2);
        
        // =================== 使用者角色分配模式 ===================
        
        // 模式 1: 分別設定不同團隊的角色
        setPermissionsTeamId($team1->id);
        $user->assignRole('admin');
        
        setPermissionsTeamId($team2->id);
        $user->assignRole('member');
        
        // 模式 2: 檢查特定團隊的角色
        setPermissionsTeamId($team1->id);
        $isAdmin = $user->hasRole('admin');
        
        setPermissionsTeamId($team2->id);
        $isMember = $user->hasRole('member');
        
        $this->assertTrue($isAdmin);
        $this->assertTrue($isMember);
        
        // 模式 3: 同步角色（會清除當前團隊的其他角色）
        setPermissionsTeamId($team1->id);
        $user->syncRoles(['admin', 'leader']); // 只保留這些角色
        
        // 模式 4: 移除特定團隊的角色
        setPermissionsTeamId($team2->id);
        $user->removeRole('member');
        
        $this->assertFalse($user->hasRole('member'));
    }

    /** @test */
    public function test_scope_queries_in_teams_mode()
    {
        $team1 = Team::find(1);
        $user1 = User::factory()->create(['name' => 'User1']);
        $user2 = User::factory()->create(['name' => 'User2']);
        
        setPermissionsTeamId($team1->id);
        $user1->assignRole('admin');
        $user2->assignRole('member');
        
        // =================== 範圍查詢的使用 ===================
        
        // 在 Teams 模式下，scope 查詢會自動考慮團隊上下文
        setPermissionsTeamId($team1->id);
        
        $admins = User::role('admin')->get();
        $members = User::role('member')->get();
        $withoutAdmin = User::withoutRole('admin')->get();
        
        echo "Admins in team1: " . $admins->count() . "\n";
        echo "Members in team1: " . $members->count() . "\n";
        echo "Without admin role in team1: " . $withoutAdmin->count() . "\n";
        
        $this->assertGreaterThan(0, $admins->count());
        $this->assertGreaterThan(0, $members->count());
    }
}

/**
 * Teams 模式 vs 非 Teams 模式對比總結
 */
class TeamsUsageComparison
{
    /**
     * 非 Teams 模式的典型用法
     */
    public function withoutTeamsMode()
    {
        $user = User::first();
        
        // 簡單直接的角色分配
        $user->assignRole('admin');
        $user->givePermissionTo('user.create');
        
        // 檢查角色和權限
        $hasRole = $user->hasRole('admin');
        $canCreate = $user->can('user.create');
        
        // 查詢使用者
        $admins = User::role('admin')->get();
    }
    
    /**
     * Teams 模式的必要用法
     */
    public function withTeamsMode()
    {
        $user = User::first();
        $team = Team::first();
        
        // 【必須】設定團隊上下文
        setPermissionsTeamId($team->id);
        
        // 角色分配（會自動關聯到當前團隊）
        $user->assignRole('admin');
        $user->givePermissionTo('user.create');
        
        // 檢查角色和權限（在當前團隊上下文中）
        $hasRole = $user->hasRole('admin');
        $canCreate = $user->can('user.create');
        
        // 查詢使用者（限制在當前團隊）
        $admins = User::role('admin')->get();
        
        // 切換到其他團隊
        setPermissionsTeamId($anotherTeam->id);
        // 所有後續操作都在新團隊上下文中進行
    }
}