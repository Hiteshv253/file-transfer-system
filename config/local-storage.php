<?php

return [
    'enabled' => env('LOCAL_FILE_PORTAL', true),
    'root' => storage_path('app/server-storage'),
    'user_limit' => 5,
    'timezone' => 'Asia/Kolkata',
    'lock_writes' => env('FILEVAULT_STORAGE_LOCK', false),
    'sync_writes' => env('FILEVAULT_FSYNC', false),
    'lock_path' => storage_path('app/locks/storage-backup.lock'),
];
