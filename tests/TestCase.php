<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Tests;

use MyParcelCom\ResourceCleanup\ResourceCleanupServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ResourceCleanupServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'pgsql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', 5432),
            'database' => env('DB_DATABASE', 'resource_cleanup_testing'),
            'username' => env('DB_USERNAME', 'testing'),
            'password' => env('DB_PASSWORD', 'testing'),
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }
}
