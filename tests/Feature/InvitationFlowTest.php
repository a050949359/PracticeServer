<?php

namespace Tests\Feature;

use App\Mail\TypedEmail;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvitationFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_admin_can_create_invitation_and_send_email(): void
    {
        Mail::fake();

        $inviter = $this->actingAsStaffUser();

        $response = $this->postJson('/api/admin/v1/invitations', [
            'email' => 'invited@example.com',
            'name' => 'Invited User',
            'context' => 'staff_invited_register',
            'expires_in_hours' => 24,
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Invitation created',
                'invitation' => [
                    'email' => 'invited@example.com',
                    'name' => 'Invited User',
                    'context' => 'staff_invited_register',
                ],
            ]);

        $this->assertDatabaseHas('invitations', [
            'email' => 'invited@example.com',
            'name' => 'Invited User',
            'context' => 'staff_invited_register',
            'invited_by' => $inviter->id,
        ]);

        Mail::assertSent(TypedEmail::class, function (TypedEmail $mail): bool {
            return $mail->type === 'registration_invite'
                && $mail->recipientName === 'Invited User'
                && $mail->envelope()->subject === 'PracticeServer 註冊邀請';
        });
    }

    #[Test]
    public function test_invitation_rejects_invalid_context(): void
    {
        $inviter = $this->actingAsStaffUser();

        $response = $this->postJson('/api/admin/v1/invitations', [
            'email' => 'invited@example.com',
            'context' => 'staff_self_register',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['context']);
    }

    #[Test]
    public function test_can_fetch_invitation_by_token(): void
    {
        $invitation = Invitation::query()->create([
            'email' => 'invited@example.com',
            'name' => 'Invited User',
            'context' => 'user_invited_register',
            'token' => 'token-abc-123',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/auth/invitations/'.$invitation->token);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Invitation found',
                'invitation' => [
                    'email' => 'invited@example.com',
                    'context' => 'user_invited_register',
                ],
            ]);
    }

    #[Test]
    public function test_non_staff_cannot_create_invitation(): void
    {
        $inviter = User::factory()->create();
        Sanctum::actingAs($inviter);

        $response = $this->postJson('/api/admin/v1/invitations', [
            'email' => 'invited@example.com',
            'name' => 'Invited User',
            'context' => 'staff_invited_register',
            'expires_in_hours' => 24,
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'code' => 'forbidden_staff_only',
            ]);
    }

    #[Test]
    public function test_can_complete_invitation_registration_and_receive_redirect(): void
    {
        Mail::fake();

        Invitation::query()->create([
            'email' => 'staff-invite@example.com',
            'name' => 'Staff Invite',
            'context' => 'staff_invited_register',
            'token' => 'token-staff-456',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/auth/register/invitation', [
            'token' => 'token-staff-456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'message' => 'Invitation registration completed',
                'role' => 'staff',
                'redirect_to' => '/admin',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'staff-invite@example.com',
            'name' => 'Staff Invite',
        ]);

        $this->assertDatabaseHas('invitations', [
            'email' => 'staff-invite@example.com',
        ]);

        $this->assertNotNull(Invitation::query()->where('token', 'token-staff-456')->first()?->accepted_at);

        Mail::assertSent(TypedEmail::class, function (TypedEmail $mail): bool {
            return $mail->type === 'welcome'
                && $mail->recipientName === 'Staff Invite'
                && $mail->envelope()->subject === '歡迎加入 PracticeServer';
        });
    }

    private function actingAsStaffUser(): User
    {
        $user = User::factory()->create();

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

        Sanctum::actingAs($user);

        return $user;
    }
}
