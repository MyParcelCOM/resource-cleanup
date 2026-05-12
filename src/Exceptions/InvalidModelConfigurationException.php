<?php

declare(strict_types=1);

namespace MyParcelCom\ResourceCleanup\Exceptions;

use RuntimeException;

class InvalidModelConfigurationException extends RuntimeException
{
    public function __construct(string $modelClass)
    {
        parent::__construct($modelClass . ' does not extend Laravel\'s Model class.');
    }
}
