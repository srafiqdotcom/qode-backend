<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;
use App\Models\Blog;
use App\Policies\BlogPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Blog::class => BlogPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();

        // **qode** Configure Laravel Passport
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        Gate::define('manage-blogs', function ($user) {
            return $user->isAuthor();
        });

        Gate::define('manage-tags', function ($user) {
            return $user->isAuthor();
        });

        Gate::define('manage-comments', function ($user) {
            return $user->isAuthor();
        });
    }
}
