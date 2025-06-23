<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blog;
use App\Jobs\MigrateImageToS3Job;
use Illuminate\Support\Facades\Storage;

class MigrateImagesToS3Command extends Command
{
    protected $signature = 'images:migrate-to-s3 
                            {--batch-size=50 : Number of images to process in each batch}
                            {--delay=5 : Delay in seconds between batches}
                            {--dry-run : Show what would be migrated without actually doing it}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Migrate all local blog images to S3 storage';

    public function handle()
    {
        $batchSize = (int) $this->option('batch-size');
        $delay = (int) $this->option('delay');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (!Storage::disk('s3')->exists('')) {
            $this->error('S3 storage is not properly configured. Please check your AWS credentials.');
            return 1;
        }

        $blogsWithImages = Blog::whereNotNull('image_path')
                              ->where('image_path', '!=', '')
                              ->get(['id', 'image_path', 'title']);

        if ($blogsWithImages->isEmpty()) {
            $this->info('No blog images found to migrate.');
            return 0;
        }

        $totalImages = $blogsWithImages->count();
        $localImages = $blogsWithImages->filter(function ($blog) {
            return Storage::disk('local')->exists($blog->image_path);
        });

        $this->info("Found {$totalImages} blogs with images.");
        $this->info("Found {$localImages->count()} images in local storage to migrate.");

        if ($localImages->isEmpty()) {
            $this->info('No local images found to migrate.');
            return 0;
        }

        if ($dryRun) {
            $this->info('DRY RUN - The following images would be migrated:');
            $localImages->each(function ($blog) {
                $this->line("- Blog ID: {$blog->id} | Title: {$blog->title} | Path: {$blog->image_path}");
            });
            return 0;
        }

        if (!$force) {
            if (!$this->confirm("Are you sure you want to migrate {$localImages->count()} images to S3?")) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        $this->info('Starting migration to S3...');
        $progressBar = $this->output->createProgressBar($localImages->count());
        $progressBar->start();

        $batches = $localImages->chunk($batchSize);
        $jobsDispatched = 0;

        foreach ($batches as $batchIndex => $batch) {
            foreach ($batch as $blog) {
                MigrateImageToS3Job::dispatch($blog->id, $blog->image_path)
                                  ->onQueue('s3-migration');
                $jobsDispatched++;
                $progressBar->advance();
            }

            if ($batchIndex < $batches->count() - 1) {
                $this->newLine();
                $this->info("Batch " . ($batchIndex + 1) . " dispatched. Waiting {$delay} seconds...");
                sleep($delay);
            }
        }

        $progressBar->finish();
        $this->newLine(2);
        $this->info("Successfully dispatched {$jobsDispatched} migration jobs to the 's3-migration' queue.");
        $this->info('Monitor the queue with: php artisan queue:work --queue=s3-migration');
        $this->info('Check migration status with: php artisan images:migration-status');

        return 0;
    }
}
