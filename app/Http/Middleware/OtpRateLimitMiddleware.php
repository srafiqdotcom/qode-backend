<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Utilities\ResponseHandler;

class OtpRateLimitMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $email = $request->input('email');
        $ip = $request->ip();
        
        if (!$email) {
            return $next($request);
        }

        $emailKey = "otp_request_email:{$email}";
        $ipKey = "otp_request_ip:{$ip}";
        
        $emailAttempts = Cache::get($emailKey, 0);
        $ipAttempts = Cache::get($ipKey, 0);
        
        $maxAttemptsPerEmail = 3;
        $maxAttemptsPerIp = 10;
        $rateLimitWindow = 300; // 5 minutes

        if ($emailAttempts >= $maxAttemptsPerEmail) {
            return ResponseHandler::error(
                'Too many OTP requests for this email. Please try again later.',
                429,
                22
            );
        }

        if ($ipAttempts >= $maxAttemptsPerIp) {
            return ResponseHandler::error(
                'Too many OTP requests from this IP. Please try again later.',
                429,
                23
            );
        }

        Cache::put($emailKey, $emailAttempts + 1, $rateLimitWindow);
        Cache::put($ipKey, $ipAttempts + 1, $rateLimitWindow);

        return $next($request);
    }
}
