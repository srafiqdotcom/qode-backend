<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;

class ThrottleRequests
{
    protected RateLimiter $limiter;

    /**
     * Create a new middleware instance.
     *
     * @param RateLimiter $limiter
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param Closure $next
     * @param int $maxAttempts
     * @param int $decayMinutes
     * @return mixed
     * @throws ThrottleRequestsException
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1)
    {
        // Generate a unique key for this request
        $key = $this->resolveRequestSignature($request);

        // Rate limit check
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            throw new ThrottleRequestsException(
                'Too Many Attempts. Please try again later.',
                null,
                $this->getHeaders($maxAttempts, $decayMinutes, $key)
            );
        }

        // Increment attempt count and set decay time
        $this->limiter->hit($key, $decayMinutes * 60);

        // Add headers for remaining attempts
        $response = $next($request);
        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->limiter->retriesLeft($key, $maxAttempts),
            $decayMinutes
        );
    }

    /**
     * Determine the unique key for the request based on IP or user.
     *
     * @param \Illuminate\Http\Request $request
     * @return string
     */
    protected function resolveRequestSignature($request)
    {
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }

        return 'ip:' . $request->ip();
    }

    /**
     * Add rate limit headers to the response.
     *
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @param int $maxAttempts
     * @param int $remainingAttempts
     * @param int $decayMinutes
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addHeaders($response, $maxAttempts, $remainingAttempts, $decayMinutes)
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
            'X-RateLimit-Reset' => now()->addMinutes($decayMinutes)->timestamp,
        ]);

        return $response;
    }

    /**
     * Generate the headers for a throttled response.
     *
     * @param int $maxAttempts
     * @param int $decayMinutes
     * @param string $key
     * @return array
     */
    protected function getHeaders($maxAttempts, $decayMinutes, $key)
    {
        $remainingAttempts = $this->limiter->retriesLeft($key, $maxAttempts);

        return [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
            'Retry-After' => $this->limiter->availableIn($key),
        ];
    }
}
