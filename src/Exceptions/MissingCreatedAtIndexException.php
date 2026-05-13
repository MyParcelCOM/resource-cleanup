<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Exceptions;

use RuntimeException;

class MissingCreatedAtIndexException extends RuntimeException
{
    public function __construct(string $table)
    {
        parent::__construct("Table '{$table}' has no index on created_at. Aborting.");
    }
}
