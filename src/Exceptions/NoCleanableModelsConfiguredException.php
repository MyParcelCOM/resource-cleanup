<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Exceptions;

use RuntimeException;

class NoCleanableModelsConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'No cleanable models defined. Add class-strings to resource-cleanup.models in your config, e.g. App\\Models\\YourModel.',
        );
    }
}
