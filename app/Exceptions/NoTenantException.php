<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when tenant data is asked for while no business is being acted in.
 * There is no database to read from, so the request stops here.
 */
class NoTenantException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Tenant data was requested, but no business is being acted in.');
    }
}
