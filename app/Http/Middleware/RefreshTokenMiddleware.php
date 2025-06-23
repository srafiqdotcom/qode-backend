<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Utilities\ResponseHandler;
use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;

class RefreshTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Attempt to authenticate the user with the access token
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return ResponseHandler::ErrorResponse('User not found.', 404);
            }
        } catch (TokenExpiredException $e) {
            // If access token is expired, try to refresh it using the refresh token
            try {
                $refreshToken = $request->header('refresh_token');

                // Verify the refresh token against the stored one for the user
                $user = User::find(JWTAuth::getPayload(JWTAuth::getToken())->get('sub'));

                if ($user && $user->refresh_token === $refreshToken) {
                    // Generate a new access token
                    $newAccessToken = JWTAuth::fromUser($user);

                    // Attach the new access token to the response headers
                    $request->headers->set('Authorization', 'Bearer ' . $newAccessToken);

                    // Send the new access token in the response if required by the client
                    return $next($request)->header('New-Access-Token', $newAccessToken);
                } else {
                    return ResponseHandler::ErrorResponse('Refresh token invalid or expired. Please log in again.', 401);
                }
            } catch (JWTException $e) {
                return ResponseHandler::ErrorResponse('Token refresh failed. Please log in again.', 401);
            }
        } catch (JWTException $e) {
            return ResponseHandler::ErrorResponse('Token error. Please log in again.', 401);
        }

        return $next($request);
    }
}
