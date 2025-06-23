<?php

namespace App\Observers;

use App\Models\Tag;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class TagCacheObserver
{
    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function created(Tag $tag): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            $this->cacheService->invalidateTags();

            Log::info('Cache invalidated after tag creation', [
                'tag_id' => $tag->id,
                'tag_name' => $tag->name
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed after tag creation', [
                'tag_id' => $tag->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updated(Tag $tag): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            $this->cacheService->invalidateTags();

            if ($tag->wasChanged(['name', 'slug'])) {
                $this->cacheService->invalidateByTags(['blog_lists']);
            }

            Log::info('Cache invalidated after tag update', [
                'tag_id' => $tag->id,
                'changed_fields' => array_keys($tag->getDirty())
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed after tag update', [
                'tag_id' => $tag->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deleted(Tag $tag): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            $this->cacheService->invalidateTags();
            $this->cacheService->invalidateByTags(['blog_lists']);

            Log::info('Cache invalidated after tag deletion', [
                'tag_id' => $tag->id,
                'tag_name' => $tag->name
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed after tag deletion', [
                'tag_id' => $tag->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
