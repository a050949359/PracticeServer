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
        $content = $this->resolveContent();

        return new Envelope(
            subject: $content['subject'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $content = $this->resolveContent();

        return new Content(
            view: 'emails.typed',
            with: $content,
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

    private function resolveContent(): array
    {
        $recipientName = $this->recipientName ?: '使用者';

        return match ($this->type) {
            'welcome' => [
                'subject' => '歡迎加入 PracticeServer',
                'heading' => "歡迎你，{$recipientName}",
                'intro' => $this->data['message'] ?? '你的帳號已建立完成，請先完成 Email 驗證。',
                'actionLabel' => $this->data['action_label'] ?? null,
                'actionUrl' => $this->data['action_url'] ?? null,
            ],
            'registration_invite' => [
                'subject' => 'PracticeServer 註冊邀請',
                'heading' => '你收到一封註冊邀請',
                'intro' => $this->data['message'] ?? '請透過下方連結完成註冊。',
                'actionLabel' => $this->data['action_label'] ?? '前往註冊',
                'actionUrl' => $this->data['action_url'] ?? null,
            ],
            default => throw new InvalidArgumentException("Unsupported email type [{$this->type}]"),
        };
    }
}
