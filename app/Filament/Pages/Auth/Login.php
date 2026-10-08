<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * The tenant admin sign-in page. Anyone who may not come in gets the same
 * message as for a wrong password, with one exception: someone who would get
 * in if their company's subscription allowed it has proved who they are, so
 * they are told why.
 */
class Login extends BaseLogin
{
    /**
     * This runs only after the password has been checked.
     */
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if (parent::isUserAllowedToAccessPanel($user)) {
            return true;
        }

        if ($user instanceof User && $user->holdsAdminAccess() && ($problem = $user->businessAccessProblem()) !== null) {
            throw ValidationException::withMessages(['data.email' => $problem]);
        }

        return false;
    }
}
