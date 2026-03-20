<?php

namespace Tests\Feature;

use App\Events\SendEmailRequested;
use App\Mail\TypedEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailEventTest extends TestCase
{
    #[Test]
    public function test_welcome_email_event_sends_welcome_mail(): void
    {
        Mail::fake();

        Event::dispatch(new SendEmailRequested(
            email: 'welcome@example.com',
            type: 'welcome',
            name: 'Welcome User',
            data: [
                'action_label' => '前往首頁',
                'action_url' => 'http://localhost:8084',
            ],
        ));

        Mail::assertSent(TypedEmail::class, function (TypedEmail $mail): bool {
            return $mail->type === 'welcome'
                && $mail->envelope()->subject === __('mail.welcome.subject', ['app' => config('app.name')]);
        });
    }

    #[Test]
    public function test_registration_invite_event_sends_invitation_mail(): void
    {
        Mail::fake();

        Event::dispatch(new SendEmailRequested(
            email: 'invite@example.com',
            type: 'registration_invite',
            name: 'Invited User',
            data: [
                'message' => '請點擊連結完成你的員工註冊。',
                'action_label' => '完成註冊',
                'action_url' => 'http://localhost:8084/register?token=test-token',
            ],
        ));

        Mail::assertSent(TypedEmail::class, function (TypedEmail $mail): bool {
            return $mail->type === 'registration_invite'
                && $mail->envelope()->subject === __('mail.registration_invite.subject', ['app' => config('app.name')]);
        });
    }
}
