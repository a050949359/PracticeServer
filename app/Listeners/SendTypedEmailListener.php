<?php

namespace App\Listeners;

use App\Events\SendEmailRequested;
use App\Mail\TypedEmail;
use Illuminate\Support\Facades\Mail;

class SendTypedEmailListener
{
    /**
     * Handle the event.
     */
    public function handle(SendEmailRequested $event): void
    {
        Mail::to($event->email, $event->name)->send(
            new TypedEmail(
                type: $event->type,
                recipientName: $event->name,
                data: $event->data,
            )
        );
    }
}
