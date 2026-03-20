<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_authenticated_user_can_update_profile_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/auth/me', [
            'name' => 'New Name',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated')
            ->assertJsonPath('user.name', 'New Name')
            ->assertJsonPath('user.email', $user->email);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    #[Test]
    public function test_update_profile_requires_valid_name(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/auth/me', [
            'name' => 'A',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function test_update_profile_requires_authentication(): void
    {
        $response = $this->patchJson('/api/auth/me', [
            'name' => 'No Auth',
        ]);

        $response->assertUnauthorized();
    }
}
