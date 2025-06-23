<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BulkNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $subject;
    public string $message;
    public array $data;

    public function __construct(User $user, string $subject, string $message, array $data = [])
    {
        $this->user = $user;
        $this->subject = $subject;
        $this->message = $message;
        $this->data = $data;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bulk-notification',
            with: [
                'user' => $this->user,
                'message' => $this->message,
                'data' => $this->data,
                'appName' => config('app.name'),
                'appUrl' => config('app.url'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
