# resource-cleanup

[![Tests](https://github.com/myparcelcom/resource-cleanup/actions/workflows/tests.yml/badge.svg)](https://github.com/myparcelcom/resource-cleanup/actions/workflows/tests.yml)

A Laravel package to permanently delete soft-deleted or expired records after a configurable cutoff date.

## Requirements

- PHP 8.2+
- Laravel 9, 10, 11, or 12

## Installation

```bash
composer require myparcelcom/resource-cleanup
```

Publish the config file:

```bash
php artisan vendor:publish --tag=resource-cleanup-config
```

## Configuration

`config/resource-cleanup.php`:

```php
return [
    // Records older than this many days will be deleted (default: 90)
    'default_retention_days' => env('RESOURCE_CLEANUP_RETENTION_DAYS', 90),

    // Models to clean up when running the command without --model
    'models' => [
        \App\Models\Order::class,
        \App\Models\AuditLog::class,
    ],
];
```

## Usage

### Artisan Command

Run cleanup for all models defined in the config:

```bash
php artisan resource-cleanup:run
```

Target specific models:

```bash
php artisan resource-cleanup:run --model=App\\Models\\Order
```

Override the retention period:

```bash
php artisan resource-cleanup:run --days=30
```

Preview what would be deleted without deleting anything:

```bash
php artisan resource-cleanup:run --dry-run
```

### Programmatic Usage

```php
use MyParcelCom\ResourceCleanup\ResourceCleanup;

$cleanup = app(ResourceCleanup::class);

// Clean up using the default retention period from config
$deleted = $cleanup->cleanup(\App\Models\Order::class);

// Clean up with a specific cutoff date
$deleted = $cleanup->cleanup(\App\Models\Order::class, Carbon::now()->subDays(30));

// Clean up multiple models at once
$results = $cleanup->cleanupAll([
    \App\Models\Order::class    => null,           // uses default
    \App\Models\AuditLog::class => Carbon::now()->subDays(14),
]);
```

### Per-Model Cutoff Dates

Implement the `CleanableResource` contract on any model to define its own retention period:

```php
use Carbon\Carbon;
use MyParcelCom\ResourceCleanup\Contracts\CleanableResource;

class AuditLog extends Model implements CleanableResource
{
    public static function getCleanupCutoffDate(): Carbon
    {
        return Carbon::now()->subDays(14);
    }
}
```

### Scheduling

Add to your `app/Console/Kernel.php` (Laravel 9/10) or `routes/console.php` (Laravel 11+):

```php
// Laravel 11+
Schedule::command('resource-cleanup:run')->daily();
```

## How Soft Deletes Are Handled

If a model uses the `SoftDeletes` trait, the package will **only** target soft-deleted records (`deleted_at` is set). Hard-deleted records are already gone. If the model does not use `SoftDeletes`, all records older than the cutoff are deleted permanently.

## Testing

### With Docker (recommended)

Build the container and run the test suite:

```bash
docker compose run --rm app
```

Run a specific test or filter:

```bash
docker compose run --rm app vendor/bin/phpunit --filter test_it_deletes_soft_deleted_records
```

Drop into a shell inside the container:

```bash
docker compose run --rm app bash
```

Run Composer commands without installing PHP locally:

```bash
docker compose run --rm app composer require some/package
```

### Without Docker

```bash
composer install
composer test
```

## License

MIT
