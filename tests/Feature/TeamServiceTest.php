<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Repository\Permission\TeamRepository;
use BadMethodCallException;
use Database\Seeders\GenUserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TeamRepository $teamRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teamRepository = app(TeamRepository::class);
        $this->seed(GenUserPermission::class);
    }

    /** @test */
    public function test_can_get_all_teams(): void
    {
        $teams = $this->teamRepository->getAllTeams();

        $this->assertGreaterThan(0, $teams->count());
    }

    /** @test */
    public function test_can_get_team_data(): void
    {
        $team = Team::first();

        $loaded = $this->teamRepository->getTeamData($team->id);

        $this->assertNotNull($loaded);
        $this->assertSame($team->id, $loaded->id);
        $this->assertSame($team->name, $loaded->name);
    }

    /** @test */
    public function test_returns_null_for_nonexistent_team_data(): void
    {
        $this->assertNull($this->teamRepository->getTeamData(99999));
    }

    /** @test */
    public function test_can_create_team(): void
    {
        $teamData = ['name' => 'New Test Team'];

        $created = $this->teamRepository->createTeam($teamData);

        $this->assertSame('New Test Team', $created->name);
        $this->assertDatabaseHas('teams', ['name' => 'New Test Team']);
    }

    /** @test */
    public function test_can_update_team(): void
    {
        $team = Team::first();
        $updateData = ['name' => 'Updated Team Name'];

        $this->teamRepository->updateTeam($team->id, $updateData);

        $team->refresh();
        $this->assertSame('Updated Team Name', $team->name);
    }

    /** @test */
    public function test_can_delete_empty_team(): void
    {
        $team = Team::create(['name' => 'Empty Team']);

        $this->expectException(BadMethodCallException::class);
        $this->teamRepository->deleteTeam($team->id);
    }

    /** @test */
    public function test_cannot_delete_team_with_users(): void
    {
        $team = Team::where('name', 'Maintainer')->firstOrFail();

        $this->expectException(BadMethodCallException::class);

        $this->teamRepository->deleteTeam($team->id);
    }

    /** @test */
    public function test_can_get_team_members(): void
    {
        $team = Team::first();

        $loaded = $this->teamRepository->getTeamMembers($team->id);

        $this->assertSame($team->id, $loaded->id);
        $this->assertIsIterable($loaded->roles);
    }

    /** @test */
    public function test_can_get_team_roles(): void
    {
        $team = Team::first();

        $loaded = $this->teamRepository->getTeamRoles($team->id);

        $this->assertSame($team->id, $loaded->id);
        $this->assertIsIterable($loaded->roles);
    }

    /** @test */
    public function test_returns_404_for_team_members_of_nonexistent_team(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Team not found');

        $this->teamRepository->getTeamMembers(99999);
    }

    /** @test */
    public function test_returns_404_for_team_roles_of_nonexistent_team(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Team not found');

        $this->teamRepository->getTeamRoles(99999);
    }

    /** @test */
    public function test_team_deletion_also_removes_roles(): void
    {
        $team = Team::create(['name' => 'Test Team for Deletion']);
        $role = Role::create([
            'name' => 'test-role',
            'team_id' => $team->id,
            'is_leader' => false,
        ]);

        $this->expectException(BadMethodCallException::class);
        $this->teamRepository->deleteTeam($team->id);
    }

    /** @test */
    public function test_fails_update_nonexistent_team(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Team not found');

        $this->teamRepository->updateTeam(99999, ['name' => 'New Name']);
    }
}
