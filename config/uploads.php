<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for file uploads including storage, validation, and
    | processing options. Designed to be easily migrated to S3.
    |
    */

    'default_disk' => env('UPLOAD_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
        ],
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],
    ],

    'validation' => [
        'max_file_size' => env('UPLOAD_MAX_SIZE', 5120), // KB
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'webp', 'gif'
        ],
    ],

    'image_processing' => [
        'default_quality' => 85,
        'variants' => [
            'thumbnail' => [
                'width' => 300,
                'height' => 200,
                'quality' => 80,
            ],
            'medium' => [
                'width' => 800,
                'height' => 600,
                'quality' => 85,
            ],
            'large' => [
                'width' => 1200,
                'height' => 800,
                'quality' => 90,
            ],
        ],
    ],

    'directories' => [
        'blogs' => 'blogs',
        'users' => 'users',
        'temp' => 'temp',
    ],

    's3_migration' => [
        'enabled' => env('S3_MIGRATION_ENABLED', false),
        'batch_size' => env('S3_MIGRATION_BATCH_SIZE', 100),
        'delete_local_after_migration' => env('S3_DELETE_LOCAL_AFTER_MIGRATION', true),
    ],

];
