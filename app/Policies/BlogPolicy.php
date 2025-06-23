<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Blog;

class BlogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Blog $blog): bool
    {
        if ($blog->isPublished()) {
            return true;
        }

        return $user->id === $blog->author_id || $user->isAuthor();
    }

    public function create(User $user): bool
    {
        return $user->isAuthor();
    }

    public function update(User $user, Blog $blog): bool
    {
        return $user->id === $blog->author_id || $user->isAuthor();
    }

    public function delete(User $user, Blog $blog): bool
    {
        return $user->id === $blog->author_id || $user->isAuthor();
    }

    public function publish(User $user, Blog $blog): bool
    {
        return $user->id === $blog->author_id || $user->isAuthor();
    }

    public function schedule(User $user, Blog $blog): bool
    {
        return $user->id === $blog->author_id || $user->isAuthor();
    }
}
