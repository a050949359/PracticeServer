<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthLoginAudienceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_public_user_can_login_with_public_audience(): void
    {
        $user = User::factory()->create([
            'email' => 'public-login@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'audience' => 'public',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    #[Test]
    public function test_public_user_cannot_login_with_admin_audience(): void
    {
        $user = User::factory()->create([
            'email' => 'public-no-admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'audience' => 'admin',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'code' => 'forbidden_admin_only',
            ]);
    }

    #[Test]
    public function test_staff_user_cannot_login_with_public_audience(): void
    {
        $user = $this->createStaffUser('staff-no-public@example.com');

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'audience' => 'public',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'code' => 'forbidden_public_only',
            ]);
    }

    #[Test]
    public function test_staff_user_can_login_with_admin_audience(): void
    {
        $user = $this->createStaffUser('staff-admin-ok@example.com');

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'audience' => 'admin',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    #[Test]
    public function test_login_returns_invalid_credentials_code_for_wrong_password(): void
    {
        $user = User::factory()->create([
            'email' => 'wrong-password@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'audience' => 'public',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'code' => 'invalid_credentials',
            ]);
    }

    private function createStaffUser(string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => bcrypt('password123'),
        ]);

        $team = Team::query()->firstOrCreate(['name' => 'Staff']);
        $staffRole = Role::query()->firstOrCreate(
            [
                'team_id' => $team->id,
                'name' => 'staff',
                'guard_name' => config('auth.defaults.guard'),
            ],
            [
                'is_leader' => false,
            ],
        );

        setPermissionsTeamId($team->id);
        $user->assignRole($staffRole);

        return $user;
    }
}
