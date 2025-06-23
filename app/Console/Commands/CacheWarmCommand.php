<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CacheService;
use App\Models\Blog;
use App\Models\Tag;
use Illuminate\Http\Request;

class CacheWarmCommand extends Command
{
    protected $signature = 'blog:cache-warm 
                            {--type=all : Type of cache to warm (all, popular, recent, tags)}
                            {--limit=50 : Number of items to cache}';

    protected $description = 'Warm up blog application cache with frequently accessed data';

    public function handle(CacheService $cacheService)
    {
        $type = $this->option('type');
        $limit = (int) $this->option('limit');

        $this->info('Warming up cache...');

        try {
            switch ($type) {
                case 'popular':
                    $this->warmPopularBlogs($cacheService, $limit);
                    break;

                case 'recent':
                    $this->warmRecentBlogs($cacheService, $limit);
                    break;

                case 'tags':
                    $this->warmPopularTags($cacheService, $limit);
                    break;

                case 'all':
                default:
                    $this->warmPopularBlogs($cacheService, $limit);
                    $this->warmRecentBlogs($cacheService, $limit);
                    $this->warmPopularTags($cacheService, $limit);
                    break;
            }

            $this->info('Cache warming completed successfully.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Cache warming failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function warmPopularBlogs(CacheService $cacheService, int $limit): void
    {
        $this->line('Warming popular blogs cache...');

        $popularBlogs = Blog::with(['author', 'tags'])
                           ->published()
                           ->orderBy('views_count', 'desc')
                           ->limit($limit)
                           ->get();

        foreach ($popularBlogs as $blog) {
            $cacheService->setBlog($blog->id, $blog);
        }

        $cacheKey = 'popular_' . md5("limit:{$limit}");
        $cacheService->setBlogList($cacheKey, $popularBlogs, ['popular']);

        $this->info("Cached {$popularBlogs->count()} popular blogs.");
    }

    private function warmRecentBlogs(CacheService $cacheService, int $limit): void
    {
        $this->line('Warming recent blogs cache...');

        $recentBlogs = Blog::with(['author', 'tags'])
                          ->published()
                          ->orderBy('published_at', 'desc')
                          ->limit($limit)
                          ->get();

        foreach ($recentBlogs as $blog) {
            $cacheService->setBlog($blog->id, $blog);
        }

        $cacheKey = 'recent_' . md5("limit:{$limit}");
        $cacheService->setBlogList($cacheKey, $recentBlogs, ['recent']);

        $this->info("Cached {$recentBlogs->count()} recent blogs.");
    }

    private function warmPopularTags(CacheService $cacheService, int $limit): void
    {
        $this->line('Warming popular tags cache...');

        $popularTags = Tag::popular($limit)->get();
        $cacheService->setTags('popular', $popularTags);

        $allTags = Tag::orderBy('name')->get();
        $cacheService->setTags('all', $allTags);

        $this->info("Cached {$popularTags->count()} popular tags and {$allTags->count()} total tags.");
    }
}
