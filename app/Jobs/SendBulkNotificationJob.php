<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\BulkNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendBulkNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $userIds;
    protected string $subject;
    protected string $message;
    protected array $data;

    public $tries = 3;
    public $timeout = 300;
    public $backoff = [120, 300, 600];

    public function __construct(array $userIds, string $subject, string $message, array $data = [])
    {
        $this->userIds = $userIds;
        $this->subject = $subject;
        $this->message = $message;
        $this->data = $data;
    }

    public function handle(): void
    {
        try {
            $users = User::whereIn('id', $this->userIds)
                        ->where('email_notifications', true)
                        ->whereNotNull('email_verified_at')
                        ->get(['id', 'name', 'email']);

            if ($users->isEmpty()) {
                Log::info('No eligible users found for bulk notification', [
                    'requested_user_ids' => $this->userIds
                ]);
                return;
            }

            $emailsSent = 0;
            $emailsFailed = 0;

            foreach ($users as $user) {
                try {
                    Mail::to($user->email)->send(
                        new BulkNotificationMail($user, $this->subject, $this->message, $this->data)
                    );
                    $emailsSent++;
                    
                    usleep(100000);
                } catch (\Exception $e) {
                    $emailsFailed++;
                    Log::warning('Failed to send bulk notification to user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Bulk notification batch completed', [
                'requested_users' => count($this->userIds),
                'eligible_users' => $users->count(),
                'emails_sent' => $emailsSent,
                'emails_failed' => $emailsFailed,
                'subject' => $this->subject
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk notification job failed', [
                'user_ids' => $this->userIds,
                'subject' => $this->subject,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk notification job permanently failed', [
            'user_ids' => $this->userIds,
            'subject' => $this->subject,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(4);
    }
}
