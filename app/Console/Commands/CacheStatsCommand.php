<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class CacheStatsCommand extends Command
{
    protected $signature = 'blog:cache-stats 
                            {--detailed : Show detailed cache information}';

    protected $description = 'Display blog application cache statistics';

    public function handle()
    {
        $detailed = $this->option('detailed');

        try {
            $this->info('Blog Application Cache Statistics');
            $this->line('=====================================');

            $this->displayRedisInfo();
            $this->newLine();
            $this->displayCacheKeys();

            if ($detailed) {
                $this->newLine();
                $this->displayDetailedStats();
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to retrieve cache stats: ' . $e->getMessage());
            return 1;
        }
    }

    private function displayRedisInfo(): void
    {
        try {
            $redis = Redis::connection();
            $info = $redis->info();

            $this->info('Redis Server Information:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Redis Version', $info['redis_version'] ?? 'N/A'],
                    ['Used Memory', $this->formatBytes($info['used_memory'] ?? 0)],
                    ['Connected Clients', $info['connected_clients'] ?? 'N/A'],
                    ['Total Commands Processed', number_format($info['total_commands_processed'] ?? 0)],
                    ['Keyspace Hits', number_format($info['keyspace_hits'] ?? 0)],
                    ['Keyspace Misses', number_format($info['keyspace_misses'] ?? 0)],
                    ['Hit Rate', $this->calculateHitRate($info)],
                ]
            );
        } catch (\Exception $e) {
            $this->warn('Could not retrieve Redis info: ' . $e->getMessage());
        }
    }

    private function displayCacheKeys(): void
    {
        try {
            $redis = Redis::connection();
            $prefix = config('cache.prefix', '') . config('app.name', 'blog') . ':';
            
            $blogKeys = $redis->keys($prefix . 'blog:*');
            $blogListKeys = $redis->keys($prefix . 'blogs:list:*');
            $commentKeys = $redis->keys($prefix . 'comments:*');
            $tagKeys = $redis->keys($prefix . 'tags:*');
            $searchKeys = $redis->keys($prefix . 'search:*');

            $this->info('Cache Key Statistics:');
            $this->table(
                ['Cache Type', 'Key Count', 'Example Keys'],
                [
                    ['Individual Blogs', count($blogKeys), $this->getExampleKeys($blogKeys, 3)],
                    ['Blog Lists', count($blogListKeys), $this->getExampleKeys($blogListKeys, 3)],
                    ['Comments', count($commentKeys), $this->getExampleKeys($commentKeys, 3)],
                    ['Tags', count($tagKeys), $this->getExampleKeys($tagKeys, 3)],
                    ['Search Results', count($searchKeys), $this->getExampleKeys($searchKeys, 3)],
                ]
            );
        } catch (\Exception $e) {
            $this->warn('Could not retrieve cache keys: ' . $e->getMessage());
        }
    }

    private function displayDetailedStats(): void
    {
        try {
            $redis = Redis::connection();
            $prefix = config('cache.prefix', '') . config('app.name', 'blog') . ':';
            
            $this->info('Detailed Cache Analysis:');
            
            $blogKeys = $redis->keys($prefix . 'blog:*');
            if (!empty($blogKeys)) {
                $totalSize = 0;
                $sampleKeys = array_slice($blogKeys, 0, 10);
                
                foreach ($sampleKeys as $key) {
                    $size = strlen($redis->get($key) ?? '');
                    $totalSize += $size;
                }
                
                $avgSize = count($sampleKeys) > 0 ? $totalSize / count($sampleKeys) : 0;
                $estimatedTotalSize = $avgSize * count($blogKeys);
                
                $this->line("Blog Cache Analysis:");
                $this->line("- Total blog cache keys: " . count($blogKeys));
                $this->line("- Average cache entry size: " . $this->formatBytes($avgSize));
                $this->line("- Estimated total size: " . $this->formatBytes($estimatedTotalSize));
            }

            $this->displayCacheTTLInfo();
        } catch (\Exception $e) {
            $this->warn('Could not retrieve detailed stats: ' . $e->getMessage());
        }
    }

    private function displayCacheTTLInfo(): void
    {
        $this->newLine();
        $this->info('Cache TTL Configuration:');
        
        $ttlConfig = config('cache.ttl', []);
        $rows = [];
        
        foreach ($ttlConfig as $type => $ttl) {
            $rows[] = [
                ucfirst(str_replace('_', ' ', $type)),
                $this->formatDuration($ttl)
            ];
        }
        
        $this->table(['Cache Type', 'TTL'], $rows);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' minutes';
        } else {
            return round($seconds / 3600, 1) . ' hours';
        }
    }

    private function calculateHitRate(array $info): string
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        if ($total === 0) {
            return 'N/A';
        }
        
        return round(($hits / $total) * 100, 2) . '%';
    }

    private function getExampleKeys(array $keys, int $limit): string
    {
        if (empty($keys)) {
            return 'None';
        }
        
        $examples = array_slice($keys, 0, $limit);
        $examples = array_map(function ($key) {
            return basename($key);
        }, $examples);
        
        $result = implode(', ', $examples);
        
        if (count($keys) > $limit) {
            $result .= '... (+' . (count($keys) - $limit) . ' more)';
        }
        
        return $result;
    }
}
