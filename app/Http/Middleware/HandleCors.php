<?php

namespace App\Http\Middleware;

use Closure;

class HandleCors
{
    /**
     * Handle an incoming request and apply CORS headers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, Closure $next)
    {
        // **qode** Allow multiple frontend origins for development and production
        $allowedOrigins = [
            'http://localhost:5173',  // Vite dev server
            'http://localhost:3000',  // React/Next.js dev server
            'http://localhost:8080',  // Alternative dev server
            'http://127.0.0.1:5173',  // Alternative localhost
            'http://127.0.0.1:3000',  // Alternative localhost
        ];

        $allowedMethods = 'GET, POST, PUT, DELETE, OPTIONS, PATCH';
        $allowedHeaders = 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-TOKEN';

        $origin = $request->header('Origin');
        $allowOrigin = in_array($origin, $allowedOrigins) ? $origin : null;

        $response = $next($request);

        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $allowOrigin)
                ->header('Access-Control-Allow-Methods', $allowedMethods)
                ->header('Access-Control-Allow-Headers', $allowedHeaders)
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400'); // Cache preflight for 24 hours
        }

        return $response
            ->header('Access-Control-Allow-Origin', $allowOrigin)
            ->header('Access-Control-Allow-Methods', $allowedMethods)
            ->header('Access-Control-Allow-Headers', $allowedHeaders)
            ->header('Access-Control-Allow-Credentials', 'true');
    }
}
