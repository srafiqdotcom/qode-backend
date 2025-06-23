<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blog Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Redis caching in the blog application including
    | TTL settings, cache keys, and invalidation strategies.
    |
    */

    'enabled' => env('BLOG_CACHE_ENABLED', true),

    'default_driver' => env('CACHE_DRIVER', 'redis'),

    'ttl' => [
        'default' => env('CACHE_TTL_DEFAULT', 3600), // 1 hour
        'blog_list' => env('CACHE_TTL_BLOG_LIST', 1800), // 30 minutes
        'blog_detail' => env('CACHE_TTL_BLOG_DETAIL', 7200), // 2 hours
        'comments' => env('CACHE_TTL_COMMENTS', 1800), // 30 minutes
        'tags' => env('CACHE_TTL_TAGS', 3600), // 1 hour
        'search' => env('CACHE_TTL_SEARCH', 900), // 15 minutes
        'popular_blogs' => env('CACHE_TTL_POPULAR_BLOGS', 3600), // 1 hour
        'recent_blogs' => env('CACHE_TTL_RECENT_BLOGS', 600), // 10 minutes
        'author_blogs' => env('CACHE_TTL_AUTHOR_BLOGS', 1800), // 30 minutes
        'tag_blogs' => env('CACHE_TTL_TAG_BLOGS', 1800), // 30 minutes
    ],

    'keys' => [
        'prefix' => env('CACHE_KEY_PREFIX', 'blog_app'),
        'separator' => ':',
        'blog_list' => 'blogs:list',
        'blog_detail' => 'blog',
        'comments' => 'comments',
        'tags' => 'tags',
        'search' => 'search',
        'popular' => 'popular',
        'recent' => 'recent',
    ],

    'tags' => [
        'global' => ['blog_app'],
        'blogs' => ['blogs'],
        'blog_lists' => ['blog_lists'],
        'blog_details' => ['blog_details'],
        'comments' => ['comments'],
        'tags' => ['tags'],
        'search' => ['search'],
        'popular' => ['popular'],
        'recent' => ['recent'],
    ],

    'invalidation' => [
        'auto_invalidate' => env('CACHE_AUTO_INVALIDATE', true),
        'cascade_invalidation' => env('CACHE_CASCADE_INVALIDATION', true),
        'log_invalidation' => env('CACHE_LOG_INVALIDATION', true),
        
        'triggers' => [
            'blog_created' => ['blog_lists', 'search', 'recent', 'author_blogs', 'tags'],
            'blog_updated' => ['blog_details', 'blog_lists', 'search', 'author_blogs'],
            'blog_deleted' => ['blog_details', 'blog_lists', 'search', 'author_blogs', 'comments'],
            'blog_published' => ['blog_lists', 'search', 'recent', 'popular'],
            'comment_created' => ['comments'],
            'comment_approved' => ['comments'],
            'comment_deleted' => ['comments'],
            'tag_created' => ['tags', 'blog_lists'],
            'tag_updated' => ['tags', 'tag_blogs'],
            'tag_deleted' => ['tags', 'tag_blogs', 'blog_lists'],
        ],
    ],

    'performance' => [
        'compression' => env('CACHE_COMPRESSION', true),
        'serialization' => env('CACHE_SERIALIZATION', 'json'),
        'connection_timeout' => env('CACHE_CONNECTION_TIMEOUT', 5),
        'read_timeout' => env('CACHE_READ_TIMEOUT', 10),
        'max_connections' => env('CACHE_MAX_CONNECTIONS', 100),
    ],

    'monitoring' => [
        'enabled' => env('CACHE_MONITORING_ENABLED', true),
        'log_hits' => env('CACHE_LOG_HITS', false),
        'log_misses' => env('CACHE_LOG_MISSES', true),
        'log_invalidations' => env('CACHE_LOG_INVALIDATIONS', true),
        'metrics_collection' => env('CACHE_METRICS_COLLECTION', true),
    ],

    'warming' => [
        'enabled' => env('CACHE_WARMING_ENABLED', false),
        'schedule' => env('CACHE_WARMING_SCHEDULE', '0 */6 * * *'), // Every 6 hours
        'items' => [
            'popular_blogs' => true,
            'recent_blogs' => true,
            'popular_tags' => true,
            'featured_content' => false,
        ],
    ],

];
