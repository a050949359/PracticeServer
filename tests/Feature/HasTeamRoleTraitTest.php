<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Database\Seeders\GenUserPermission;

class HasTeamRoleTraitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $team1;
    protected Team $team2;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 執行權限 seeder
        $this->seed(GenUserPermission::class);
        
        $this->user = User::factory()->create(['name' => 'Test User']);
        $this->team1 = Team::find(1);
        $this->team2 = Team::find(2);
    }

    /** @test */
    public function test_assign_role_in_team()
    {
        // 使用新的便利方法在團隊中分配角色
        $this->user->assignRoleInTeam('admin', $this->team1);
        
        // 驗證角色已分配
        $this->assertTrue($this->user->hasRoleInTeam('admin', $this->team1));
        $this->assertFalse($this->user->hasRoleInTeam('admin', $this->team2));
    }

    /** @test */
    public function test_assign_different_roles_in_different_teams()
    {
        // 在不同團隊分配不同角色
        $this->user->assignRoleInTeam('admin', $this->team1)
                   ->assignRoleInTeam('member', $this->team2);
        
        // 驗證各團隊的角色
        $this->assertTrue($this->user->hasRoleInTeam('admin', $this->team1));
        $this->assertTrue($this->user->hasRoleInTeam('member', $this->team2));
        
        // 確認角色不會跨團隊
        $this->assertFalse($this->user->hasRoleInTeam('member', $this->team1));
        $this->assertFalse($this->user->hasRoleInTeam('admin', $this->team2));
    }

    /** @test */
    public function test_sync_roles_in_team()
    {
        // 先分配一些角色
        $this->user->assignRoleInTeam('admin', $this->team1)
                   ->assignRoleInTeam('leader', $this->team1);
        
        // 同步角色（應該只保留新指定的角色）
        $this->user->syncRolesInTeam(['member'], $this->team1);
        
        // 驗證舊角色已移除，新角色已分配
        $this->assertFalse($this->user->hasRoleInTeam('admin', $this->team1));
        $this->assertFalse($this->user->hasRoleInTeam('leader', $this->team1));
        $this->assertTrue($this->user->hasRoleInTeam('member', $this->team1));
    }

    /** @test */
    public function test_check_permissions_in_team()
    {
        // 分配有權限的角色
        $this->user->assignRoleInTeam('admin', $this->team1);
        
        // 檢查團隊中的權限
        $canManage = $this->user->canInTeam('user.manage.all', $this->team1);
        $this->assertTrue($canManage);
        
        // 在沒有角色的團隊中檢查權限
        $canManageInTeam2 = $this->user->canInTeam('user.manage.all', $this->team2);
        $this->assertFalse($canManageInTeam2);
    }

    /** @test */
    public function test_get_roles_in_team()
    {
        // 在團隊中分配多個角色
        $this->user->assignRoleInTeam('admin', $this->team1)
                   ->assignRoleInTeam('leader', $this->team1);
        
        // 獲取團隊中的角色
        $roles = $this->user->getRolesInTeam($this->team1);
        $roleNames = $roles->pluck('name')->toArray();
        
        $this->assertContains('admin', $roleNames);
        $this->assertContains('leader', $roleNames);
        $this->assertCount(2, $roleNames);
    }

    /** @test */
    public function test_get_roles_across_all_teams()
    {
        // 在不同團隊分配角色
        $this->user->assignRoleInTeam('admin', $this->team1)
                   ->assignRoleInTeam('member', $this->team2);
        
        // 獲取所有團隊的角色
        $rolesAcrossTeams = $this->user->getRolesAcrossAllTeams();
        
        $this->assertCount(2, $rolesAcrossTeams);
        
        // 驗證團隊1的資料
        $team1Data = collect($rolesAcrossTeams)->firstWhere('team_id', $this->team1->id);
        $this->assertNotNull($team1Data);
        $this->assertContains('admin', $team1Data['roles']);
        
        // 驗證團隊2的資料
        $team2Data = collect($rolesAcrossTeams)->firstWhere('team_id', $this->team2->id);
        $this->assertNotNull($team2Data);
        $this->assertContains('member', $team2Data['roles']);
    }

    /** @test */
    public function test_transfer_role_between_teams()
    {
        // 先在團隊1分配角色
        $this->user->assignRoleInTeam('admin', $this->team1);
        $this->assertTrue($this->user->hasRoleInTeam('admin', $this->team1));
        
        // 轉移角色到團隊2
        $this->user->transferRoleBetweenTeams('admin', $this->team1, $this->team2);
        
        // 驗證轉移結果
        $this->assertFalse($this->user->hasRoleInTeam('admin', $this->team1));
        $this->assertTrue($this->user->hasRoleInTeam('admin', $this->team2));
    }

    /** @test */
    public function test_copy_role_to_team()
    {
        // 先在團隊1分配角色
        $this->user->assignRoleInTeam('admin', $this->team1);
        
        // 複製角色到團隊2
        $this->user->copyRoleToTeam('admin', $this->team1, $this->team2);
        
        // 驗證兩個團隊都有該角色
        $this->assertTrue($this->user->hasRoleInTeam('admin', $this->team1));
        $this->assertTrue($this->user->hasRoleInTeam('admin', $this->team2));
    }

    /** @test */
    public function test_belongs_to_team()
    {
        // 分配角色後用戶應該屬於該團隊
        $this->user->assignRoleInTeam('member', $this->team1);
        
        $this->assertTrue($this->user->belongsToTeam($this->team1));
        $this->assertFalse($this->user->belongsToTeam($this->team2));
    }

    /** @test */
    public function test_get_teams()
    {
        // 在多個團隊分配角色
        $this->user->assignRoleInTeam('admin', $this->team1)
                   ->assignRoleInTeam('member', $this->team2);
        
        // 獲取用戶所屬團隊
        $userTeams = $this->user->getTeams();
        $teamIds = $userTeams->pluck('id')->toArray();
        
        $this->assertContains($this->team1->id, $teamIds);
        $this->assertContains($this->team2->id, $teamIds);
        $this->assertCount(2, $teamIds);
    }

    /** @test */
    public function test_with_team_context()
    {
        // 先在團隊1設定一個角色
        $this->user->assignRoleInTeam('admin', $this->team1);
        
        // 使用團隊上下文執行操作
        $result = $this->user->withTeamContext($this->team1, function ($user) {
            return $user->hasRole('admin');
        });
        
        $this->assertTrue($result);
        
        // 在不同團隊上下文中檢查
        $result2 = $this->user->withTeamContext($this->team2, function ($user) {
            return $user->hasRole('admin');
        });
        
        $this->assertFalse($result2);
    }

    /** @test */
    public function test_assign_role_in_multiple_teams()
    {
        // 在多個團隊中分配相同角色
        $this->user->assignRoleInMultipleTeams('member', [$this->team1->id, $this->team2->id]);
        
        // 驗證兩個團隊都有該角色
        $this->assertTrue($this->user->hasRoleInTeam('member', $this->team1));
        $this->assertTrue($this->user->hasRoleInTeam('member', $this->team2));
    }

    /** @test */
    public function test_has_role_in_any_team()
    {
        // 只在一個團隊中分配角色
        $this->user->assignRoleInTeam('admin', $this->team1);
        
        // 檢查是否在任意團隊中有該角色
        $this->assertTrue($this->user->hasRoleInAnyTeam('admin'));
        $this->assertFalse($this->user->hasRoleInAnyTeam('super-admin'));
    }

    /** @test */
    public function test_get_teams_with_role()
    {
        // 在多個團隊分配相同角色
        $this->user->assignRoleInTeam('admin', $this->team1)
                   ->assignRoleInTeam('admin', $this->team2)
                   ->assignRoleInTeam('member', $this->team2);
        
        // 獲取有 admin 角色的團隊
        $teamsWithAdmin = $this->user->getTeamsWithRole('admin');
        $teamIds = $teamsWithAdmin->pluck('id')->toArray();
        
        $this->assertContains($this->team1->id, $teamIds);
        $this->assertContains($this->team2->id, $teamIds);
        $this->assertCount(2, $teamIds);
        
        // 獲取有 member 角色的團隊
        $teamsWithMember = $this->user->getTeamsWithRole('member');
        $this->assertCount(1, $teamsWithMember);
        $this->assertEquals($this->team2->id, $teamsWithMember->first()->id);
    }
}

