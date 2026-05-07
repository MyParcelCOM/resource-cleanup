<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Tests\Feature;

use Carbon\Carbon;
use MyParcelCom\ResourceCleanup\Tests\Models\TestCleanableResource;
use MyParcelCom\ResourceCleanup\Tests\Models\TestResource;
use MyParcelCom\ResourceCleanup\Tests\TestCase;

class ResourceCleanupCommandTest extends TestCase
{
    public function test_dry_run(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestResource::class]);

        $old = Carbon::now()->subDays(91);
        TestResource::create(['name' => 'old-1', 'created_at' => $old])->delete();
        TestResource::create(['name' => 'old-2', 'created_at' => $old])->delete();
        TestResource::create(['name' => 'old-3', 'created_at' => $old])->delete();
        TestResource::create(['name' => 'recent']);

        $this->artisan('resource-cleanup:run --dry-run')
            ->expectsOutput('[dry-run] ' . TestResource::class . ': 3 record(s) would be deleted.')
            ->assertSuccessful();

        // No records should have been deleted
        $this->assertSame(4, TestResource::withTrashed()->count());
    }

    public function test_resource_cleanup_all_configured_models(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestResource::class]);

        $old = Carbon::now()->subDays(91);
        TestResource::create(['name' => 'old-1', 'created_at' => $old])->delete();
        TestResource::create(['name' => 'old-2', 'created_at' => $old])->delete();
        TestResource::create(['name' => 'old-2', 'created_at' => $old]);
        TestResource::create(['name' => 'recent']);

        $this->artisan('resource-cleanup:run')
            ->expectsOutput(TestResource::class . ': 3 record(s) deleted.')
            ->expectsOutput('Done. Total: 3 record(s) deleted.')
            ->assertSuccessful();

        $this->assertSame(0, TestResource::withTrashed()->where('name', 'like', 'old-%')->count());
        $this->assertSame(1, TestResource::count());
    }

    public function test_resource_cleanup_valid_model_options(): void
    {
        $this->app['config']->set('resource-cleanup.models', [
            TestResource::class,
            TestCleanableResource::class,
        ]);

        $old = Carbon::now()->subDays(91);
        TestResource::create(['name' => 'old', 'created_at' => $old])->delete();

        $this->artisan('resource-cleanup:run', ['--model' => [TestResource::class]])
            ->expectsOutput(TestResource::class . ': 1 record(s) deleted.')
            ->assertSuccessful();
    }

    public function test_resource_cleanup_with_default_configured_cut_off_date(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestResource::class]);
        $this->app['config']->set('resource-cleanup.default_retention_days', 30);

        $old = Carbon::now()->subDays(31);
        $recent = Carbon::now()->subDays(20);

        TestResource::create(['name' => 'old', 'created_at' => $old])->delete();
        TestResource::create(['name' => 'recent', 'created_at' => $recent])->delete();

        $this->artisan('resource-cleanup:run')
            ->assertSuccessful();

        $this->assertSame(0, TestResource::withTrashed()->where('name', 'old')->count());
        $this->assertSame(1, TestResource::withTrashed()->where('name', 'recent')->count());
    }

    public function test_resource_cleanup_uses_scope_cleanable_on_cleanable_resource(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestCleanableResource::class]);

        // Older than the default 90-day cutoff but newer than 1 year — the default query would
        // delete this, but scopeCleanable() (which uses subYear()) should leave it untouched.
        $withinOneYear = Carbon::now()->subDays(180);
        TestCleanableResource::create(['name' => 'within-one-year', 'created_at' => $withinOneYear]);

        // Older than 1 year — scopeCleanable() should include this record.
        $olderThanOneYear = Carbon::now()->subDays(400);
        TestCleanableResource::create(['name' => 'older-than-one-year', 'created_at' => $olderThanOneYear]);

        $this->artisan('resource-cleanup:run')
            ->assertSuccessful();

        $this->assertSame(1, TestCleanableResource::where('name', 'within-one-year')->count());
        $this->assertSame(0, TestCleanableResource::withTrashed()->where('name', 'older-than-one-year')->count());
    }

    public function test_resource_cleanup_fails_without_configured_models(): void
    {
        $this->app['config']->set('resource-cleanup.models', []);

        $this->artisan('resource-cleanup:run')
            ->expectsOutput('No cleanable models defined. Add class-strings to resource-cleanup.models in your config, e.g. App\\Models\\YourModel.')
            ->assertFailed();
    }

    public function test_resource_cleanup_fails_with_invalid_model_options(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestResource::class]);

        $this->artisan('resource-cleanup:run', ['--model' => ['App\\Models\\NonExistent']])
            ->expectsOutput("Invalid model options specified. Valid models are: \n" . TestResource::class)
            ->assertFailed();
    }
}
