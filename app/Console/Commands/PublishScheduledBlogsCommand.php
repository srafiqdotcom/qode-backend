<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blog;
use App\Jobs\PublishScheduledBlogJob;

class PublishScheduledBlogsCommand extends Command
{
    protected $signature = 'blogs:publish-scheduled 
                            {--dry-run : Show what would be published without actually doing it}
                            {--limit=50 : Maximum number of blogs to process}';

    protected $description = 'Publish blogs that are scheduled for the current time';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $scheduledBlogs = Blog::where('status', 'scheduled')
                             ->where('scheduled_at', '<=', now())
                             ->whereNotNull('scheduled_at')
                             ->limit($limit)
                             ->get(['id', 'title', 'scheduled_at', 'author_id']);

        if ($scheduledBlogs->isEmpty()) {
            $this->info('No blogs scheduled for publishing at this time.');
            return 0;
        }

        $this->info("Found {$scheduledBlogs->count()} blog(s) scheduled for publishing:");

        if ($dryRun) {
            $this->table(
                ['ID', 'Title', 'Scheduled At', 'Author ID'],
                $scheduledBlogs->map(function ($blog) {
                    return [
                        $blog->id,
                        substr($blog->title, 0, 50) . (strlen($blog->title) > 50 ? '...' : ''),
                        $blog->scheduled_at->format('Y-m-d H:i:s'),
                        $blog->author_id
                    ];
                })->toArray()
            );

            $this->warn('DRY RUN - No blogs were actually published.');
            return 0;
        }

        $progressBar = $this->output->createProgressBar($scheduledBlogs->count());
        $progressBar->start();

        $jobsDispatched = 0;

        foreach ($scheduledBlogs as $blog) {
            try {
                PublishScheduledBlogJob::dispatch($blog->id)
                                      ->onQueue('blog-publishing')
                                      ->delay(now()->addSeconds($jobsDispatched * 2));

                $jobsDispatched++;
                $progressBar->advance();
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to dispatch job for blog {$blog->id}: " . $e->getMessage());
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Successfully dispatched {$jobsDispatched} publishing jobs to the 'blog-publishing' queue.");
        $this->info('Monitor the queue with: php artisan queue:work --queue=blog-publishing');

        return 0;
    }
}
