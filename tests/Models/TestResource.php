<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Tests\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use MyParcelCom\ResourceCleanup\Contracts\CleanableResource;

class TestResource extends Model
{
    use SoftDeletes;

    protected $table = 'test_resources';

    protected $fillable = ['name', 'created_at'];
}

class TestResourceWithCustomCutoff extends TestResource implements CleanableResource
{
    public static function getCleanupCutoffDate(): Carbon
    {
        return Carbon::now()->subDays(30);
    }
}