/**
 * 使用範例展示
 */
class HasTeamRoleUsageExamples
{
    public function examples()
    {
        $user = User::first();
        $team1 = Team::find(1);
        $team2 = Team::find(2);
        
        // =============== 基本使用 ===============
        
        // 在團隊中分配角色（比原生方法更簡潔）
        $user->assignRoleInTeam('admin', $team1);
        
        // 檢查團隊角色
        $isAdmin = $user->hasRoleInTeam('admin', $team1);
        
        // 檢查團隊權限
        $canManage = $user->canInTeam('user.manage.all', $team1);
        
        // =============== 進階使用 ===============
        
        // 在多個團隊分配相同角色
        $user->assignRoleInMultipleTeams('member', [$team1->id, $team2->id]);
        
        // 獲取所有團隊的角色概覽
        $allRoles = $user->getRolesAcrossAllTeams();
        
        // 角色轉移
        $user->transferRoleBetweenTeams('leader', $team1, $team2);
        
        // 使用團隊上下文執行複雜操作
        $result = $user->withTeamContext($team1, function ($user) {
            // 在這裡執行的所有權限操作都在 team1 上下文中
            return $user->getAllPermissions();
        });
        
        // 檢查用戶在任意團隊中是否有特定角色
        $isLeaderAnywhere = $user->hasRoleInAnyTeam('leader');
        
        // 獲取用戶擁有特定角色的所有團隊
        $leaderTeams = $user->getTeamsWithRole('leader');
    }
}