<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class S3MigrationStatusCommand extends Command
{
    protected $signature = 'images:migration-status 
                            {--detailed : Show detailed information about each image}';

    protected $description = 'Check the status of S3 migration for blog images';

    public function handle()
    {
        $detailed = $this->option('detailed');

        $blogsWithImages = Blog::whereNotNull('image_path')
                              ->where('image_path', '!=', '')
                              ->get(['id', 'image_path', 'title']);

        if ($blogsWithImages->isEmpty()) {
            $this->info('No blog images found.');
            return 0;
        }

        $totalImages = $blogsWithImages->count();
        $localOnly = 0;
        $s3Only = 0;
        $both = 0;
        $missing = 0;

        $this->info('Checking migration status...');
        $progressBar = $this->output->createProgressBar($totalImages);
        $progressBar->start();

        $detailedResults = [];

        foreach ($blogsWithImages as $blog) {
            $localExists = Storage::disk('local')->exists($blog->image_path);
            $s3Exists = Storage::disk('s3')->exists($blog->image_path);

            if ($localExists && $s3Exists) {
                $both++;
                $status = 'Both Local & S3';
            } elseif ($localExists && !$s3Exists) {
                $localOnly++;
                $status = 'Local Only';
            } elseif (!$localExists && $s3Exists) {
                $s3Only++;
                $status = 'S3 Only (Migrated)';
            } else {
                $missing++;
                $status = 'Missing';
            }

            if ($detailed) {
                $detailedResults[] = [
                    'ID' => $blog->id,
                    'Title' => substr($blog->title, 0, 30) . '...',
                    'Path' => $blog->image_path,
                    'Status' => $status,
                ];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary table
        $this->info('Migration Status Summary:');
        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['Total Images', $totalImages, '100%'],
                ['Local Only (Pending)', $localOnly, round(($localOnly / $totalImages) * 100, 1) . '%'],
                ['S3 Only (Migrated)', $s3Only, round(($s3Only / $totalImages) * 100, 1) . '%'],
                ['Both Local & S3', $both, round(($both / $totalImages) * 100, 1) . '%'],
                ['Missing', $missing, round(($missing / $totalImages) * 100, 1) . '%'],
            ]
        );

        // Queue status
        $pendingJobs = DB::table('jobs')->where('queue', 's3-migration')->count();
        $failedJobs = DB::table('failed_jobs')->where('queue', 's3-migration')->count();

        $this->newLine();
        $this->info('Queue Status:');
        $this->line("Pending migration jobs: {$pendingJobs}");
        $this->line("Failed migration jobs: {$failedJobs}");

        if ($detailed && !empty($detailedResults)) {
            $this->newLine();
            $this->info('Detailed Status:');
            $this->table(
                ['ID', 'Title', 'Path', 'Status'],
                $detailedResults
            );
        }

        // Recommendations
        $this->newLine();
        if ($localOnly > 0) {
            $this->warn("Recommendation: {$localOnly} images are still pending migration.");
            $this->line('Run: php artisan images:migrate-to-s3');
        }

        if ($both > 0) {
            $this->warn("Note: {$both} images exist in both local and S3 storage.");
            $this->line('Consider cleaning up local files after verifying S3 migration.');
        }

        if ($missing > 0) {
            $this->error("Warning: {$missing} images are missing from both storages!");
        }

        if ($failedJobs > 0) {
            $this->error("Warning: {$failedJobs} migration jobs have failed.");
            $this->line('Check failed jobs with: php artisan queue:failed');
        }

        return 0;
    }
}
