<?php

namespace App\Mail;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Comment $comment;
    public User $recipient;
    public string $type;

    public function __construct(Comment $comment, User $recipient, string $type = 'new_comment')
    {
        $this->comment = $comment;
        $this->recipient = $recipient;
        $this->type = $type;
    }

    public function envelope(): Envelope
    {
        $subject = match($this->type) {
            'new_comment' => "New comment on your blog: {$this->comment->blog->title}",
            'comment_reply' => "Someone replied to your comment",
            'comment_approved' => "Your comment has been approved",
            default => "Comment notification"
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.comment-notification',
            with: [
                'comment' => $this->comment,
                'recipient' => $this->recipient,
                'type' => $this->type,
                'blogUrl' => config('app.url') . '/blogs/' . ($this->comment->blog->slug ?? $this->comment->blog->id),
                'commentUrl' => config('app.url') . '/blogs/' . ($this->comment->blog->slug ?? $this->comment->blog->id) . '#comment-' . $this->comment->id,
                'appName' => config('app.name'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
