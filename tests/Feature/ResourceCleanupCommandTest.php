<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Tests\Feature;

use Carbon\Carbon;
use MyParcelCom\ResourceCleanup\Tests\Models\TestCleanableResource;
use MyParcelCom\ResourceCleanup\Tests\Models\TestCleanableSoftDeletableResource;
use MyParcelCom\ResourceCleanup\Tests\Models\TestResource;
use MyParcelCom\ResourceCleanup\Tests\Models\TestSoftDeletableResource;
use MyParcelCom\ResourceCleanup\Tests\TestCase;

class ResourceCleanupCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Dry run
    // -------------------------------------------------------------------------

    public function test_dry_run_without_soft_deletes(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestResource::class]);

        $old = Carbon::now()->subDays(91);
        TestResource::create(['name' => 'old-1', 'created_at' => $old]);
        TestResource::create(['name' => 'old-2', 'created_at' => $old]);
        TestResource::create(['name' => 'old-3', 'created_at' => $old]);
        TestResource::create(['name' => 'recent']);

        $this->artisan('resource-cleanup:run --dry-run')
            ->expectsOutput('Done. Total: 3 record(s) would be deleted.')
            ->assertSuccessful();

        $this->assertSame(4, TestResource::count());
    }

    public function test_dry_run_with_soft_deletes(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestSoftDeletableResource::class]);

        $old = Carbon::now()->subDays(91);
        TestSoftDeletableResource::create(['name' => 'old-1', 'created_at' => $old])->delete();
        TestSoftDeletableResource::create(['name' => 'old-2', 'created_at' => $old])->delete();
        TestSoftDeletableResource::create(['name' => 'old-3', 'created_at' => $old])->delete();
        TestSoftDeletableResource::create(['name' => 'recent']);

        $this->artisan('resource-cleanup:run --dry-run')
            ->expectsOutput('Done. Total: 3 record(s) would be deleted.')
            ->assertSuccessful();

        $this->assertSame(4, TestSoftDeletableResource::withTrashed()->count());
    }

    // -------------------------------------------------------------------------
    // Cleanup — all configured models
    // -------------------------------------------------------------------------

    public function test_resource_cleanup_deletes_old_records_without_soft_deletes(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestResource::class]);

        $old = Carbon::now()->subDays(91);
        TestResource::create(['name' => 'old-1', 'created_at' => $old]);
        TestResource::create(['name' => 'old-2', 'created_at' => $old]);
        TestResource::create(['name' => 'recent']);

        $this->artisan('resource-cleanup:run')
            ->expectsOutput('Done. Total: 2 record(s) deleted.')
            ->assertSuccessful();

        $this->assertSame(0, TestResource::where('name', 'like', 'old-%')->count());
        $this->assertSame(1, TestResource::count());
    }

    public function test_resource_cleanup_deletes_old_soft_deleted_records(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestSoftDeletableResource::class]);

        $old = Carbon::now()->subDays(91);
        TestSoftDeletableResource::create(['name' => 'old-deleted-1', 'created_at' => $old])->delete();
        TestSoftDeletableResource::create(['name' => 'old-deleted-2', 'created_at' => $old])->delete();
        TestSoftDeletableResource::create(['name' => 'old-not-deleted', 'created_at' => $old]);
        TestSoftDeletableResource::create(['name' => 'recent']);

        $this->artisan('resource-cleanup:run')
            ->expectsOutput('Done. Total: 3 record(s) deleted.')
            ->assertSuccessful();

        $this->assertSame(0, TestSoftDeletableResource::withTrashed()->where('name', 'like', 'old-%')->count());
        $this->assertSame(1, TestSoftDeletableResource::count());
    }

    public function test_resource_cleanup_does_not_delete_recently_soft_deleted_records(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestSoftDeletableResource::class]);

        // Recently soft-deleted — within retention period, must be kept.
        TestSoftDeletableResource::create(['name' => 'recently-soft-deleted'])->delete();

        $this->artisan('resource-cleanup:run')
            ->assertSuccessful();

        $this->assertSame(1, TestSoftDeletableResource::withTrashed()->count());
    }

    // -------------------------------------------------------------------------
    // Cleanup — model option filtering
    // -------------------------------------------------------------------------

    public function test_resource_cleanup_valid_model_options(): void
    {
        $this->app['config']->set('resource-cleanup.models', [
            TestSoftDeletableResource::class,
            TestCleanableSoftDeletableResource::class,
        ]);

        $old = Carbon::now()->subDays(91);
        TestSoftDeletableResource::create(['name' => 'old', 'created_at' => $old])->delete();

        $this->artisan('resource-cleanup:run', ['--model' => [TestSoftDeletableResource::class]])
            ->expectsOutput('  ' . TestSoftDeletableResource::class . ': 1 record(s) deleted.')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // Cleanup — default cut-off date
    // -------------------------------------------------------------------------

    public function test_resource_cleanup_with_default_configured_cut_off_date_without_soft_deletes(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestResource::class]);
        $this->app['config']->set('resource-cleanup.default_retention_days', 30);

        TestResource::create(['name' => 'old', 'created_at' => Carbon::now()->subDays(31)]);
        TestResource::create(['name' => 'recent', 'created_at' => Carbon::now()->subDays(20)]);

        $this->artisan('resource-cleanup:run')->assertSuccessful();

        $this->assertSame(0, TestResource::where('name', 'old')->count());
        $this->assertSame(1, TestResource::where('name', 'recent')->count());
    }

    public function test_resource_cleanup_with_default_configured_cut_off_date_with_soft_deletes(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestSoftDeletableResource::class]);
        $this->app['config']->set('resource-cleanup.default_retention_days', 30);

        TestSoftDeletableResource::create(['name' => 'old', 'created_at' => Carbon::now()->subDays(31)])->delete();
        TestSoftDeletableResource::create(['name' => 'recent', 'created_at' => Carbon::now()->subDays(20)])->delete();

        $this->artisan('resource-cleanup:run')->assertSuccessful();

        $this->assertSame(0, TestSoftDeletableResource::withTrashed()->where('name', 'old')->count());
        $this->assertSame(1, TestSoftDeletableResource::withTrashed()->where('name', 'recent')->count());
    }

    // -------------------------------------------------------------------------
    // Cleanup — CleanableResource scope
    // -------------------------------------------------------------------------

    public function test_resource_cleanup_uses_scope_cleanable_on_non_soft_deletable_cleanable_resource(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestCleanableResource::class]);

        // Older than the default 90-day cutoff but newer than 1 year — the default query would
        // delete this, but scopeCleanable() (which uses subYear()) should leave it untouched.
        TestCleanableResource::create(['name' => 'within-one-year', 'created_at' => Carbon::now()->subDays(180)]);

        // Older than 1 year — scopeCleanable() should include this record.
        TestCleanableResource::create(['name' => 'older-than-one-year', 'created_at' => Carbon::now()->subDays(400)]);

        $this->artisan('resource-cleanup:run')->assertSuccessful();

        $this->assertSame(1, TestCleanableResource::where('name', 'within-one-year')->count());
        $this->assertSame(0, TestCleanableResource::where('name', 'older-than-one-year')->count());
    }

    public function test_resource_cleanup_uses_scope_cleanable_on_soft_deletable_cleanable_resource(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestCleanableSoftDeletableResource::class]);

        // Older than the default 90-day cutoff but newer than 1 year — the default query would
        // delete this, but scopeCleanable() (which uses subYear()) should leave it untouched.
        TestCleanableSoftDeletableResource::create(['name' => 'within-one-year', 'created_at' => Carbon::now()->subDays(180)]);

        // Older than 1 year — scopeCleanable() should include this record.
        TestCleanableSoftDeletableResource::create(['name' => 'older-than-one-year', 'created_at' => Carbon::now()->subDays(400)]);

        $this->artisan('resource-cleanup:run')->assertSuccessful();

        $this->assertSame(1, TestCleanableSoftDeletableResource::where('name', 'within-one-year')->count());
        $this->assertSame(0, TestCleanableSoftDeletableResource::withTrashed()->where('name', 'older-than-one-year')->count());
    }

    // -------------------------------------------------------------------------
    // Failure cases
    // -------------------------------------------------------------------------

    public function test_resource_cleanup_fails_without_configured_models(): void
    {
        $this->app['config']->set('resource-cleanup.models', []);

        $this->artisan('resource-cleanup:run')
            ->expectsOutput('No cleanable models defined. Add class-strings to resource-cleanup.models in your config, e.g. App\\Models\\YourModel.')
            ->assertFailed();
    }

    public function test_resource_cleanup_fails_with_invalid_model_options(): void
    {
        $this->app['config']->set('resource-cleanup.models', [TestSoftDeletableResource::class]);

        $this->artisan('resource-cleanup:run', ['--model' => ['App\\Models\\NonExistent']])
            ->expectsOutput("Invalid model options specified. Valid models are:\n" . TestSoftDeletableResource::class)
            ->assertFailed();
    }
}
