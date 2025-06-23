<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\OtpMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendOtpEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public User $user;
    public string $otpCode;
    public string $purpose;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [30, 60, 120];

    public function __construct(User $user, string $otpCode, string $purpose = 'login')
    {
        $this->user = $user;
        $this->otpCode = $otpCode;
        $this->purpose = $purpose;
    }

    public function handle(): void
    {
        try {
            Mail::to($this->user->email)->send(new OtpMail($this->user, $this->otpCode, $this->purpose));

            Log::info('OTP email sent successfully', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'purpose' => $this->purpose,
                'attempt' => $this->attempts()
            ]);
        } catch (\Exception $e) {
            Log::error('OTP email sending failed', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'purpose' => $this->purpose,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('OTP email job permanently failed', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'purpose' => $this->purpose,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }
}
