<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blog;
use Illuminate\Support\Facades\Storage;

class CleanupLocalImagesCommand extends Command
{
    protected $signature = 'images:cleanup-local 
                            {--dry-run : Show what would be deleted without actually doing it}
                            {--force : Skip confirmation prompt}
                            {--verify-s3 : Only delete if S3 copy exists}';

    protected $description = 'Clean up local images that have been successfully migrated to S3';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $verifyS3 = $this->option('verify-s3');

        if (!Storage::disk('s3')->exists('')) {
            $this->error('S3 storage is not properly configured. Cannot verify S3 copies.');
            return 1;
        }

        $blogsWithImages = Blog::whereNotNull('image_path')
                              ->where('image_path', '!=', '')
                              ->get(['id', 'image_path', 'title']);

        if ($blogsWithImages->isEmpty()) {
            $this->info('No blog images found.');
            return 0;
        }

        $this->info('Checking for local images that can be cleaned up...');
        $progressBar = $this->output->createProgressBar($blogsWithImages->count());
        $progressBar->start();

        $toDelete = [];

        foreach ($blogsWithImages as $blog) {
            $localExists = Storage::disk('local')->exists($blog->image_path);
            
            if (!$localExists) {
                $progressBar->advance();
                continue;
            }

            if ($verifyS3) {
                $s3Exists = Storage::disk('s3')->exists($blog->image_path);
                if (!$s3Exists) {
                    $progressBar->advance();
                    continue;
                }
            }

            $toDelete[] = [
                'blog_id' => $blog->id,
                'title' => $blog->title,
                'path' => $blog->image_path,
                'size' => Storage::disk('local')->size($blog->image_path),
            ];

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if (empty($toDelete)) {
            $this->info('No local images found for cleanup.');
            return 0;
        }

        $totalSize = array_sum(array_column($toDelete, 'size'));
        $totalSizeMB = round($totalSize / 1024 / 1024, 2);

        $this->info("Found " . count($toDelete) . " local images for cleanup (Total size: {$totalSizeMB} MB)");

        if ($dryRun) {
            $this->info('DRY RUN - The following local images would be deleted:');
            foreach ($toDelete as $item) {
                $sizeMB = round($item['size'] / 1024 / 1024, 2);
                $this->line("- Blog ID: {$item['blog_id']} | Title: " . substr($item['title'], 0, 30) . "... | Size: {$sizeMB} MB");
            }
            return 0;
        }

        if (!$force) {
            $message = "Are you sure you want to delete " . count($toDelete) . " local images ({$totalSizeMB} MB)?";
            if ($verifyS3) {
                $message .= "\n(S3 copies have been verified to exist)";
            } else {
                $message .= "\n(WARNING: S3 verification is disabled - files may be permanently lost!)";
            }

            if (!$this->confirm($message)) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        $this->info('Starting cleanup...');
        $progressBar = $this->output->createProgressBar(count($toDelete));
        $progressBar->start();

        $deleted = 0;
        $failed = 0;

        foreach ($toDelete as $item) {
            try {
                if (Storage::disk('local')->delete($item['path'])) {
                    $deleted++;
                } else {
                    $failed++;
                    $this->newLine();
                    $this->error("Failed to delete: {$item['path']}");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Error deleting {$item['path']}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Cleanup completed:");
        $this->line("- Successfully deleted: {$deleted} files");
        if ($failed > 0) {
            $this->error("- Failed to delete: {$failed} files");
        }

        $deletedSizeMB = round(($deleted / count($toDelete)) * $totalSizeMB, 2);
        $this->info("- Space freed: ~{$deletedSizeMB} MB");

        return $failed > 0 ? 1 : 0;
    }
}
