<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup;

use Illuminate\Support\ServiceProvider;
use MyParcelCom\ResourceCleanup\Console\Commands\ResourceCleanupCommand;

class ResourceCleanupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/resource-cleanup.php',
            'resource-cleanup',
        );

        $this->app->singleton(ResourceCleanup::class, function ($app) {
            return new ResourceCleanup(config('resource-cleanup'));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/resource-cleanup.php' => config_path('resource-cleanup.php'),
            ], 'resource-cleanup-config');

            $this->commands([
                ResourceCleanupCommand::class,
            ]);
        }
    }
}
