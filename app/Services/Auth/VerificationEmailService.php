<?php

namespace App\Services\Auth;

use App\Events\SendEmailRequested;
use App\Models\User;
use Illuminate\Support\Facades\URL;

class VerificationEmailService
{
    public function sendTo(User $user): void
    {
        event(new SendEmailRequested(
            email: $user->email,
            type: 'welcome',
            name: $user->name,
            data: [
                'message' => '你的帳號已建立完成，請先點擊下方按鈕驗證 Email。',
                'action_label' => '驗證 Email',
                'action_url' => URL::temporarySignedRoute(
                    'auth.verification.verify',
                    now()->addHours(24),
                    [
                        'id' => $user->id,
                        'hash' => sha1($user->email),
                    ],
                ),
            ],
        ));
    }
}
