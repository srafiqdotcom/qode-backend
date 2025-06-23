<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OtpService;

class CleanupExpiredOtps extends Command
{
    protected $signature = 'otp:cleanup';
    protected $description = 'Clean up expired OTP codes from the database';

    public function handle(OtpService $otpService)
    {
        $deletedCount = $otpService->cleanupExpiredOtps();
        
        $this->info("Cleaned up {$deletedCount} expired OTP codes.");
        
        return 0;
    }
}
