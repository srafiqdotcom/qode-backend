<?php

namespace App\Observers;

use App\Models\Comment;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class CommentCacheObserver
{
    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function created(Comment $comment): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            $this->cacheService->invalidateComments($comment->blog_id);

            Log::info('Cache invalidated after comment creation', [
                'comment_id' => $comment->id,
                'blog_id' => $comment->blog_id
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed after comment creation', [
                'comment_id' => $comment->id,
                'blog_id' => $comment->blog_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updated(Comment $comment): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            $this->cacheService->invalidateComments($comment->blog_id);

            if ($comment->wasChanged('status')) {
                Log::info('Comment status changed, invalidating cache', [
                    'comment_id' => $comment->id,
                    'blog_id' => $comment->blog_id,
                    'old_status' => $comment->getOriginal('status'),
                    'new_status' => $comment->status
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed after comment update', [
                'comment_id' => $comment->id,
                'blog_id' => $comment->blog_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deleted(Comment $comment): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            $this->cacheService->invalidateComments($comment->blog_id);

            Log::info('Cache invalidated after comment deletion', [
                'comment_id' => $comment->id,
                'blog_id' => $comment->blog_id
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed after comment deletion', [
                'comment_id' => $comment->id,
                'blog_id' => $comment->blog_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function restored(Comment $comment): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            $this->cacheService->invalidateComments($comment->blog_id);

            Log::info('Cache invalidated after comment restoration', [
                'comment_id' => $comment->id,
                'blog_id' => $comment->blog_id
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed after comment restoration', [
                'comment_id' => $comment->id,
                'blog_id' => $comment->blog_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
