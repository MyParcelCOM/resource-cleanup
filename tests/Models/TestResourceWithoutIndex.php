<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class TestResourceWithoutIndex extends Model
{
    protected $table = 'test_resources_without_index';

    protected $fillable = ['name', 'created_at'];
}
