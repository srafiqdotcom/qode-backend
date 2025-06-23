<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blog;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;

class WarmBlogCache extends Command
{
    protected $signature = 'cache:warm-blogs {--limit=100 : Number of recent blogs to cache}';
    protected $description = 'Warm up the blog cache with recent blog data';

    public function handle()
    {
        $limit = $this->option('limit');
        $cacheService = app(CacheService::class);

        $this->info("🔥 Warming blog cache with {$limit} recent blogs...");

        // **qode** Cache recent blog lists with different pagination and pages
        $perPageOptions = [5, 10, 15, 20];
        $pagesToCache = [1, 2, 3, 4, 5, 10, 15, 20]; // Cache popular page numbers

        foreach ($perPageOptions as $perPage) {
            foreach ($pagesToCache as $page) {
                $this->info("📄 Caching blog list (per_page: {$perPage}, page: {$page})...");

                $blogs = Blog::with(['author:id,name,email', 'tags:id,name,slug'])
                             ->select('id', 'uuid', 'title', 'slug', 'excerpt', 'image_path', 'image_alt',
                                     'status', 'published_at', 'author_id', 'views_count', 'comments_count', 'created_at')
                             ->published()
                             ->orderBy('created_at', 'desc')
                             ->paginate($perPage, ['*'], 'page', $page);

                // Include page in cache key
                $cacheKey = md5(serialize([
                    'per_page' => $perPage,
                    'page' => $page,
                    'status' => 'published',
                    'order_by' => 'created_at',
                    'order' => 'desc'
                ]));
                $cacheService->setBlogList($cacheKey, $blogs);

                $this->line("✅ Cached blog list (per_page: {$perPage}, page: {$page}) - {$blogs->count()} blogs");

                // Break if we've reached the end of available data
                if ($blogs->count() < $perPage) {
                    $this->line("🔚 Reached end of data for per_page: {$perPage}");
                    break;
                }
            }
        }

        // **qode** Cache individual recent blogs
        $this->info("📝 Caching individual blog details...");
        
        $recentBlogs = Blog::with(['author', 'tags', 'approvedComments.user'])
                          ->published()
                          ->orderBy('created_at', 'desc')
                          ->limit($limit)
                          ->get();

        foreach ($recentBlogs as $blog) {
            $cacheService->setBlog($blog->id, $blog);
        }

        $this->line("✅ Cached {$recentBlogs->count()} individual blog details");

        // **qode** Cache popular search terms
        $this->info("🔍 Caching popular search results...");
        
        $popularTerms = ['laravel', 'php', 'javascript', 'docker', 'redis', 'mysql', 'vue', 'react'];
        
        foreach ($popularTerms as $term) {
            $searchResults = Blog::with(['author:id,name', 'tags:id,name,slug'])
                                ->select('id', 'uuid', 'title', 'slug', 'excerpt', 'image_path',
                                        'status', 'published_at', 'author_id', 'views_count', 'comments_count')
                                ->published()
                                ->where(function ($q) use ($term) {
                                    $q->where('title', 'like', "%{$term}%")
                                      ->orWhere('excerpt', 'like', "%{$term}%")
                                      ->orWhereJsonContains('keywords', $term);
                                })
                                ->orderBy('published_at', 'desc')
                                ->paginate(15);

            $searchCacheKey = 'search:' . md5($term);
            Cache::put($searchCacheKey, $searchResults, 900); // 15 minutes
            
            $this->line("🔍 Cached search results for '{$term}' - {$searchResults->count()} results");
        }

        $this->info("🎉 Blog cache warming completed successfully!");
        
        return Command::SUCCESS;
    }
}
