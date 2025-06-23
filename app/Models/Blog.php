<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Blog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'excerpt',
        'description',
        'image_path',
        'image_alt',
        'keywords',
        'meta_title',
        'meta_description',
        'status',
        'published_at',
        'scheduled_at',
        'author_id',
        'views_count',
        'comments_count',
    ];

    protected $casts = [
        'keywords' => 'array',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'views_count' => 'integer',
        'comments_count' => 'integer',
    ];

    protected $appends = [
        'image_url',
        'approved_comments'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($blog) {
            if (empty($blog->uuid)) {
                $blog->uuid = Str::uuid();
            }
            if (empty($blog->slug)) {
                $blog->slug = Str::slug($blog->title);
            }
        });

        static::updating(function ($blog) {
            if ($blog->isDirty('title') && empty($blog->getOriginal('slug'))) {
                $blog->slug = Str::slug($blog->title);
            }
        });

        // **qode** Cache invalidation on blog changes
        static::created(function ($blog) {
            app(\App\Services\CacheService::class)->invalidateBlog($blog->id);
        });

        static::updated(function ($blog) {
            app(\App\Services\CacheService::class)->invalidateBlog($blog->id);
        });

        static::deleted(function ($blog) {
            app(\App\Services\CacheService::class)->invalidateBlog($blog->id);
        });
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function approvedComments()
    {
        return $this->hasMany(Comment::class)->where('status', 'approved');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where('published_at', '<=', now());
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
                    ->where('scheduled_at', '>', now());
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    public function scopeWithTag($query, $tagSlug)
    {
        return $query->whereHas('tags', function ($q) use ($tagSlug) {
            $q->where('slug', $tagSlug);
        });
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && 
               $this->published_at && 
               $this->published_at->isPast();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && 
               $this->scheduled_at && 
               $this->scheduled_at->isFuture();
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function incrementViews()
    {
        $this->increment('views_count');
    }

    public function getReadingTimeAttribute()
    {
        $wordCount = str_word_count(strip_tags($this->description));
        return ceil($wordCount / 200);
    }

    /**
     * **qode** Get the full image URL for frontend display
     */
    public function getImageUrlAttribute()
    {
        if (!$this->image_path) {
            return null;
        }

        // If image_path already contains full URL, return as is
        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }

        // Generate full URL from relative path
        return url($this->image_path);
    }

    /**
     * **qode** Get approved comments for frontend display
     */
    public function getApprovedCommentsAttribute()
    {
        return $this->approvedComments()->get();
    }
}
