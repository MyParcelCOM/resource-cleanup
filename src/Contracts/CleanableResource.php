<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * Implement this interface on any Eloquent model to make it self-describing
 * for the resource cleanup package.
 *
 * When the cleanup command picks up a model that implements this contract, it
 * will call ModelClass::cleanable() instead of the global default.
 */
interface CleanableResource
{
    public static function scopeCleanable(): Builder;
}
