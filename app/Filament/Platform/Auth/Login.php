<?php

namespace App\Filament\Platform\Auth;

use App\Filament\Concerns\PointsToTheRightSignIn;
use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The platform sign-in page, for RasaHub's own staff. Restaurant staff who
 * come here with the right password are sent to their own sign-in page;
 * anyone else gets the same message as for a wrong password.
 */
class Login extends BaseLogin
{
    use PointsToTheRightSignIn;

    public function getHeading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication) ? parent::getHeading() : 'Platform Console';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getSubheading()
            : 'For RasaHub staff only.';
    }

    /**
     * This runs only after the password has been checked.
     */
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if (parent::isUserAllowedToAccessPanel($user)) {
            return true;
        }

        if ($user instanceof User && ! $user->isPlatformUser()) {
            if ($user->holdsAdminAccess()) {
                $this->sendToTheRightSignIn(
                    'This is a restaurant account. Sign in at Business Admin instead.',
                    'Go to Business Admin sign-in',
                    Filament::getPanel('admin')->getLoginUrl(),
                );
            }

            if ($user->holdsPosAccess()) {
                $this->sendToTheRightSignIn(
                    'This is a restaurant staff account. Sign in at the POS instead.',
                    'Go to POS sign-in',
                    route('pos.login'),
                );
            }
        }

        return false;
    }
}
