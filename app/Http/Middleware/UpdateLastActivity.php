<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class UpdateLastActivity
{
    public function handle($request, Closure $next)
    {
        if (auth()->check()) {
            DB::table('sessions')
                ->where('user_id', auth()->id())
                ->update(['last_activity' => now()->timestamp]);
        }

        return $next($request);
    }
}
