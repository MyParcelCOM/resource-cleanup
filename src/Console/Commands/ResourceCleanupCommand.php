<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use MyParcelCom\ResourceCleanup\Contracts\CleanableResource;
use MyParcelCom\ResourceCleanup\Exceptions\InvalidModelConfigurationException;
use MyParcelCom\ResourceCleanup\Exceptions\MissingCreatedAtIndexException;
use MyParcelCom\ResourceCleanup\Exceptions\NoCleanableModelsConfiguredException;

class ResourceCleanupCommand extends Command
{
    protected $signature = 'resource-cleanup:run
                            {--model=* : One or more fully-qualified model class names to clean up}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--skip-index-check : Skip the created_at index validation (not recommended, may cause slow queries)}';

    protected $description = 'Permanently delete records older than the configured cutoff date.';

    public function handle(): int
    {
        try {
            $cleanableModels = $this->getCleanableModelsConfig();
        } catch (NoCleanableModelsConfiguredException|InvalidModelConfigurationException $e) {
            $this->error($e->getMessage());

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
            try {
                $query = $this->getCleanableQuery($modelClass);
            } catch (MissingCreatedAtIndexException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            if ($this->option('dry-run')) {
                $count = $query->count();

                $this->line(sprintf('  [dry-run] %s: %d record(s) would be deleted.', $modelClass, $count));

                $totalDeleted += $count;

                continue;
            }

            $deleted = 0;
            $query->chunkById(
                config('resource-cleanup.cleanup_chunk_size'),
                function (Collection $records) use ($modelClass, &$deleted) {
                    $deleted += $modelClass::query()->whereIn('id', $records->pluck('id'))->forceDelete();

                    // 25ms sleep gives the DB breathing room for consecutive queries
                    usleep(25000);
                },
            );

            $this->line(sprintf('  %s: %d record(s) deleted.', $modelClass, $deleted));

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
     * @return array<class-string<Model>>
     *
     * @throws NoCleanableModelsConfiguredException
     * @throws InvalidModelConfigurationException
     */
    private function getCleanableModelsConfig(): array
    {
        $cleanableModels = config('resource-cleanup.models', []);
        if (empty($cleanableModels)) {
            throw new NoCleanableModelsConfiguredException();
        }

        foreach ($cleanableModels as $modelClass) {
            if (!is_subclass_of($modelClass, Model::class)) {
                throw new InvalidModelConfigurationException($modelClass);
            }
        }

        return $cleanableModels;
    }

    /**
     * @param string[]      $cleanableModels
     * @param string[]|null $modelOptions
     */
    private function resolveModels(array $cleanableModels, ?array $modelOptions): array
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
    private function getCleanableQuery(string $modelClass): Builder
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
        if (!$this->option('skip-index-check')) {
            $this->validateCreatedAtIndex($modelClass);
        }

        $days = config('resource-cleanup.default_retention_days');
        $cutOffDate = Carbon::now()->subDays($days);

        $this->line(sprintf('Using default created_at cutoff date %s to clean up %s', $cutOffDate, $modelClass));

        $query = $modelClass::query()->where('created_at', '<', $cutOffDate);
        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * @param class-string<Model> $modelClass
     */
    public function validateCreatedAtIndex(string $modelClass): void
    {
        $table = resolve($modelClass)->getTable();
        $indexes = DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexdef LIKE '%created_at%'",
            [$table],
        );
        if (empty($indexes)) {
            throw new MissingCreatedAtIndexException($table);
        }
    }
}
