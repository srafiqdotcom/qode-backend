<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Utilities\ResponseHandler;

class CommentRateLimitMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('comments.rate_limiting.enabled', true)) {
            return $next($request);
        }

        $user = Auth::user();
        if (!$user) {
            return ResponseHandler::error('Authentication required', 401, 110);
        }

        $userId = $user->id;
        $ip = $request->ip();
        
        $hourlyKey = "comment_rate_limit_hour:{$userId}";
        $dailyKey = "comment_rate_limit_day:{$userId}";
        $cooldownKey = "comment_cooldown:{$userId}";
        $ipHourlyKey = "comment_rate_limit_ip_hour:{$ip}";

        $maxPerHour = config('comments.rate_limiting.max_per_hour', 10);
        $maxPerDay = config('comments.rate_limiting.max_per_day', 50);
        $cooldownMinutes = config('comments.rate_limiting.cooldown_minutes', 1);

        if (Cache::has($cooldownKey)) {
            $remainingTime = Cache::get($cooldownKey) - now()->timestamp;
            return ResponseHandler::error(
                "Please wait {$remainingTime} seconds before posting another comment.",
                429,
                111
            );
        }

        $hourlyCount = Cache::get($hourlyKey, 0);
        $dailyCount = Cache::get($dailyKey, 0);
        $ipHourlyCount = Cache::get($ipHourlyKey, 0);

        if ($hourlyCount >= $maxPerHour) {
            return ResponseHandler::error(
                'Too many comments this hour. Please try again later.',
                429,
                112
            );
        }

        if ($dailyCount >= $maxPerDay) {
            return ResponseHandler::error(
                'Daily comment limit reached. Please try again tomorrow.',
                429,
                113
            );
        }

        if ($ipHourlyCount >= ($maxPerHour * 2)) {
            return ResponseHandler::error(
                'Too many comments from this IP address. Please try again later.',
                429,
                114
            );
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200) {
            Cache::put($hourlyKey, $hourlyCount + 1, 3600);
            Cache::put($dailyKey, $dailyCount + 1, 86400);
            Cache::put($ipHourlyKey, $ipHourlyCount + 1, 3600);
            Cache::put($cooldownKey, now()->addMinutes($cooldownMinutes)->timestamp, $cooldownMinutes * 60);
        }

        return $response;
    }
}
