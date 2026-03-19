<?php

namespace Tests\Feature;

use App\Mail\TypedEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_authenticated_user_can_resend_own_verification_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/email/verification-notification');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Verification email sent',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);

        Mail::assertSent(TypedEmail::class, function (TypedEmail $mail) use ($user): bool {
            return $mail->type === 'welcome'
                && $mail->recipientName === $user->name
                && str_contains((string) ($mail->data['action_url'] ?? ''), '/verify-email/'.$user->id.'/'.sha1($user->email));
        });
    }

    #[Test]
    public function test_authenticated_user_can_send_verification_email_to_specific_user(): void
    {
        Mail::fake();

        $actor = User::factory()->create();
        $targetUser = User::factory()->create([
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($actor);

        $response = $this->postJson('/api/admin/v1/users/'.$targetUser->id.'/verification-email');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Verification email sent',
                'user' => [
                    'id' => $targetUser->id,
                    'email' => $targetUser->email,
                ],
            ]);

        Mail::assertSent(TypedEmail::class, function (TypedEmail $mail) use ($targetUser): bool {
            return $mail->type === 'welcome'
                && $mail->recipientName === $targetUser->name
                && str_contains((string) ($mail->data['action_url'] ?? ''), '/verify-email/'.$targetUser->id.'/'.sha1($targetUser->email));
        });
    }

    #[Test]
    public function test_can_verify_email_and_update_email_verified_at(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'auth.verification.verify',
            now()->addHours(24),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ],
        );

        $response = $this->get($verificationUrl);

        $response->assertRedirect();

        $redirectLocation = $response->headers->get('Location');

        $this->assertNotNull($redirectLocation);
        $this->assertStringContainsString('/register/verify-email', $redirectLocation);
        $this->assertStringContainsString('status=success', $redirectLocation);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
