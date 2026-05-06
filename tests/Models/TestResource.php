<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestResource extends Model
{
    use SoftDeletes;

    protected $table = 'test_resources';

    protected $fillable = ['name', 'created_at'];
}
