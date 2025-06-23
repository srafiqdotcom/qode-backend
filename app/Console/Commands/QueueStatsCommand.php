<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueStatsCommand extends Command
{
    protected $signature = 'queue:stats 
                            {--detailed : Show detailed queue information}
                            {--failed : Show failed jobs information}';

    protected $description = 'Display queue statistics and job information';

    public function handle()
    {
        $detailed = $this->option('detailed');
        $showFailed = $this->option('failed');

        try {
            $this->info('Queue Statistics');
            $this->line('=================');

            $this->displayQueueSizes();
            $this->newLine();
            $this->displayJobStats();

            if ($showFailed) {
                $this->newLine();
                $this->displayFailedJobs();
            }

            if ($detailed) {
                $this->newLine();
                $this->displayDetailedStats();
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to retrieve queue stats: ' . $e->getMessage());
            return 1;
        }
    }

    private function displayQueueSizes(): void
    {
        $queues = [
            'default' => 'Default Queue',
            'emails' => 'Email Queue',
            'notifications' => 'Notifications',
            'blog-publishing' => 'Blog Publishing',
            'bulk-emails' => 'Bulk Emails',
            's3-migration' => 'S3 Migration'
        ];

        $this->info('Queue Sizes:');
        
        $queueData = [];
        foreach ($queues as $queue => $description) {
            try {
                $size = Redis::llen("queues:{$queue}");
                $queueData[] = [$description, $queue, $size];
            } catch (\Exception $e) {
                $queueData[] = [$description, $queue, 'Error'];
            }
        }

        $this->table(['Queue', 'Name', 'Pending Jobs'], $queueData);
    }

    private function displayJobStats(): void
    {
        try {
            $totalJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            
            $jobsByQueue = DB::table('jobs')
                            ->select('queue', DB::raw('count(*) as count'))
                            ->groupBy('queue')
                            ->get();

            $this->info('Job Statistics:');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Pending Jobs', $totalJobs],
                    ['Total Failed Jobs', $failedJobs],
                ]
            );

            if ($jobsByQueue->isNotEmpty()) {
                $this->newLine();
                $this->info('Jobs by Queue:');
                $this->table(
                    ['Queue', 'Pending Jobs'],
                    $jobsByQueue->map(function ($item) {
                        return [$item->queue, $item->count];
                    })->toArray()
                );
            }
        } catch (\Exception $e) {
            $this->warn('Could not retrieve job statistics: ' . $e->getMessage());
        }
    }

    private function displayFailedJobs(): void
    {
        try {
            $failedJobs = DB::table('failed_jobs')
                           ->orderBy('failed_at', 'desc')
                           ->limit(10)
                           ->get(['id', 'queue', 'payload', 'failed_at']);

            if ($failedJobs->isEmpty()) {
                $this->info('No failed jobs found.');
                return;
            }

            $this->info('Recent Failed Jobs (Last 10):');
            
            $failedJobsData = [];
            foreach ($failedJobs as $job) {
                $payload = json_decode($job->payload, true);
                $jobClass = $payload['displayName'] ?? 'Unknown';
                
                $failedJobsData[] = [
                    $job->id,
                    $job->queue,
                    $jobClass,
                    \Carbon\Carbon::parse($job->failed_at)->diffForHumans()
                ];
            }

            $this->table(
                ['ID', 'Queue', 'Job Class', 'Failed'],
                $failedJobsData
            );

            $this->line('Use "php artisan queue:retry all" to retry all failed jobs');
            $this->line('Use "php artisan queue:flush" to clear all failed jobs');
        } catch (\Exception $e) {
            $this->warn('Could not retrieve failed jobs: ' . $e->getMessage());
        }
    }

    private function displayDetailedStats(): void
    {
        $this->info('Detailed Queue Information:');
        
        try {
            $redis = Redis::connection();
            $info = $redis->info();
            
            $this->table(
                ['Redis Metric', 'Value'],
                [
                    ['Connected Clients', $info['connected_clients'] ?? 'N/A'],
                    ['Used Memory', $this->formatBytes($info['used_memory'] ?? 0)],
                    ['Total Commands', number_format($info['total_commands_processed'] ?? 0)],
                    ['Keyspace Hits', number_format($info['keyspace_hits'] ?? 0)],
                    ['Keyspace Misses', number_format($info['keyspace_misses'] ?? 0)],
                ]
            );
        } catch (\Exception $e) {
            $this->warn('Could not retrieve Redis information: ' . $e->getMessage());
        }

        try {
            $recentJobs = DB::table('jobs')
                           ->orderBy('created_at', 'desc')
                           ->limit(5)
                           ->get(['id', 'queue', 'payload', 'attempts', 'created_at']);

            if ($recentJobs->isNotEmpty()) {
                $this->newLine();
                $this->info('Recent Pending Jobs:');
                
                $recentJobsData = [];
                foreach ($recentJobs as $job) {
                    $payload = json_decode($job->payload, true);
                    $jobClass = $payload['displayName'] ?? 'Unknown';
                    
                    $recentJobsData[] = [
                        $job->id,
                        $job->queue,
                        $jobClass,
                        $job->attempts,
                        \Carbon\Carbon::parse($job->created_at)->diffForHumans()
                    ];
                }

                $this->table(
                    ['ID', 'Queue', 'Job Class', 'Attempts', 'Created'],
                    $recentJobsData
                );
            }
        } catch (\Exception $e) {
            $this->warn('Could not retrieve recent jobs: ' . $e->getMessage());
        }
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
}
