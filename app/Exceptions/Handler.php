<?php

namespace App\Exceptions;

use Throwable;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;

class Handler extends ExceptionHandler
{
    /**
     * Exception types that should not be reported.
     */
    protected $dontReport = [
        AuthorizationException::class,
        NotFoundHttpException::class,
        ValidationException::class,
    ];

    /**
     * Fields that are never flashed for validation exceptions.
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     */
    public function report(Throwable $exception)
    {
        if (!app()->environment('local') && $this->shouldReport($exception)) {
            Log::error($this->formatExceptionMessage($exception), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Custom handling for model not found
        if ($exception instanceof ModelNotFoundException) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        // Custom handling for method not allowed
        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json(['error' => 'Method not allowed'], 405);
        }

        // Custom handling for validation errors
        if ($exception instanceof ValidationException) {
            return response()->json(['errors' => $exception->errors()], 422);
        }

        // Custom handling for JWT exceptions
        if ($exception instanceof TokenBlacklistedException) {
            return response()->json([
                'error' => 'Token error',
                'message' => 'This token has been blacklisted. Please log in again.',
                'code' => 401,
            ], 401);
        }

        if ($exception instanceof TokenExpiredException) {
            return response()->json([
                'error' => 'Token expired',
                'message' => 'Your session has expired. Please log in again.',
                'code' => 401,
            ], 401);
        }

        if ($exception instanceof JWTException) {
            return response()->json([
                'error' => 'Token error',
                'message' => 'There was an issue with your token. Please try again.',
                'code' => 401,
            ], 401);
        }

        // Determine status code for other exceptions
        $status = $this->isHttpException($exception) ? $exception->getStatusCode() : 500;

        // Response for non-debug mode (production)
        if (!config('app.debug')) {
            return response()->json([
                'error' => 'Server error',
                'message' => 'An unexpected error occurred. Please try again later.',
                'code' => $status,
            ], $status);
        }

        // Debug mode: return full exception details and log the error
        Log::error('Exception in debug mode', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        return response()->json([
            'error' => 'Server error (Debug Mode)',
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $status,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
        ], $status);
    }

    /**
     * Format exception message for logging.
     */
    protected function formatExceptionMessage(Throwable $exception): string
    {
        return sprintf(
            "Exception: %s at %s:%s\nMessage: %s\nTrace: %s",
            get_class($exception),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getMessage(),
            $exception->getTraceAsString()
        );
    }
}
