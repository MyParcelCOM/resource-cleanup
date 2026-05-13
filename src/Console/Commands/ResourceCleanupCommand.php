<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use MyParcelCom\ResourceCleanup\Contracts\CleanableResource;

class ResourceCleanupCommand extends Command
{
    protected $signature = 'resource-cleanup:run
                            {--model=* : One or more fully-qualified model class names to clean up}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Permanently delete records older than the configured cutoff date.';

    public function handle(): int
    {
        $cleanableModels = config('resource-cleanup.models', []);
        if (empty($cleanableModels)) {
            $this->error(
                'No cleanable models defined. Add class-strings to resource-cleanup.models in your config, e.g. App\\Models\\YourModel.',
            );

            return self::FAILURE;
        }

        $models = $this->resolveModels($cleanableModels, $this->option('model'));
        if (empty($models)) {
            $this->error(
                sprintf("Invalid model options specified. Valid models are:\n%s", implode("\n", $cleanableModels)),
            );

            return self::FAILURE;
        }

        $totalDeleted = 0;
        foreach ($models as $modelClass) {
            $query = $this->getCleanableQuery($modelClass);

            if ($this->option('dry-run')) {
                $count = $query->count();

                $this->line(sprintf('[dry-run] %s: %d record(s) would be deleted.', $modelClass, $count));

                $totalDeleted += $count;

                continue;
            }

            $deleted = 0;
            $query->chunkById(100, function (Collection $records) use (&$deleted) {
                foreach ($records as $record) {
                    $record->forceDelete();
                    $deleted++;
                }
            });

            $this->line(sprintf('%s: %d record(s) deleted.', $modelClass, $deleted));

            $totalDeleted += $deleted;
        }

        $this->info(
            sprintf(
                'Done. Total: %d record(s) %s.',
                $totalDeleted,
                $this->option('dry-run') ? 'would be deleted' : 'deleted',
            ),
        );

        return self::SUCCESS;
    }

    /**
     * @param string[]      $cleanableModels
     * @param string[]|null $modelOptions
     */
    protected function resolveModels(array $cleanableModels, ?array $modelOptions): array
    {
        if (!empty($modelOptions)) {
            // test if all model options are present in the cleanableModels array
            $areValidOptions = empty(array_diff($modelOptions, $cleanableModels));

            return $areValidOptions ? $modelOptions : [];
        }

        return $cleanableModels;
    }

    /**
     * @param class-string<Model> $modelClass
     */
    public function getCleanableQuery(string $modelClass): Builder
    {
        return is_subclass_of($modelClass, CleanableResource::class)
            ? $modelClass::cleanable()
            : $this->defaultCleanableQuery($modelClass);
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function defaultCleanableQuery(string $modelClass): Builder
    {
        $days = config('resource-cleanup.default_retention_days');
        $cutOffDate = Carbon::now()->subDays($days);

        $this->line(sprintf('Using default created_at cutoff date %s to clean up %s', $cutOffDate, $modelClass));

        $query = $modelClass::query()->where('created_at', '<', $cutOffDate);
        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return $query;
    }
}
