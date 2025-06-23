<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\FileUploadService;
use App\Models\Blog;

class MigrateImageToS3Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $blogId;
    protected string $localPath;

    public $tries = 3;
    public $timeout = 300;

    public function __construct(int $blogId, string $localPath)
    {
        $this->blogId = $blogId;
        $this->localPath = $localPath;
    }

    public function handle(FileUploadService $fileUploadService): void
    {
        try {
            $blog = Blog::find($this->blogId);
            
            if (!$blog) {
                Log::warning('Blog not found for S3 migration', ['blog_id' => $this->blogId]);
                return;
            }

            if (!$fileUploadService->fileExists($this->localPath)) {
                Log::warning('Local file not found for S3 migration', [
                    'blog_id' => $this->blogId,
                    'local_path' => $this->localPath
                ]);
                return;
            }

            $s3Path = $this->localPath;
            $migrated = $fileUploadService->moveToS3($this->localPath, $s3Path);

            if ($migrated) {
                Log::info('Successfully migrated image to S3', [
                    'blog_id' => $this->blogId,
                    'local_path' => $this->localPath,
                    's3_path' => $s3Path
                ]);
            } else {
                Log::error('Failed to migrate image to S3', [
                    'blog_id' => $this->blogId,
                    'local_path' => $this->localPath
                ]);
                
                $this->fail('Failed to migrate image to S3');
            }

        } catch (\Exception $e) {
            Log::error('S3 migration job failed', [
                'blog_id' => $this->blogId,
                'local_path' => $this->localPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('S3 migration job permanently failed', [
            'blog_id' => $this->blogId,
            'local_path' => $this->localPath,
            'error' => $exception->getMessage()
        ]);
    }
}
