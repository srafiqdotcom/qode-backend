<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * The URIs that should be accessible during maintenance mode.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Define URIs that should be accessible during maintenance
    ];

    /**
     * Determine if the request has a URI that should pass through maintenance mode.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldPassThrough($request)
    {
        // Allow IP whitelisting for maintenance mode access if needed
        $whitelistedIPs = [
            '127.0.0.1', // Localhost
            'your_whitelisted_ip_here',
        ];

        // Allow URIs defined in `$except` to bypass maintenance
        if (in_array($request->ip(), $whitelistedIPs)) {
            return true;
        }

        // If request path is in the except array, allow access
        return collect($this->except)
            ->contains(fn ($uri) => $request->is($uri));
    }

    /**
     * Custom response when the application is in maintenance mode.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function renderMaintenanceModeResponse($request)
    {
        return response()->json([
            'message' => 'The application is currently in maintenance mode. Please check back later.',
            'code' => 503,
        ], 503);
    }
}
