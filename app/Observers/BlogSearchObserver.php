<?php

namespace App\Observers;

use App\Models\Blog;
use App\Services\SearchService;
use Illuminate\Support\Facades\Log;

class BlogSearchObserver
{
    protected SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function created(Blog $blog): void
    {
        if (!config('blog_cache.enabled', true)) {
            return;
        }

        try {
            if ($blog->isPublished()) {
                $this->searchService->indexBlog($blog);
                
                Log::info('Blog indexed for search after creation', [
                    'blog_id' => $blog->id,
                    'blog_status' => $blog->status
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Blog search indexing failed after creation', [
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
            if ($blog->isPublished()) {
                $this->searchService->indexBlog($blog);
                
                Log::info('Blog search index updated', [
                    'blog_id' => $blog->id,
                    'changed_fields' => array_keys($blog->getDirty())
                ]);
            } else {
                $this->searchService->removeBlogFromIndex($blog->id);
                
                Log::info('Blog removed from search index (unpublished)', [
                    'blog_id' => $blog->id,
                    'status' => $blog->status
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Blog search index update failed', [
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
            $this->searchService->removeBlogFromIndex($blog->id);
            
            Log::info('Blog removed from search index after deletion', [
                'blog_id' => $blog->id
            ]);
        } catch (\Exception $e) {
            Log::error('Blog search index removal failed after deletion', [
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
            if ($blog->isPublished()) {
                $this->searchService->indexBlog($blog);
                
                Log::info('Blog re-indexed for search after restoration', [
                    'blog_id' => $blog->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Blog search re-indexing failed after restoration', [
                'blog_id' => $blog->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
