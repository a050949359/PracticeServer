<?php

namespace App\Services\Auth;

use App\Models\Invitation;
use App\Models\User;
use App\Services\EmailDispatchService;
use Illuminate\Support\Facades\URL;

class EmailService
{
    public function __construct(private EmailDispatchService $emailDispatchService) {}

    public function sendVerificationTo(User $user): void
    {
        $this->emailDispatchService->dispatch(
            email: $user->email,
            type: 'welcome',
            name: $user->name,
            data: [
                'action_url' => $this->buildVerificationUrl($user),
            ],
        );
    }

    public function sendPasswordResetTo(User $user, string $token): void
    {
        $this->emailDispatchService->dispatch(
            email: $user->email,
            type: 'password_reset',
            name: $user->name,
            data: [
                'action_url' => $this->buildResetPasswordUrl($user, $token),
            ],
        );
    }

    public function sendRegistrationInviteTo(Invitation $invitation): void
    {
        $this->emailDispatchService->dispatch(
            email: $invitation->email,
            type: 'registration_invite',
            name: $invitation->name,
            data: [
                'action_url' => $this->buildInvitationUrl($invitation),
            ],
        );
    }

    private function buildResetPasswordUrl(User $user, string $token): string
    {
        $expires = now()->addMinutes((int) config('auth.passwords.users.expire', 60))->timestamp;
        $signature = hash_hmac(
            'sha256',
            implode('|', [$user->email, $token, $expires]),
            (string) config('app.key'),
        );

        return route('auth.password.reset').'?'.http_build_query([
            'token' => $token,
            'email' => $user->email,
            'expires' => $expires,
            'signature' => $signature,
        ]);
    }

    private function buildInvitationUrl(Invitation $invitation): string
    {
        $expires = ($invitation->expires_at ?? now()->addHours(72))->timestamp;
        $signature = hash_hmac(
            'sha256',
            implode('|', [$invitation->email, $invitation->token, $expires]),
            (string) config('app.key'),
        );

        return route('auth.register').'?'.http_build_query([
            'token' => $invitation->token,
            'expires' => $expires,
            'signature' => $signature,
        ]);
    }

    private function buildVerificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'auth.verification.verify',
            now()->addHours(24),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ],
        );
    }
}
