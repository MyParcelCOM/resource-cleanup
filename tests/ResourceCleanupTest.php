<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Tests;

use Carbon\Carbon;
use MyParcelCom\ResourceCleanup\ResourceCleanup;
use MyParcelCom\ResourceCleanup\Tests\Models\TestResource;
use MyParcelCom\ResourceCleanup\Tests\Models\TestResourceWithCustomCutoff;

class ResourceCleanupTest extends TestCase
{
    private ResourceCleanup $cleanup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanup = new ResourceCleanup(['default_retention_days' => 90]);
    }

    public function test_it_deletes_soft_deleted_records_older_than_cutoff(): void
    {
        // Old soft-deleted record — should be cleaned up
        $old = TestResource::create(['name' => 'old', 'created_at' => Carbon::now()->subDays(120)]);
        $old->delete();

        // Recent soft-deleted record — should be kept
        $recent = TestResource::create(['name' => 'recent', 'created_at' => Carbon::now()->subDays(10)]);
        $recent->delete();

        $deleted = $this->cleanup->cleanup(TestResource::class);

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('test_resources', ['id' => $old->id]);
        $this->assertDatabaseHas('test_resources', ['id' => $recent->id]);
    }

    public function test_it_respects_a_custom_cutoff_date(): void
    {
        $record = TestResource::create(['name' => 'target', 'created_at' => Carbon::now()->subDays(50)]);
        $record->delete();

        // Default cutoff is 90 days, so this record would normally be kept.
        // We override to 30 days, so it should now be deleted.
        $deleted = $this->cleanup->cleanup(TestResource::class, Carbon::now()->subDays(30));

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('test_resources', ['id' => $record->id]);
    }

    public function test_it_does_not_delete_records_within_retention_period(): void
    {
        $record = TestResource::create(['name' => 'keep-me', 'created_at' => Carbon::now()->subDays(30)]);
        $record->delete();

        $deleted = $this->cleanup->cleanup(TestResource::class);

        $this->assertSame(0, $deleted);
        $this->assertDatabaseHas('test_resources', ['id' => $record->id]);
    }

    public function test_cleanup_all_handles_multiple_models(): void
    {
        $old = TestResource::create(['name' => 'old', 'created_at' => Carbon::now()->subDays(120)]);
        $old->delete();

        $results = $this->cleanup->cleanupAll([
            TestResource::class => null,
        ]);

        $this->assertArrayHasKey(TestResource::class, $results);
        $this->assertSame(1, $results[TestResource::class]);
    }

    public function test_default_cutoff_date_uses_config(): void
    {
        $cleanup = new ResourceCleanup(['default_retention_days' => 30]);
        $cutoff = $cleanup->defaultCutoffDate();

        $this->assertTrue($cutoff->isBefore(Carbon::now()->subDays(29)));
        $this->assertTrue($cutoff->isAfter(Carbon::now()->subDays(31)));
    }

    public function test_model_implementing_cleanable_resource_uses_its_own_cutoff(): void
    {
        // 40 days old — between the custom 30-day cutoff and the default 90-day cutoff
        $record = TestResourceWithCustomCutoff::create([
            'name'       => 'custom-cutoff',
            'created_at' => Carbon::now()->subDays(40),
        ]);
        $record->delete();

        // Using the custom model's cutoff (30 days), this record should be deleted
        $deleted = $this->cleanup->cleanup(
            TestResourceWithCustomCutoff::class,
            TestResourceWithCustomCutoff::getCleanupCutoffDate(),
        );

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('test_resources', ['id' => $record->id]);
    }
}
