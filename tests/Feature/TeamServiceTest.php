<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Database\Seeders\GenUserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TeamService $teamService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teamService = new TeamService();
        
        // 運行權限相關的 seeder
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_can_get_all_teams()
    {
        $result = $this->teamService->getAllTeams();
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertIsArray($data['data']);
        $this->assertGreaterThan(0, count($data['data']));
    }

    /** @test */
    public function test_can_get_team_data()
    {
        $team = Team::first();
        
        $result = $this->teamService->getTeamData($team->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertEquals($team->id, $data['data']['id']);
        $this->assertEquals($team->name, $data['data']['name']);
    }

    /** @test */
    public function test_returns_404_for_nonexistent_team()
    {
        $result = $this->teamService->getTeamData(99999);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Team not found', $data['message']);
    }

    /** @test */
    public function test_can_create_team()
    {
        $teamData = ['name' => 'New Test Team'];
        
        $result = $this->teamService->createTeam($teamData);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Team created successfully', $data['message']);
        $this->assertEquals('New Test Team', $data['data']['name']);
        
        // 驗證團隊確實被創建
        $team = Team::where('name', 'New Test Team')->first();
        $this->assertNotNull($team);
    }

    /** @test */
    public function test_can_update_team()
    {
        $team = Team::first();
        $updateData = ['name' => 'Updated Team Name'];
        
        $result = $this->teamService->updateTeam($team->id, $updateData);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Team updated successfully', $data['message']);
        $this->assertEquals('Updated Team Name', $data['data']['name']);
        
        // 驗證數據庫中的數據確實被更新
        $team->refresh();
        $this->assertEquals('Updated Team Name', $team->name);
    }

    /** @test */
    public function test_can_delete_empty_team()
    {
        // 創建一個沒有成員的團隊
        $team = Team::create(['name' => 'Empty Team']);
        
        $result = $this->teamService->deleteTeam($team->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Team deleted successfully', $data['message']);
        
        // 驗證團隊確實被刪除
        $this->assertNull(Team::find($team->id));
    }

    /** @test */
    public function test_cannot_delete_team_with_users()
    {
        $team = Team::where('name', 'First Team')->first();
        
        $result = $this->teamService->deleteTeam($team->id);
        $response = $result->getResponse();
        
        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Cannot delete team with existing users', $data['message']);
        
        // 驗證團隊沒有被刪除
        $this->assertNotNull(Team::find($team->id));
    }

    /** @test */
    public function test_can_get_team_members()
    {
        $team = Team::first();
        
        $result = $this->teamService->getTeamMembers($team->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('OK', $data['message']);
        $this->assertArrayHasKey('team', $data['data']);
        $this->assertArrayHasKey('members', $data['data']);
        $this->assertEquals($team->id, $data['data']['team']['id']);
    }

    /** @test */
    public function test_can_get_team_roles()
    {
        $team = Team::first();
        
        $result = $this->teamService->getTeamRoles($team->id);
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
    public function test_returns_404_for_team_members_of_nonexistent_team()
    {
        $result = $this->teamService->getTeamMembers(99999);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Team not found', $data['message']);
    }

    /** @test */
    public function test_returns_404_for_team_roles_of_nonexistent_team()
    {
        $result = $this->teamService->getTeamRoles(99999);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Team not found', $data['message']);
    }

    /** @test */
    public function test_team_deletion_also_removes_roles()
    {
        // 創建一個新團隊和角色用於測試
        $team = Team::create(['name' => 'Test Team for Deletion']);
        $role = Role::create([
            'name' => 'test-role',
            'team_id' => $team->id,
            'is_leader' => false
        ]);
        
        $roleId = $role->id;
        
        // 刪除團隊
        $result = $this->teamService->deleteTeam($team->id);
        $response = $result->getResponse();
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // 驗證角色也被刪除
        $this->assertNull(Role::find($roleId));
    }

    /** @test */
    public function test_fails_update_nonexistent_team()
    {
        $result = $this->teamService->updateTeam(99999, ['name' => 'New Name']);
        $response = $result->getResponse();
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Team not found', $data['message']);
    }
}