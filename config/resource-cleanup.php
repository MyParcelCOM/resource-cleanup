<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Retention Period
    |--------------------------------------------------------------------------
    |
    | Records older than this many days will be permanently deleted when
    | running the cleanup command. Individual models can override this by
    | implementing the CleanableResource contract.
    |
    */
    'default_retention_days' => env('RESOURCE_CLEANUP_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Default Cleanup Chunk Size
    |--------------------------------------------------------------------------
    |
    | Number of records that will be loaded into memory for deletion
    |
    */
    'cleanup_chunk_size' => env('RESOURCE_CLEANUP_CHUNK_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | List the fully-qualified Eloquent model class names that can be
    | cleaned up when running `php artisan resource-cleanup:run`.
    |
    | Example:
    |   \App\Models\Order::class,
    |   \App\Models\AuditLog::class,
    |
    */
    'models' => [
        // \App\Models\YourModel::class,
    ],

];
