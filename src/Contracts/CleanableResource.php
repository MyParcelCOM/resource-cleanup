<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Contracts;

use Carbon\Carbon;

/**
 * Implement this interface on any Eloquent model to make it self-describing
 * for the resource cleanup package.
 *
 * When the cleanup command picks up a model that implements this contract, it
 * will call getCleanupCutoffDate() instead of the global default.
 */
interface CleanableResource
{
    /**
     * Return the cutoff date for this model.
     * Records older than this date will be permanently deleted.
     */
    public static function getCleanupCutoffDate(): Carbon;
}
