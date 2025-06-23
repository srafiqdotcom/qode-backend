<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class FileUploadService
{
    protected string $disk;
    protected array $allowedMimeTypes;
    protected int $maxFileSize;

    public function __construct()
    {
        // **qode** Use local disk to store directly in public directory
        $this->disk = 'local';
        $this->allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif'
        ];
        $this->maxFileSize = 5 * 1024 * 1024; // 5MB
    }

    public function uploadImage(UploadedFile $file, string $directory = 'uploads', array $resizeOptions = []): ?array
    {
        try {
            if (!$this->validateFile($file)) {
                return null;
            }

            // **qode** Get file info before moving
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            $filename = $this->generateUniqueFilename($file);

            // **qode** Store directly in public directory for direct web access
            $publicDirectory = public_path('uploads/' . $directory . '/' . dirname($filename));
            $fullPath = $publicDirectory . '/' . basename($filename);

            // Create directory if it doesn't exist
            if (!file_exists($publicDirectory)) {
                mkdir($publicDirectory, 0755, true);
            }

            // Move the uploaded file to public directory
            $moved = $file->move($publicDirectory, basename($filename));

            if (!$moved) {
                Log::error('Failed to upload file', [
                    'filename' => $filename,
                    'directory' => $directory,
                    'public_directory' => $publicDirectory
                ]);
                return null;
            }

            $relativePath = 'uploads/' . $directory . '/' . $filename;

            return [
                'path' => $relativePath,
                'filename' => $filename,
                'original_name' => $originalName,
                'size' => $fileSize,
                'mime_type' => $mimeType,
                'url' => url($relativePath),
            ];

        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'directory' => $directory
            ]);
            return null;
        }
    }

    public function deleteFile(string $path): bool
    {
        try {
            // **qode** Handle both old storage paths and new public paths
            $fullPath = public_path($path);

            if (file_exists($fullPath)) {
                return unlink($fullPath);
            }

            // **qode** Fallback for old storage paths
            if (Storage::disk($this->disk)->exists($path)) {
                return Storage::disk($this->disk)->delete($path);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'error' => $e->getMessage(),
                'path' => $path,
                'full_path' => $fullPath ?? 'N/A'
            ]);
            return false;
        }
    }

    public function getFileUrl(string $path): string
    {
        if ($this->disk === 's3') {
            return Storage::disk($this->disk)->url($path);
        }

        // **qode** Return direct URL for public directory files
        return url($path);
    }

    public function fileExists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    public function moveToS3(string $localPath, string $s3Path, bool $deleteLocal = true): bool
    {
        try {
            if (!Storage::disk('local')->exists($localPath)) {
                Log::warning('Local file does not exist for S3 migration', [
                    'local_path' => $localPath
                ]);
                return false;
            }

            $fileContent = Storage::disk('local')->get($localPath);
            $uploaded = Storage::disk('s3')->put($s3Path, $fileContent, 'public');

            if ($uploaded) {
                Log::info('File uploaded to S3 successfully', [
                    'local_path' => $localPath,
                    's3_path' => $s3Path
                ]);

                if ($deleteLocal && config('uploads.s3_migration.delete_local_after_migration', true)) {
                    $deleted = Storage::disk('local')->delete($localPath);
                    if ($deleted) {
                        Log::info('Local file deleted after S3 migration', [
                            'local_path' => $localPath
                        ]);
                    } else {
                        Log::warning('Failed to delete local file after S3 migration', [
                            'local_path' => $localPath
                        ]);
                    }
                }

                return true;
            }

            Log::error('Failed to upload file to S3', [
                'local_path' => $localPath,
                's3_path' => $s3Path
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('S3 migration failed with exception', [
                'error' => $e->getMessage(),
                'local_path' => $localPath,
                's3_path' => $s3Path,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function verifyS3Migration(string $localPath, string $s3Path): array
    {
        $result = [
            'local_exists' => Storage::disk('local')->exists($localPath),
            's3_exists' => Storage::disk('s3')->exists($s3Path),
            'sizes_match' => false,
            'migration_verified' => false,
        ];

        if ($result['local_exists'] && $result['s3_exists']) {
            $localSize = Storage::disk('local')->size($localPath);
            $s3Size = Storage::disk('s3')->size($s3Path);
            $result['sizes_match'] = $localSize === $s3Size;
            $result['local_size'] = $localSize;
            $result['s3_size'] = $s3Size;
        }

        $result['migration_verified'] = $result['s3_exists'] &&
                                       (!$result['local_exists'] || $result['sizes_match']);

        return $result;
    }

    private function validateFile(UploadedFile $file): bool
    {
        if (!$file->isValid()) {
            Log::warning('Invalid file upload', [
                'error' => $file->getErrorMessage(),
                'filename' => $file->getClientOriginalName()
            ]);
            return false;
        }

        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            Log::warning('Invalid file type', [
                'mime_type' => $file->getMimeType(),
                'filename' => $file->getClientOriginalName(),
                'allowed_types' => $this->allowedMimeTypes
            ]);
            return false;
        }

        if ($file->getSize() > $this->maxFileSize) {
            Log::warning('File too large', [
                'size' => $file->getSize(),
                'max_size' => $this->maxFileSize,
                'filename' => $file->getClientOriginalName()
            ]);
            return false;
        }

        return true;
    }

    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y/m/d');
        $uuid = Str::uuid();

        // **qode** Create directory structure and return just the filename
        $this->ensureDirectoryExists($timestamp);

        return $timestamp . '/' . $uuid . '.' . $extension;
    }

    private function ensureDirectoryExists(string $dateDirectory): void
    {
        $fullPath = public_path('uploads/blogs/' . $dateDirectory);
        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
    }

    public function getImageVariants(string $originalPath, array $variants): array
    {
        $results = [];
        $pathInfo = pathinfo($originalPath);

        foreach ($variants as $name => $options) {
            $variantPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_' . $name . '.' . $pathInfo['extension'];
            $results[$name] = [
                'path' => $variantPath,
                'url' => $this->getFileUrl($variantPath),
            ];
        }

        return $results;
    }
}
