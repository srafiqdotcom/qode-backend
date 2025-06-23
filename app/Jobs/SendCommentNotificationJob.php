<?php

namespace App\Jobs;

use App\Models\Comment;
use App\Mail\CommentNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendCommentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Comment $comment;
    public string $type;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [30, 60, 120];

    public function __construct(Comment $comment, string $type = 'new_comment')
    {
        $this->comment = $comment;
        $this->type = $type;
    }

    public function handle(): void
    {
        try {
            $this->comment->load(['user', 'blog.author', 'parent.user']);

            $recipients = $this->getRecipients();

            if (empty($recipients)) {
                Log::info('No recipients found for comment notification', [
                    'comment_id' => $this->comment->id,
                    'type' => $this->type
                ]);
                return;
            }

            $emailsSent = 0;

            foreach ($recipients as $recipient) {
                try {
                    Mail::to($recipient['email'])->send(
                        new CommentNotificationMail($this->comment, $recipient['user'], $this->type)
                    );
                    $emailsSent++;
                } catch (\Exception $e) {
                    Log::warning('Failed to send comment notification', [
                        'comment_id' => $this->comment->id,
                        'recipient_email' => $recipient['email'],
                        'type' => $this->type,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Comment notifications sent', [
                'comment_id' => $this->comment->id,
                'type' => $this->type,
                'emails_sent' => $emailsSent,
                'total_recipients' => count($recipients)
            ]);

        } catch (\Exception $e) {
            Log::error('Comment notification job failed', [
                'comment_id' => $this->comment->id,
                'type' => $this->type,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Comment notification job permanently failed', [
            'comment_id' => $this->comment->id,
            'type' => $this->type,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(1);
    }

    private function getRecipients(): array
    {
        $recipients = [];

        switch ($this->type) {
            case 'new_comment':
                if ($this->comment->blog->author->email_notifications ?? false) {
                    $recipients[] = [
                        'user' => $this->comment->blog->author,
                        'email' => $this->comment->blog->author->email
                    ];
                }
                break;

            case 'comment_reply':
                if ($this->comment->parent && 
                    $this->comment->parent->user->id !== $this->comment->user_id &&
                    ($this->comment->parent->user->email_notifications ?? false)) {
                    $recipients[] = [
                        'user' => $this->comment->parent->user,
                        'email' => $this->comment->parent->user->email
                    ];
                }
                break;

            case 'comment_approved':
                if ($this->comment->user->email_notifications ?? false) {
                    $recipients[] = [
                        'user' => $this->comment->user,
                        'email' => $this->comment->user->email
                    ];
                }
                break;
        }

        return array_filter($recipients, function ($recipient) {
            return !empty($recipient['email']) && 
                   $recipient['user']->email_verified_at !== null;
        });
    }
}
