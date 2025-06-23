<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CacheService;

class CacheClearCommand extends Command
{
    protected $signature = 'blog:cache-clear 
                            {--type=all : Type of cache to clear (all, blogs, comments, tags, search)}
                            {--force : Skip confirmation}';

    protected $description = 'Clear blog application cache';

    public function handle(CacheService $cacheService)
    {
        $type = $this->option('type');
        $force = $this->option('force');

        if (!$force && !$this->confirm("Are you sure you want to clear {$type} cache?")) {
            $this->info('Cache clear cancelled.');
            return 0;
        }

        $this->info('Clearing cache...');

        try {
            switch ($type) {
                case 'blogs':
                    $cacheService->invalidateByTags(['blogs', 'blog_lists', 'blog_details']);
                    $this->info('Blog cache cleared successfully.');
                    break;

                case 'comments':
                    $cacheService->invalidateByTags(['comments']);
                    $this->info('Comments cache cleared successfully.');
                    break;

                case 'tags':
                    $cacheService->invalidateByTags(['tags']);
                    $this->info('Tags cache cleared successfully.');
                    break;

                case 'search':
                    $cacheService->invalidateByTags(['search']);
                    $this->info('Search cache cleared successfully.');
                    break;

                case 'all':
                default:
                    $cacheService->flushAll();
                    $this->info('All blog cache cleared successfully.');
                    break;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Cache clear failed: ' . $e->getMessage());
            return 1;
        }
    }
}
