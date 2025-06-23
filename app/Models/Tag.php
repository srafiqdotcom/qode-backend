<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'color',
        'blogs_count',
    ];

    protected $casts = [
        'blogs_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->uuid)) {
                $tag->uuid = Str::uuid();
            }
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });

        static::updating(function ($tag) {
            if ($tag->isDirty('name') && empty($tag->getOriginal('slug'))) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function blogs()
    {
        return $this->belongsToMany(Blog::class);
    }

    public function publishedBlogs()
    {
        return $this->belongsToMany(Blog::class)
                   ->where('status', 'published')
                   ->where('published_at', '<=', now());
    }

    public function scopePopular($query, $limit = 10)
    {
        return $query->orderBy('blogs_count', 'desc')->limit($limit);
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function updateBlogsCount()
    {
        $this->blogs_count = $this->publishedBlogs()->count();
        $this->save();
    }
}
