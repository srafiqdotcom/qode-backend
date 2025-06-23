<?php

namespace App\Jobs;

use App\Models\Blog;
use App\Models\User;
use App\Mail\BlogPublishedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendBlogPublishedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Blog $blog;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [60, 120, 300];

    public function __construct(Blog $blog)
    {
        $this->blog = $blog;
    }

    public function handle(): void
    {
        try {
            $this->blog->load(['author', 'tags']);

            // **qode** Send notification to blog author for now (no subscribers needed)
            $author = $this->blog->author;

            if (!$author || !$author->email) {
                Log::info('No author email found for blog notification', [
                    'blog_id' => $this->blog->id,
                    'author_id' => $this->blog->author_id
                ]);
                return;
            }

            try {
                Mail::to($author->email)->send(new BlogPublishedMail($this->blog, $author));

                Log::info('Blog published notification email sent to author', [
                    'blog_id' => $this->blog->id,
                    'author_id' => $author->id,
                    'author_email' => $author->email
                ]);

            } catch (\Exception $e) {
                Log::warning('Failed to send blog notification to author', [
                    'blog_id' => $this->blog->id,
                    'author_id' => $author->id,
                    'author_email' => $author->email,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Blog published notification job failed', [
                'blog_id' => $this->blog->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Blog published notification job permanently failed', [
            'blog_id' => $this->blog->id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }


}
