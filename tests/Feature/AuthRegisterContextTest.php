<?php

namespace Tests\Feature;

use App\Mail\TypedEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthRegisterContextTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_register_uses_user_context_by_default(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Public User',
            'email' => 'public@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'context' => 'user_self_register',
                'team' => 'Users',
                'role' => 'user',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'public@example.com',
        ]);

        $this->assertDatabaseHas('teams', [
            'name' => 'Users',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'user',
        ]);

        Mail::assertSent(TypedEmail::class, function (TypedEmail $mail): bool {
            return $mail->type === 'welcome'
                && $mail->recipientName === 'Public User'
                && str_contains((string) ($mail->data['action_url'] ?? ''), '/verify-email/')
                && $mail->envelope()->subject === __('mail.welcome.subject', ['app' => config('app.name')]);
        });
    }

    #[Test]
    public function test_register_uses_staff_context_when_provided(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'context' => 'staff_self_register',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'context' => 'staff_self_register',
                'team' => 'Staff',
                'role' => 'staff',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'staff@example.com',
        ]);

        $this->assertDatabaseHas('teams', [
            'name' => 'Staff',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'staff',
        ]);
    }

    #[Test]
    public function test_register_rejects_unknown_context(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Bad Context',
            'email' => 'bad-context@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'context' => 'admin_root_register',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['context']);
    }
}
