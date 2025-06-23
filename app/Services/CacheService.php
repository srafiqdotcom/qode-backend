<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CacheService
{
    protected array $defaultTags;
    protected int $defaultTtl;
    protected string $keyPrefix;

    public function __construct()
    {
        $this->defaultTags = ['blog_app'];
        $this->defaultTtl = config('cache.ttl.default', 3600);
        $this->keyPrefix = config('app.name', 'blog') . ':';
    }

    public function getBlogList(string $cacheKey, array $tags = [], int $ttl = null): mixed
    {
        try {
            $fullKey = $this->buildKey('blogs:list:' . $cacheKey);

            // **qode** Simplified cache without tags for better performance
            return Cache::get($fullKey);
        } catch (\Exception $e) {
            Log::error('Cache get failed for blog list', [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function setBlogList(string $cacheKey, $data, array $tags = [], int $ttl = null): bool
    {
        try {
            $fullKey = $this->buildKey('blogs:list:' . $cacheKey);

            // **qode** Simplified cache without tags for better performance
            Cache::put($fullKey, $data, $ttl ?? $this->getTtl('blog_list'));

            Log::info('Blog list cached', [
                'key' => $cacheKey,
                'ttl' => $ttl ?? $this->getTtl('blog_list')
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Cache set failed for blog list', [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getBlog(int $blogId): mixed
    {
        try {
            $fullKey = $this->buildKey("blog:{$blogId}");

            // **qode** Simplified cache without tags
            return Cache::get($fullKey);
        } catch (\Exception $e) {
            Log::error('Cache get failed for blog', [
                'blog_id' => $blogId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function setBlog(int $blogId, $data, int $ttl = null): bool
    {
        try {
            $fullKey = $this->buildKey("blog:{$blogId}");

            // **qode** Simplified cache without tags
            Cache::put($fullKey, $data, $ttl ?? $this->getTtl('blog_detail'));

            Log::info('Blog cached', [
                'blog_id' => $blogId,
                'ttl' => $ttl ?? $this->getTtl('blog_detail')
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Cache set failed for blog', [
                'blog_id' => $blogId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getComments(int $blogId, string $cacheKey): mixed
    {
        try {
            $fullKey = $this->buildKey("comments:blog_{$blogId}:{$cacheKey}");
            $cacheTags = array_merge($this->defaultTags, ['comments', "blog_{$blogId}_comments"]);
            
            return Cache::tags($cacheTags)->get($fullKey);
        } catch (\Exception $e) {
            Log::error('Cache get failed for comments', [
                'blog_id' => $blogId,
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function setComments(int $blogId, string $cacheKey, $data, int $ttl = null): bool
    {
        try {
            $fullKey = $this->buildKey("comments:blog_{$blogId}:{$cacheKey}");
            $cacheTags = array_merge($this->defaultTags, ['comments', "blog_{$blogId}_comments"]);
            
            Cache::tags($cacheTags)->put($fullKey, $data, $ttl ?? $this->getTtl('comments'));
            
            return true;
        } catch (\Exception $e) {
            Log::error('Cache set failed for comments', [
                'blog_id' => $blogId,
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getTags(string $cacheKey = 'all'): mixed
    {
        try {
            $fullKey = $this->buildKey("tags:{$cacheKey}");
            $cacheTags = array_merge($this->defaultTags, ['tags']);
            
            return Cache::tags($cacheTags)->get($fullKey);
        } catch (\Exception $e) {
            Log::error('Cache get failed for tags', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function setTags(string $cacheKey, $data, int $ttl = null): bool
    {
        try {
            $fullKey = $this->buildKey("tags:{$cacheKey}");
            $cacheTags = array_merge($this->defaultTags, ['tags']);
            
            Cache::tags($cacheTags)->put($fullKey, $data, $ttl ?? $this->getTtl('tags'));
            
            return true;
        } catch (\Exception $e) {
            Log::error('Cache set failed for tags', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getSearchResults(string $query, array $filters = []): mixed
    {
        try {
            $cacheKey = $this->buildSearchKey($query, $filters);
            $fullKey = $this->buildKey("search:{$cacheKey}");
            $cacheTags = array_merge($this->defaultTags, ['search', 'blogs']);
            
            return Cache::tags($cacheTags)->get($fullKey);
        } catch (\Exception $e) {
            Log::error('Cache get failed for search', [
                'query' => $query,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function setSearchResults(string $query, array $filters, $data, int $ttl = null): bool
    {
        try {
            $cacheKey = $this->buildSearchKey($query, $filters);
            $fullKey = $this->buildKey("search:{$cacheKey}");
            $cacheTags = array_merge($this->defaultTags, ['search', 'blogs']);
            
            Cache::tags($cacheTags)->put($fullKey, $data, $ttl ?? $this->getTtl('search'));
            
            return true;
        } catch (\Exception $e) {
            Log::error('Cache set failed for search', [
                'query' => $query,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }



    public function invalidateBlog(int $blogId): bool
    {
        try {
            $this->invalidateByTags(["blog_{$blogId}"]);
            $this->invalidateByTags(['blog_lists']);
            $this->invalidateByTags(['search']);
            
            Log::info('Blog cache invalidated', ['blog_id' => $blogId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Blog cache invalidation failed', [
                'blog_id' => $blogId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function invalidateComments(int $blogId): bool
    {
        try {
            $this->invalidateByTags(["blog_{$blogId}_comments"]);
            
            Log::info('Comments cache invalidated', ['blog_id' => $blogId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Comments cache invalidation failed', [
                'blog_id' => $blogId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function invalidateTags(): bool
    {
        try {
            $this->invalidateByTags(['tags']);
            $this->invalidateByTags(['blog_lists']);
            
            Log::info('Tags cache invalidated');
            return true;
        } catch (\Exception $e) {
            Log::error('Tags cache invalidation failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function invalidateByTags(array $tags): bool
    {
        try {
            Cache::tags($tags)->flush();
            return true;
        } catch (\Exception $e) {
            Log::error('Cache invalidation by tags failed', [
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function flushAll(): bool
    {
        try {
            Cache::tags($this->defaultTags)->flush();
            Log::info('All blog cache flushed');
            return true;
        } catch (\Exception $e) {
            Log::error('Cache flush all failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function buildKey(string $key): string
    {
        return $this->keyPrefix . $key;
    }

    private function buildSearchKey(string $query, array $filters): string
    {
        $filterString = empty($filters) ? '' : ':' . md5(serialize($filters));
        return md5($query) . $filterString;
    }

    private function getTtl(string $type): int
    {
        return config("cache.ttl.{$type}", $this->defaultTtl);
    }
}
