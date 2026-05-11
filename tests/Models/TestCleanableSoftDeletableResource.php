<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Tests\Models;

use Illuminate\Database\Eloquent\Builder;
use MyParcelCom\ResourceCleanup\Contracts\CleanableResource;

class TestCleanableSoftDeletableResource extends TestSoftDeletableResource implements CleanableResource
{
    public static function scopeCleanable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subYear());
    }
}
