<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\BlogController;
use App\Http\Controllers\V1\TagController;
use App\Http\Controllers\V1\CommentController;
use Illuminate\Http\Request;
Route::middleware('throttle:api')->group(function () {

// Public (no auth)
    Route::post('register', [AuthController::class, 'register']);
    Route::post('auth/request-otp', [AuthController::class, 'requestOtp'])
        ->middleware(\App\Http\Middleware\OtpRateLimitMiddleware::class);
    Route::post('auth/verify-otp', [AuthController::class, 'verifyOtp']);

    // Public blog endpoints
    Route::get('blogs', [BlogController::class, 'index']);
    Route::get('blogs/search', [BlogController::class, 'search']);
    Route::get('blogs/{id}', [BlogController::class, 'show']);
    Route::get('blogs/tag/{tagSlug}', [BlogController::class, 'getByTag']);
    Route::get('blogs/author/{authorId}', [BlogController::class, 'getByAuthor']);

    // Public tag endpoints
    Route::get('tags', [TagController::class, 'index']);
    Route::get('tags/{id}', [TagController::class, 'show']);

    // Public comment endpoints
    Route::get('blogs/{blogId}/comments', [CommentController::class, 'getCommentsByBlog']);
    Route::get('comments/{id}', [CommentController::class, 'show']);

// Protected (with Passport Token JWT)
    Route::middleware('auth:api')->group(function () {
       Route::post('auth/logout', [AuthController::class, 'logout']);

        // CRUD for Users
        Route::apiResource('users', UserController::class);

        // Comment management (authenticated users)
        Route::post('comments', [CommentController::class, 'store'])
            ->middleware(\App\Http\Middleware\CommentRateLimitMiddleware::class);
        Route::put('comments/{id}', [CommentController::class, 'update']);
        Route::delete('comments/{id}', [CommentController::class, 'destroy']);

        // Blog management (open for all authenticated authors - authorization handled in repository)
        Route::post('blogs', [BlogController::class, 'store']);
        Route::put('blogs/{id}', [BlogController::class, 'update']);
        Route::delete('blogs/{id}', [BlogController::class, 'destroy']);
        Route::post('blogs/{id}/publish', [BlogController::class, 'publish']);
        Route::post('blogs/{id}/schedule', [BlogController::class, 'schedule']);
        Route::post('blogs/{id}/draft', [BlogController::class, 'draft']);

        // Tag management (open for all authenticated authors - authorization handled in repository)
        Route::post('tags', [TagController::class, 'store']);
        Route::put('tags/{id}', [TagController::class, 'update']);
        Route::delete('tags/{id}', [TagController::class, 'destroy']);

        // Image upload endpoints (protected by authentication)
        Route::post('upload/image', [App\Http\Controllers\V1\ImageUploadController::class, 'uploadBlogImage']);
        Route::delete('upload/image', [App\Http\Controllers\V1\ImageUploadController::class, 'deleteImage']);


    });
});

