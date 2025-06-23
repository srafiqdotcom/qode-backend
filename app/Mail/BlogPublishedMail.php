<?php

namespace App\Mail;

use App\Models\Blog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BlogPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Blog $blog;
    public User $subscriber;

    public function __construct(Blog $blog, User $subscriber)
    {
        $this->blog = $blog;
        $this->subscriber = $subscriber;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Blog Post: {$this->blog->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.blog-published',
            with: [
                'blog' => $this->blog,
                'subscriber' => $this->subscriber,
                'blogUrl' => config('app.url') . '/blogs/' . ($this->blog->slug ?? $this->blog->id),
                'unsubscribeUrl' => config('app.url') . '/unsubscribe/' . $this->generateUnsubscribeToken(),
                'appName' => config('app.name'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function generateUnsubscribeToken(): string
    {
        return encrypt([
            'user_id' => $this->subscriber->id,
            'email' => $this->subscriber->email,
            'timestamp' => now()->timestamp
        ]);
    }
}
