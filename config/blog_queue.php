<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blog Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for queue management in the blog application including
    | queue names, retry logic, and job priorities.
    |
    */

    'enabled' => env('EMAIL_QUEUE_ENABLED', true),

    'queues' => [
        'emails' => [
            'name' => 'emails',
            'priority' => 'high',
            'retry_after' => 90,
            'max_tries' => 3,
            'backoff' => [30, 60, 120],
        ],
        'notifications' => [
            'name' => 'notifications',
            'priority' => 'medium',
            'retry_after' => 120,
            'max_tries' => 3,
            'backoff' => [60, 120, 300],
        ],
        'blog_publishing' => [
            'name' => 'blog-publishing',
            'priority' => 'high',
            'retry_after' => 180,
            'max_tries' => 3,
            'backoff' => [60, 180, 300],
        ],
        'bulk_emails' => [
            'name' => 'bulk-emails',
            'priority' => 'low',
            'retry_after' => 300,
            'max_tries' => 2,
            'backoff' => [120, 300],
        ],
        's3_migration' => [
            'name' => 's3-migration',
            'priority' => 'low',
            'retry_after' => 600,
            'max_tries' => 5,
            'backoff' => [300, 600, 1200, 2400, 4800],
        ],
    ],

    'job_types' => [
        'otp_email' => [
            'queue' => 'emails',
            'delay' => 2, // seconds
            'timeout' => 60,
        ],
        'welcome_email' => [
            'queue' => 'emails',
            'delay' => 60, // 1 minute
            'timeout' => 60,
        ],
        'blog_published' => [
            'queue' => 'notifications',
            'delay' => 120, // 2 minutes
            'timeout' => 120,
        ],
        'comment_notification' => [
            'queue' => 'notifications',
            'delay' => 60, // 1 minute
            'timeout' => 60,
        ],
        'bulk_notification' => [
            'queue' => 'bulk-emails',
            'delay' => 300, // 5 minutes
            'timeout' => 300,
        ],
        'scheduled_publishing' => [
            'queue' => 'blog-publishing',
            'delay' => 0,
            'timeout' => 120,
        ],
    ],

    'monitoring' => [
        'enabled' => env('QUEUE_MONITORING_ENABLED', true),
        'log_job_start' => env('QUEUE_LOG_JOB_START', false),
        'log_job_completion' => env('QUEUE_LOG_JOB_COMPLETION', true),
        'log_job_failure' => env('QUEUE_LOG_JOB_FAILURE', true),
        'alert_on_failure' => env('QUEUE_ALERT_ON_FAILURE', false),
    ],

    'cleanup' => [
        'failed_jobs_retention_days' => env('QUEUE_FAILED_RETENTION_DAYS', 7),
        'completed_jobs_retention_hours' => env('QUEUE_COMPLETED_RETENTION_HOURS', 24),
        'auto_cleanup_enabled' => env('QUEUE_AUTO_CLEANUP', true),
    ],

    'rate_limiting' => [
        'emails_per_minute' => env('QUEUE_EMAILS_PER_MINUTE', 60),
        'notifications_per_minute' => env('QUEUE_NOTIFICATIONS_PER_MINUTE', 30),
        'bulk_emails_per_minute' => env('QUEUE_BULK_EMAILS_PER_MINUTE', 10),
    ],

];
