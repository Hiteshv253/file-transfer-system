<?php

return [
    'upload_types' => [
        'bedrive' => [
            'visibility' => 'private',
            'dont_clean' => true,
            'supports_folders' => true,
            'supports_max_space_usage' => false,
            'label' => 'Bedrive uploads',
            'description' => 'All files uploaded by users in Bedrive.',
            'defaults' => [
                'allow_all_file_types' => true,
            ],
        ],
    ],

    'synology_backup' => [
        'enabled' => env('SYNOLOGY_BACKUP_ENABLED', false),
        'driver' => env('SYNOLOGY_BACKUP_DRIVER', 'local'),
        'root' => env('SYNOLOGY_BACKUP_ROOT'),
        'prefix' => env('SYNOLOGY_BACKUP_PREFIX'),
        'visibility' => env('SYNOLOGY_BACKUP_VISIBILITY', 'private'),
        'retain_thumbnails' => env('SYNOLOGY_BACKUP_RETAIN_THUMBNAILS', false),
        'config' => array_filter([
            'host' => env('SYNOLOGY_BACKUP_HOST'),
            'port' => env('SYNOLOGY_BACKUP_PORT'),
            'username' => env('SYNOLOGY_BACKUP_USERNAME'),
            'password' => env('SYNOLOGY_BACKUP_PASSWORD'),
            'privateKey' => env('SYNOLOGY_BACKUP_PRIVATE_KEY'),
            'passphrase' => env('SYNOLOGY_BACKUP_PASSPHRASE'),
            'baseUri' => env('SYNOLOGY_BACKUP_BASE_URI'),
            'bucket' => env('SYNOLOGY_BACKUP_BUCKET'),
            'region' => env('SYNOLOGY_BACKUP_REGION'),
            'endpoint' => env('SYNOLOGY_BACKUP_ENDPOINT'),
            'use_path_style_endpoint' => env(
                'SYNOLOGY_BACKUP_USE_PATH_STYLE_ENDPOINT',
                false,
            ),
        ], fn($value) => $value !== null && $value !== ''),
    ],
];
