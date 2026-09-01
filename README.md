# resource-cleanup

[![Tests](https://github.com/myparcelcom/resource-cleanup/actions/workflows/tests.yml/badge.svg)](https://github.com/myparcelcom/resource-cleanup/actions/workflows/tests.yml)

A Laravel package to permanently delete soft-deleted or expired Eloquent records after a configurable cutoff date.

## Requirements

- PHP 8.1+
- Laravel 8, 9, 10, 11, or 12

## Installation

```bash
composer require myparcelcom/resource-cleanup
```

Update Laravel's package auto-discovery cache

```bash
php artisan package:discover
```

Publish the config file:

```bash
php artisan vendor:publish --tag=resource-cleanup-config
```

## Configuration

`config/resource-cleanup.php`:

```php
return [
    /*
     * Records older than this many days will be permanently deleted when no
     * custom CleanableResource scope is defined on the model.
     * Default: 180 days. Override with the RESOURCE_CLEANUP_RETENTION_DAYS env var.
     */
    'default_retention_days' => env('RESOURCE_CLEANUP_RETENTION_DAYS', 180),

    /*
     * Number of records fetched per chunk during deletion.
     * Reduce this if you experience memory pressure on large tables.
     */
    'cleanup_chunk_size' => env('RESOURCE_CLEANUP_CHUNK_SIZE', 500),

    /*
     * Fully-qualified class names of the Eloquent models that the cleanup
     * command is allowed to process. The command will refuse any --model
     * option that is not present in this list.
     */
    'models' => [
        \App\Models\Order::class,
        \App\Models\AuditLog::class,
    ],
];
```

## Usage

### Artisan command

Run cleanup for all models listed in the config:

```bash
php artisan resource-cleanup:run
```

Target one or more specific models from the config:

```bash
php artisan resource-cleanup:run --model="App\Models\Order"
php artisan resource-cleanup:run --model="App\Models\Order" --model="App\Models\AuditLog"
```

Preview what would be deleted without actually deleting anything:

```bash
php artisan resource-cleanup:run --dry-run
```

Delete at most N records per model in a single run:

```bash
php artisan resource-cleanup:run --limit=1000
```

Skip the `created_at` index validation (not recommended — may cause slow queries on large tables):

```bash
php artisan resource-cleanup:run --skip-index-check
```

### How deletion works

For each model the command:

1. Builds a query using the model's `scopeCleanable` scope (if it implements `CleanableResource`) or the default cutoff query (see below).
2. Fetches records in chunks of `cleanup_chunk_size` using `chunkById`, ordered by the model's primary key.
3. Deletes each chunk with a single `DELETE WHERE primary_key IN (...)` query that re-applies all original query conditions, ensuring no record outside the original scope is accidentally deleted — even if primary key values are reused over time.
4. Sleeps 25 ms between chunks to give the database breathing room.

For models that use [soft deletes](https://laravel.com/docs/eloquent#soft-deleting), the default query automatically includes `withTrashed()` so that both soft-deleted and non-deleted records older than the cutoff are permanently removed.

### Default cutoff query

When a model does **not** implement `CleanableResource`, the command builds the following query:

```php
Model::query()
    ->where('created_at', '<', Carbon::now()->subDays(config('resource-cleanup.default_retention_days')))
    ->withTrashed()   // only added when the model uses SoftDeletes
```

The table **must have an index on `created_at`** for this query to perform well. The command validates this at startup and aborts with an error if no such index exists. Use `--skip-index-check` to bypass the check (not recommended for production).

### Custom retention period with `CleanableResource`

Implement `CleanableResource` on a model to provide a fully custom Eloquent scope that determines which records are eligible for deletion. This bypasses the default `created_at` cutoff and index check entirely.

```php
use Illuminate\Database\Eloquent\Builder;
use MyParcelCom\ResourceCleanup\Contracts\CleanableResource;

class AuditLog extends Model implements CleanableResource
{
    public static function scopeCleanable(): Builder
    {
        return self::query()
            ->where('created_at', '<', now()->subYear())
            ->where('status', 'processed');
    }
}
```

The scope must return a `Builder`. The command calls `->count()` on it for `--dry-run` runs, and `->chunkById()` + `forceDelete()` for actual deletion — identical to the default path.

> **Tip:** `scopeCleanable` is a standard Laravel local scope. You can call it directly in application code via `AuditLog::cleanable()` if you ever need the query outside of the command.

### Non-standard primary keys

The command uses `Model::getKeyName()` to resolve the primary key column. If your model declares a custom `$primaryKey`, no additional configuration is required — the command will use it automatically.

## Testing

### With Docker (recommended)

A PostgreSQL service is included in `docker-compose.yml`. Run the full test suite:

```bash
docker compose run --rm app
```

Run a specific test or filter by name:

```bash
docker compose run --rm app vendor/bin/phpunit --filter test_dry_run_without_soft_deletes
```

Drop into a shell inside the container:

```bash
docker compose run --rm app bash
```

### Without Docker

```bash
composer install
vendor/bin/phpunit
```
