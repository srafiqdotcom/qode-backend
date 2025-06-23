<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('blog_cache.enabled', true)) {
            \App\Models\Blog::observe(\App\Observers\BlogCacheObserver::class);
            \App\Models\Comment::observe(\App\Observers\CommentCacheObserver::class);
            \App\Models\Tag::observe(\App\Observers\TagCacheObserver::class);
            \App\Models\Blog::observe(\App\Observers\BlogSearchObserver::class);
        }
    }
}
