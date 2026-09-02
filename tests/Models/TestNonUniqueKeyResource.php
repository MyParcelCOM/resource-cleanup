<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Tests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A model whose primary key column has no unique constraint — used to test
 * that the cleanup command's created_at guard prevents accidentally deleting
 * a new record that reuses a key value from a previously-deleted old record.
 */
class TestNonUniqueKeyResource extends Model
{
    protected $table = 'test_non_unique_key_resources';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['uuid', 'name', 'created_at'];
}
