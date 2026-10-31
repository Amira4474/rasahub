<?php

namespace App\Filament\Pages\Auth;

use App\Enums\Permission;
use App\Filament\Concerns\PointsToTheRightSignIn;
use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/**
 * The tenant admin sign-in page. Anyone who may not come in gets the same
 * message as for a wrong password, with two exceptions, both for someone who
 * has proved who they are: someone who would get in if their company's
 * subscription allowed it is told why, and a platform user is sent to the
 * platform's own sign-in page.
 */
class Login extends BaseLogin
{
    use PointsToTheRightSignIn;

    public function getHeading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication) ? parent::getHeading() : 'Business Admin';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getSubheading()
            : 'Sign in to manage your restaurant.';
    }

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

        if ($user instanceof User && $user->isPlatformUser() && $user->is_active && $user->can(Permission::AccessPlatform)) {
            $this->sendToTheRightSignIn(
                'This is a RasaHub platform account. Sign in at the Platform Console instead.',
                'Go to Platform Console sign-in',
                url('/platform/login'),
            );
        }

        return false;
    }
}
