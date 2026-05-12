<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestSoftDeletableResource extends Model
{
    use SoftDeletes;

    protected $table = 'test_soft_deletable_resources';

    protected $fillable = ['name', 'created_at'];
}
