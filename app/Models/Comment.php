<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'blog_id',
        'user_id',
        'parent_id',
        'content',
        'status',
        'ip_address',
        'user_agent',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($comment) {
            if (empty($comment->uuid)) {
                $comment->uuid = Str::uuid();
            }
        });

        static::created(function ($comment) {
            if ($comment->status === 'approved') {
                $comment->blog->increment('comments_count');
            }
        });

        static::updated(function ($comment) {
            if ($comment->wasChanged('status')) {
                if ($comment->status === 'approved' && $comment->getOriginal('status') !== 'approved') {
                    $comment->blog->increment('comments_count');
                } elseif ($comment->status !== 'approved' && $comment->getOriginal('status') === 'approved') {
                    $comment->blog->decrement('comments_count');
                }
            }
        });

        static::deleted(function ($comment) {
            if ($comment->status === 'approved') {
                $comment->blog->decrement('comments_count');
            }
        });
    }

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function approvedReplies()
    {
        return $this->hasMany(Comment::class, 'parent_id')->where('status', 'approved');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeParentComments($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
