<?php

namespace App\Jobs;

use App\Models\Blog;
use App\Services\EmailService;
use App\Services\SearchService;
use App\Services\CacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PublishScheduledBlogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $blogId;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [60, 180, 300];

    public function __construct(int $blogId)
    {
        $this->blogId = $blogId;
    }

    public function handle(EmailService $emailService, SearchService $searchService, CacheService $cacheService): void
    {
        try {
            DB::beginTransaction();

            $blog = Blog::with(['author', 'tags'])->find($this->blogId);

            if (!$blog) {
                Log::warning('Scheduled blog not found for publishing', [
                    'blog_id' => $this->blogId
                ]);
                DB::rollBack();
                return;
            }

            if ($blog->status !== 'scheduled') {
                Log::info('Blog is no longer scheduled for publishing', [
                    'blog_id' => $this->blogId,
                    'current_status' => $blog->status
                ]);
                DB::rollBack();
                return;
            }

            if ($blog->scheduled_at && $blog->scheduled_at->isFuture()) {
                Log::info('Blog scheduled time has not arrived yet', [
                    'blog_id' => $this->blogId,
                    'scheduled_at' => $blog->scheduled_at,
                    'current_time' => now()
                ]);
                DB::rollBack();
                return;
            }

            $blog->update([
                'status' => 'published',
                'published_at' => now(),
                'scheduled_at' => null
            ]);

            DB::commit();

            $searchService->indexBlog($blog);
            $cacheService->invalidateBlog($blog->id);

            $emailService->sendBlogPublishedNotification($blog);

            Log::info('Scheduled blog published successfully', [
                'blog_id' => $this->blogId,
                'title' => $blog->title,
                'author_id' => $blog->author_id,
                'published_at' => $blog->published_at
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Scheduled blog publishing failed', [
                'blog_id' => $this->blogId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Scheduled blog publishing job permanently failed', [
            'blog_id' => $this->blogId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);

        try {
            $blog = Blog::find($this->blogId);
            if ($blog && $blog->status === 'scheduled') {
                $blog->update(['status' => 'draft']);
                
                Log::info('Reverted scheduled blog to draft status', [
                    'blog_id' => $this->blogId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to revert scheduled blog to draft', [
                'blog_id' => $this->blogId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }
}
