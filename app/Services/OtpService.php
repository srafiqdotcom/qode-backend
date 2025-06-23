<?php

namespace App\Services;

use App\Models\User;
use App\Models\Otp;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;

class OtpService
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function generateAndSendOtp(User $user, string $purpose = 'login'): bool
    {
        try {
            $otp = Otp::createForUser($user, $purpose, [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $this->emailService->sendOtpEmail($user, $otp->otp_code, $purpose);

            Log::channel('auth_logs')->info('OTP generated for user', [
                'user_id' => $user->id,
                'email' => $user->email,
                'purpose' => $purpose,
                'ip_address' => request()->ip(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('auth_logs')->error('Failed to generate OTP', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function verifyOtp(User $user, string $otpCode, string $purpose = 'login'): bool
    {
        try {
            $isValid = Otp::verifyOtp($user, $otpCode, $purpose);

            Log::channel('auth_logs')->info('OTP verification attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'purpose' => $purpose,
                'success' => $isValid,
                'ip_address' => request()->ip(),
            ]);

            return $isValid;
        } catch (\Exception $e) {
            Log::channel('auth_logs')->error('OTP verification failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }



    public function cleanupExpiredOtps(): int
    {
        return Otp::expired()->delete();
    }
}
