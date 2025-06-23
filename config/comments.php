<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Comment System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the blog comment system including moderation,
    | markdown processing, and security settings.
    |
    */

    'auto_approve' => env('COMMENTS_AUTO_APPROVE', false),

    'moderation' => [
        'require_approval' => env('COMMENTS_REQUIRE_APPROVAL', true),
        'auto_approve_registered_users' => env('COMMENTS_AUTO_APPROVE_REGISTERED', false),
        'auto_approve_authors' => env('COMMENTS_AUTO_APPROVE_AUTHORS', true),
        'max_pending_per_user' => env('COMMENTS_MAX_PENDING_PER_USER', 5),
    ],

    'rate_limiting' => [
        'enabled' => env('COMMENTS_RATE_LIMITING', true),
        'max_per_hour' => env('COMMENTS_MAX_PER_HOUR', 10),
        'max_per_day' => env('COMMENTS_MAX_PER_DAY', 50),
        'cooldown_minutes' => env('COMMENTS_COOLDOWN_MINUTES', 1),
    ],

    'content' => [
        'max_length' => env('COMMENTS_MAX_LENGTH', 5000),
        'min_length' => env('COMMENTS_MIN_LENGTH', 3),
        'allow_markdown' => env('COMMENTS_ALLOW_MARKDOWN', true),
        'allow_links' => env('COMMENTS_ALLOW_LINKS', true),
        'max_links_per_comment' => env('COMMENTS_MAX_LINKS', 3),
    ],

    'nesting' => [
        'enabled' => env('COMMENTS_NESTING_ENABLED', true),
        'max_depth' => env('COMMENTS_MAX_DEPTH', 3),
    ],

    'security' => [
        'sanitize_html' => true,
        'strip_dangerous_tags' => true,
        'validate_urls' => true,
        'block_suspicious_content' => true,
        'log_security_violations' => true,
    ],

    'notifications' => [
        'notify_author_on_comment' => env('COMMENTS_NOTIFY_AUTHOR', true),
        'notify_user_on_reply' => env('COMMENTS_NOTIFY_REPLY', true),
        'notify_user_on_approval' => env('COMMENTS_NOTIFY_APPROVAL', false),
    ],

    'spam_protection' => [
        'enabled' => env('COMMENTS_SPAM_PROTECTION', true),
        'duplicate_detection' => true,
        'duplicate_threshold_minutes' => 5,
        'suspicious_keywords' => [
            'spam', 'casino', 'viagra', 'lottery', 'winner',
            'click here', 'free money', 'make money fast'
        ],
        'max_urls_threshold' => 3,
        'min_time_between_comments' => 30, // seconds
    ],

    'cleanup' => [
        'auto_delete_rejected' => env('COMMENTS_AUTO_DELETE_REJECTED', false),
        'rejected_retention_days' => env('COMMENTS_REJECTED_RETENTION_DAYS', 30),
        'soft_delete_user_comments' => true,
    ],

];
