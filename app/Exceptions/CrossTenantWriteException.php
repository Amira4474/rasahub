<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when code tries to create, move, read or change a record outside
 * the business the current request is acting in.
 */
class CrossTenantWriteException extends RuntimeException
{
    public static function for(string $model): self
    {
        return new self("Refused to reach a [{$model}] outside the current business.");
    }
}
