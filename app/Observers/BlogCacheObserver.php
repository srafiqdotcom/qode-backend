<?php

namespace App\Observers;

use App\Models\Blog;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class BlogCacheObserver
{
    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function created(Blog $blog): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            $this->cacheService->invalidateByTags(['blog_lists']);
            $this->cacheService->invalidateByTags(['search']);
            
            if ($blog->isPublished()) {
                $this->cacheService->invalidateByTags(['recent']);
            }

            Log::info('Cache invalidated after blog creation', [
                'blog_id' => $blog->id,
                'blog_status' => $blog->status
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed after blog creation', [
                'blog_id' => $blog->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updated(Blog $blog): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            $this->cacheService->invalidateBlog($blog->id);

            if ($blog->wasChanged('status')) {
                $this->cacheService->invalidateByTags(['blog_lists']);
                
                if ($blog->status === 'published') {
                    $this->cacheService->invalidateByTags(['recent']);
                }
            }

            if ($blog->wasChanged(['title', 'excerpt', 'description', 'keywords'])) {
                $this->cacheService->invalidateByTags(['search']);
            }

            Log::info('Cache invalidated after blog update', [
                'blog_id' => $blog->id,
                'changed_fields' => array_keys($blog->getDirty())
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed after blog update', [
                'blog_id' => $blog->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deleted(Blog $blog): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            $this->cacheService->invalidateBlog($blog->id);
            $this->cacheService->invalidateComments($blog->id);

            Log::info('Cache invalidated after blog deletion', [
                'blog_id' => $blog->id
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed after blog deletion', [
                'blog_id' => $blog->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function restored(Blog $blog): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            $this->cacheService->invalidateByTags(['blog_lists']);
            $this->cacheService->invalidateByTags(['search']);

            Log::info('Cache invalidated after blog restoration', [
                'blog_id' => $blog->id
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed after blog restoration', [
                'blog_id' => $blog->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
