<?php

namespace Tests\Feature;

use App\Events\SendEmailRequested;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_forgot_password_sends_reset_notification(): void
    {
        Event::fake([SendEmailRequested::class]);

        $user = User::factory()->create([
            'email' => 'forgot@example.com',
        ]);

        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        Event::assertDispatched(SendEmailRequested::class, function (SendEmailRequested $event) use ($user): bool {
            $query = parse_url((string) ($event->data['action_url'] ?? ''), PHP_URL_QUERY);
            parse_str($query ?: '', $params);

            return $event->email === $user->email
                && $event->type === 'password_reset'
                && $event->name === $user->name
                && isset($event->data['action_url'])
                && str_contains($event->data['action_url'], '/register/reset-password?')
                && str_contains($event->data['action_url'], 'email='.urlencode($user->email))
                && isset($params['expires'])
                && isset($params['signature']);
        });
    }

    #[Test]
    public function test_forgot_password_always_returns_success_for_unknown_email(): void
    {
        Event::fake([SendEmailRequested::class]);

        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => 'notfound@example.com',
        ]);

        $response->assertOk();
        Event::assertNotDispatched(SendEmailRequested::class);
    }

    #[Test]
    public function test_reset_password_with_valid_token(): void
    {
        config(['auth.password_policy.change_cooldown_minutes' => 0]);

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => bcrypt('OldPassword!123'),
        ]);

        $token = Password::createToken($user);

        $expires = now()->addMinutes((int) config('auth.passwords.users.expire', 60))->timestamp;
        $signature = hash_hmac(
            'sha256',
            implode('|', [$user->email, $token, $expires]),
            (string) config('app.key'),
        );

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'expires' => $expires,
            'signature' => $signature,
            'password' => 'NewPassword!123',
            'password_confirmation' => 'NewPassword!123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('NewPassword!123', $user->fresh()->password));
    }

    #[Test]
    public function test_change_password_requires_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('CurrentPassword!123'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => 'WrongPassword!123',
            'password' => 'NextPassword!123',
            'password_confirmation' => 'NextPassword!123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'code' => 'current_password_incorrect',
            ]);
    }

    #[Test]
    public function test_change_password_enforces_cooldown(): void
    {
        config(['auth.password_policy.change_cooldown_minutes' => 10]);

        $user = User::factory()->create([
            'password' => bcrypt('CurrentPassword!123'),
        ]);

        Sanctum::actingAs($user);

        $firstResponse = $this->postJson('/api/auth/password/change', [
            'current_password' => 'CurrentPassword!123',
            'password' => 'NextPassword!123',
            'password_confirmation' => 'NextPassword!123',
        ]);

        $firstResponse->assertOk();

        $secondResponse = $this->postJson('/api/auth/password/change', [
            'current_password' => 'NextPassword!123',
            'password' => 'AnotherPassword!123',
            'password_confirmation' => 'AnotherPassword!123',
        ]);

        $secondResponse
            ->assertStatus(429)
            ->assertJson([
                'code' => 'password_change_cooldown',
            ]);
    }

    #[Test]
    public function test_change_password_blocks_recent_history_reuse(): void
    {
        config([
            'auth.password_policy.change_cooldown_minutes' => 0,
            'auth.password_policy.history_generations' => 3,
        ]);

        $user = User::factory()->create([
            'password' => bcrypt('CurrentPassword!123'),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/password/change', [
            'current_password' => 'CurrentPassword!123',
            'password' => 'NextPassword!123',
            'password_confirmation' => 'NextPassword!123',
        ])->assertOk();

        $reuseResponse = $this->postJson('/api/auth/password/change', [
            'current_password' => 'NextPassword!123',
            'password' => 'CurrentPassword!123',
            'password_confirmation' => 'CurrentPassword!123',
        ]);

        $reuseResponse
            ->assertStatus(422)
            ->assertJson([
                'code' => 'password_history_violation',
            ]);
    }
}
