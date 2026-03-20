<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

class TypedEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $recipientName = null,
        public readonly array $data = [],
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $template = $this->resolveTemplate();

        return new Envelope(
            subject: $template['subject'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $template = $this->resolveTemplate();

        return new Content(
            view: $template['view'],
            with: $template['with'],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * @return array{subject:string,view:string,with:array<string,mixed>}
     */
    private function resolveTemplate(): array
    {
        $recipientName = $this->recipientName ?: __('mail.common.default_recipient_name');
        $appName = config('app.name');

        return match ($this->type) {
            'welcome' => [
                'subject' => __('mail.welcome.subject', ['app' => $appName]),
                'view' => 'emails.templates.typed_email',
                'with' => [
                    'subject' => __('mail.welcome.subject', ['app' => $appName]),
                    'heading' => __('mail.welcome.heading', ['name' => $recipientName]),
                    'intro' => $this->data['message'] ?? __('mail.welcome.intro'),
                    'actionLabel' => $this->data['action_label'] ?? __('mail.welcome.action_label'),
                    'actionUrl' => $this->data['action_url'] ?? null,
                    'actionHint' => $this->data['action_hint'] ?? __('mail.common.action_hint'),
                    'buttonBackground' => '#22d3ee',
                    'buttonTextColor' => '#082f49',
                ],
            ],
            'registration_invite' => [
                'subject' => __('mail.registration_invite.subject', ['app' => $appName]),
                'view' => 'emails.templates.typed_email',
                'with' => [
                    'subject' => __('mail.registration_invite.subject', ['app' => $appName]),
                    'heading' => __('mail.registration_invite.heading'),
                    'intro' => $this->data['message'] ?? __('mail.registration_invite.intro'),
                    'actionLabel' => $this->data['action_label'] ?? __('mail.registration_invite.action_label'),
                    'actionUrl' => $this->data['action_url'] ?? null,
                    'actionHint' => $this->data['action_hint'] ?? __('mail.common.action_hint'),
                    'buttonBackground' => '#2563eb',
                    'buttonTextColor' => '#ffffff',
                ],
            ],
            default => throw new InvalidArgumentException("Unsupported email type [{$this->type}]"),
        };
    }
}
