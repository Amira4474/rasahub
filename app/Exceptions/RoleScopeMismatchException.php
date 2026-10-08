<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a platform role is given to a tenant user, or a business role
 * to a platform user.
 */
class RoleScopeMismatchException extends RuntimeException
{
    public static function for(string $role, bool $platformUser): self
    {
        return new self($platformUser
            ? "The business role [{$role}] cannot be given to a platform user."
            : "The platform role [{$role}] cannot be given to a user who belongs to a business.");
    }
}
