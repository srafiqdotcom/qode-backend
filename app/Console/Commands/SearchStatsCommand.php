<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\Blog;

class SearchStatsCommand extends Command
{
    protected $signature = 'search:stats 
                            {--detailed : Show detailed search statistics}';

    protected $description = 'Display search index statistics and performance metrics';

    public function handle()
    {
        $detailed = $this->option('detailed');

        try {
            $this->info('Search Index Statistics');
            $this->line('==========================');

            $this->displayIndexStats();
            $this->newLine();
            $this->displaySearchTermStats();

            if ($detailed) {
                $this->newLine();
                $this->displayDetailedStats();
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to retrieve search stats: ' . $e->getMessage());
            return 1;
        }
    }

    private function displayIndexStats(): void
    {
        $prefix = config('app.name', 'blog') . ':search:';
        
        $blogKeys = Redis::keys($prefix . 'blogs:*');
        $termKeys = Redis::keys($prefix . 'terms:*');
        $tagKeys = Redis::keys($prefix . 'tags:*');
        $authorKeys = Redis::keys($prefix . 'authors:*');
        $suggestionKeys = Redis::keys($prefix . 'suggestions:*');

        $totalBlogs = Blog::published()->count();
        $indexedBlogs = count($blogKeys);
        $indexCoverage = $totalBlogs > 0 ? round(($indexedBlogs / $totalBlogs) * 100, 2) : 0;

        $this->info('Index Overview:');
        $this->table(
            ['Metric', 'Count', 'Details'],
            [
                ['Total Published Blogs', $totalBlogs, 'In database'],
                ['Indexed Blogs', $indexedBlogs, "Coverage: {$indexCoverage}%"],
                ['Search Terms', count($termKeys), 'Unique indexed terms'],
                ['Tag Indexes', count($tagKeys), 'Tags with blogs'],
                ['Author Indexes', count($authorKeys), 'Authors with blogs'],
                ['Suggestion Keys', count($suggestionKeys), 'Auto-complete data'],
            ]
        );
    }

    private function displaySearchTermStats(): void
    {
        $prefix = config('app.name', 'blog') . ':search:';
        $termKeys = Redis::keys($prefix . 'terms:*');

        if (empty($termKeys)) {
            $this->warn('No search terms found in index.');
            return;
        }

        $this->info('Search Terms Analysis:');
        
        $termStats = [];
        $sampleSize = min(count($termKeys), 20);
        $sampleKeys = array_slice($termKeys, 0, $sampleSize);

        foreach ($sampleKeys as $key) {
            $term = str_replace($prefix . 'terms:', '', $key);
            $blogCount = Redis::zcard($key);
            $termStats[] = [
                'term' => $term,
                'blogs' => $blogCount
            ];
        }

        usort($termStats, function ($a, $b) {
            return $b['blogs'] <=> $a['blogs'];
        });

        $topTerms = array_slice($termStats, 0, 10);
        
        $this->table(
            ['Search Term', 'Blog Count'],
            array_map(function ($stat) {
                return [$stat['term'], $stat['blogs']];
            }, $topTerms)
        );

        if (count($termKeys) > $sampleSize) {
            $this->line("Showing top 10 terms from {$sampleSize} sampled terms (total: " . count($termKeys) . ")");
        }
    }

    private function displayDetailedStats(): void
    {
        $this->info('Detailed Analysis:');
        
        $this->displayTagStats();
        $this->newLine();
        $this->displayAuthorStats();
        $this->newLine();
        $this->displayIndexHealth();
    }

    private function displayTagStats(): void
    {
        $prefix = config('app.name', 'blog') . ':search:';
        $tagKeys = Redis::keys($prefix . 'tags:*');

        if (empty($tagKeys)) {
            $this->line('No tag indexes found.');
            return;
        }

        $this->line('Tag Index Analysis:');
        
        $tagStats = [];
        foreach (array_slice($tagKeys, 0, 10) as $key) {
            $tag = str_replace($prefix . 'tags:', '', $key);
            $blogCount = Redis::zcard($key);
            $tagStats[] = [$tag, $blogCount];
        }

        usort($tagStats, function ($a, $b) {
            return $b[1] <=> $a[1];
        });

        $this->table(['Tag', 'Blog Count'], array_slice($tagStats, 0, 5));
    }

    private function displayAuthorStats(): void
    {
        $prefix = config('app.name', 'blog') . ':search:';
        $authorKeys = Redis::keys($prefix . 'authors:*');

        if (empty($authorKeys)) {
            $this->line('No author indexes found.');
            return;
        }

        $this->line('Author Index Analysis:');
        
        $authorStats = [];
        foreach ($authorKeys as $key) {
            $authorId = str_replace($prefix . 'authors:', '', $key);
            $blogCount = Redis::zcard($key);
            $authorStats[] = ["Author {$authorId}", $blogCount];
        }

        usort($authorStats, function ($a, $b) {
            return $b[1] <=> $a[1];
        });

        $this->table(['Author', 'Blog Count'], array_slice($authorStats, 0, 5));
    }

    private function displayIndexHealth(): void
    {
        $this->line('Index Health Check:');
        
        $prefix = config('app.name', 'blog') . ':search:';
        $blogKeys = Redis::keys($prefix . 'blogs:*');
        
        $healthStats = [
            'total_keys' => count($blogKeys),
            'expired_keys' => 0,
            'empty_keys' => 0,
            'valid_keys' => 0,
        ];

        $sampleSize = min(count($blogKeys), 50);
        $sampleKeys = array_slice($blogKeys, 0, $sampleSize);

        foreach ($sampleKeys as $key) {
            $ttl = Redis::ttl($key);
            $data = Redis::hgetall($key);
            
            if ($ttl === -2) {
                $healthStats['expired_keys']++;
            } elseif (empty($data)) {
                $healthStats['empty_keys']++;
            } else {
                $healthStats['valid_keys']++;
            }
        }

        $healthPercentage = $sampleSize > 0 ? 
            round(($healthStats['valid_keys'] / $sampleSize) * 100, 2) : 0;

        $this->table(
            ['Health Metric', 'Count', 'Percentage'],
            [
                ['Valid Keys', $healthStats['valid_keys'], $healthPercentage . '%'],
                ['Empty Keys', $healthStats['empty_keys'], 
                 $sampleSize > 0 ? round(($healthStats['empty_keys'] / $sampleSize) * 100, 2) . '%' : '0%'],
                ['Expired Keys', $healthStats['expired_keys'], 
                 $sampleSize > 0 ? round(($healthStats['expired_keys'] / $sampleSize) * 100, 2) . '%' : '0%'],
            ]
        );

        if ($sampleSize < count($blogKeys)) {
            $this->line("Health check based on {$sampleSize} sampled keys out of " . count($blogKeys) . " total keys.");
        }

        if ($healthPercentage < 80) {
            $this->warn('Index health is below 80%. Consider rebuilding the search index.');
        } else {
            $this->info('Index health is good.');
        }
    }
}
