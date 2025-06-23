<?php

namespace App\Services;

use App\Models\User;
use App\Models\Blog;
use App\Models\Comment;
use App\Jobs\SendBlogPublishedNotificationJob;
use App\Jobs\SendCommentNotificationJob;
use App\Jobs\SendOtpEmailJob;
use App\Jobs\SendWelcomeEmailJob;
use Illuminate\Support\Facades\Log;

class EmailService
{
    public function sendOtpEmail(User $user, string $otpCode, string $purpose = 'login'): bool
    {
        try {
            SendOtpEmailJob::dispatch($user, $otpCode, $purpose)
                          ->onQueue('emails')
                          ->delay(now()->addSeconds(2));

            Log::info('OTP email job dispatched', [
                'user_id' => $user->id,
                'purpose' => $purpose
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to dispatch OTP email job', [
                'user_id' => $user->id,
                'purpose' => $purpose,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendWelcomeEmail(User $user): bool
    {
        try {
            SendWelcomeEmailJob::dispatch($user)
                              ->onQueue('emails')
                              ->delay(now()->addMinutes(1));

            Log::info('Welcome email job dispatched', [
                'user_id' => $user->id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to dispatch welcome email job', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendBlogPublishedNotification(Blog $blog): bool
    {
        try {
            SendBlogPublishedNotificationJob::dispatch($blog)
                                           ->onQueue('notifications')
                                           ->delay(now()->addMinutes(2));

            Log::info('Blog published notification job dispatched', [
                'blog_id' => $blog->id,
                'author_id' => $blog->author_id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to dispatch blog published notification job', [
                'blog_id' => $blog->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendCommentNotification(Comment $comment, string $type = 'new_comment'): bool
    {
        try {
            SendCommentNotificationJob::dispatch($comment, $type)
                                     ->onQueue('notifications')
                                     ->delay(now()->addMinutes(1));

            Log::info('Comment notification job dispatched', [
                'comment_id' => $comment->id,
                'blog_id' => $comment->blog_id,
                'type' => $type
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to dispatch comment notification job', [
                'comment_id' => $comment->id,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendBulkNotification(array $userIds, string $subject, string $message, array $data = []): bool
    {
        try {
            foreach (array_chunk($userIds, 50) as $chunk) {
                \App\Jobs\SendBulkNotificationJob::dispatch($chunk, $subject, $message, $data)
                                                 ->onQueue('bulk-emails')
                                                 ->delay(now()->addMinutes(5));
            }

            Log::info('Bulk notification jobs dispatched', [
                'total_users' => count($userIds),
                'chunks' => ceil(count($userIds) / 50)
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to dispatch bulk notification jobs', [
                'user_count' => count($userIds),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getEmailConfiguration(): array
    {
        return [
            'driver' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'queue_enabled' => config('queue.default') !== 'sync',
        ];
    }

    public function testEmailConfiguration(): array
    {
        try {
            $config = $this->getEmailConfiguration();
            
            $testResults = [
                'configuration' => $config,
                'smtp_connection' => false,
                'queue_connection' => false,
                'errors' => []
            ];

            if ($config['driver'] === 'smtp') {
                try {
                    $connection = fsockopen($config['host'], $config['port'], $errno, $errstr, 10);
                    if ($connection) {
                        $testResults['smtp_connection'] = true;
                        fclose($connection);
                    } else {
                        $testResults['errors'][] = "SMTP connection failed: {$errstr}";
                    }
                } catch (\Exception $e) {
                    $testResults['errors'][] = "SMTP test failed: " . $e->getMessage();
                }
            } else {
                $testResults['smtp_connection'] = true; // For log driver
            }

            try {
                \Illuminate\Support\Facades\Queue::connection()->size();
                $testResults['queue_connection'] = true;
            } catch (\Exception $e) {
                $testResults['errors'][] = "Queue connection failed: " . $e->getMessage();
            }

            return $testResults;
        } catch (\Exception $e) {
            return [
                'configuration' => [],
                'smtp_connection' => false,
                'queue_connection' => false,
                'errors' => ['Email configuration test failed: ' . $e->getMessage()]
            ];
        }
    }
}
