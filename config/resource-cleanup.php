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
    | Models
    |--------------------------------------------------------------------------
    |
    | List the fully-qualified Eloquent model class names that should be
    | cleaned up when running `php artisan resource-cleanup:run` without
    | the --model option.
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
