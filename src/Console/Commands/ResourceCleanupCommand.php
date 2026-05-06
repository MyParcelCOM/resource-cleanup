<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use MyParcelCom\ResourceCleanup\Contracts\CleanableResource;
use MyParcelCom\ResourceCleanup\ResourceCleanup;

class ResourceCleanupCommand extends Command
{
    protected $signature = 'resource-cleanup:run
                            {--model=* : One or more fully-qualified model class names to clean up}
                            {--days= : Override the retention period in days}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Permanently delete records older than the configured cutoff date.';

    public function handle(ResourceCleanup $cleanup): int
    {
        $models = $this->resolveModels();

        if (empty($models)) {
            $this->error('No models specified. Pass --model=App\\Models\\YourModel or define resource-cleanup.models in your config.');

            return self::FAILURE;
        }

        $cutoffDate = $this->resolveCutoffDate($cleanup);

        $this->info(sprintf(
            '%s records older than %s.',
            $this->option('dry-run') ? 'Scanning for' : 'Deleting',
            $cutoffDate->toDateTimeString(),
        ));

        $totalDeleted = 0;

        foreach ($models as $modelClass) {
            $modelCutoff = $this->modelCutoffDate($modelClass, $cutoffDate);

            if ($this->option('dry-run')) {
                $count = $this->dryRunCount($modelClass, $modelCutoff);
                $this->line(sprintf('  [dry-run] %s: %d record(s) would be deleted.', $modelClass, $count));
                $totalDeleted += $count;
                continue;
            }

            $deleted = $cleanup->cleanup($modelClass, $modelCutoff);
            $this->line(sprintf('  %s: %d record(s) deleted.', $modelClass, $deleted));
            $totalDeleted += $deleted;
        }

        $this->info(sprintf(
            'Done. Total: %d record(s) %s.',
            $totalDeleted,
            $this->option('dry-run') ? 'would be deleted' : 'deleted',
        ));

        return self::SUCCESS;
    }

    protected function resolveModels(): array
    {
        $fromOption = $this->option('model');
        if (!empty($fromOption)) {
            return $fromOption;
        }

        return config('resource-cleanup.models', []);
    }

    protected function resolveCutoffDate(ResourceCleanup $cleanup): Carbon
    {
        $days = $this->option('days');

        return $days !== null
            ? Carbon::now()->subDays((int) $days)
            : $cleanup->defaultCutoffDate();
    }

    protected function modelCutoffDate(string $modelClass, Carbon $default): Carbon
    {
        if (
            is_subclass_of($modelClass, CleanableResource::class)
            && !$this->option('days')
        ) {
            return $modelClass::getCleanupCutoffDate();
        }

        return $default;
    }

    protected function dryRunCount(string $modelClass, Carbon $cutoffDate): int
    {
        $query = $modelClass::query()->where('created_at', '<', $cutoffDate);

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass))) {
            $query->withTrashed()->onlyTrashed();
        }

        return $query->count();
    }
}
