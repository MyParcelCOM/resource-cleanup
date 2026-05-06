<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use MyParcelCom\ResourceCleanup\Contracts\CleanableResource;

class ResourceCleanup
{
    public function __construct(protected array $config) {}

    /**
     * Clean up records from the given model that are older than the cutoff date.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function cleanup(string $modelClass, ?Carbon $cutoffDate = null): int
    {
        $cutoffDate ??= $this->defaultCutoffDate();

        $query = $modelClass::query()->where('created_at', '<', $cutoffDate);

        if ($this->usesSoftDeletes($modelClass)) {
            $query->withTrashed()->onlyTrashed();
        }

        $deleted = 0;

        $query->chunkById(100, function (Collection $records) use (&$deleted) {
            foreach ($records as $record) {
                $record->forceDelete();
                $deleted++;
            }
        });

        return $deleted;
    }

    /**
     * Register a model for cleanup, optionally with a custom cutoff date.
     *
     * @param  array<class-string<Model>, Carbon|null>  $resources
     */
    public function cleanupAll(array $resources): array
    {
        $results = [];

        foreach ($resources as $modelClass => $cutoffDate) {
            $results[$modelClass] = $this->cleanup($modelClass, $cutoffDate instanceof Carbon ? $cutoffDate : null);
        }

        return $results;
    }

    public function defaultCutoffDate(): Carbon
    {
        $days = $this->config['default_retention_days'] ?? 90;

        return Carbon::now()->subDays($days);
    }

    protected function usesSoftDeletes(string $modelClass): bool
    {
        return in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive($modelClass),
        );
    }
}
