<?php

namespace App\Auth;

use App\Tenancy\BusinessScope;
use Illuminate\Auth\EloquentUserProvider;

/**
 * Loads the user who is signing in, or who is already signed in.
 *
 * Users are limited to the current business like other tenant data, but the
 * current business is worked out from the signed-in user. Finding that user
 * therefore cannot be limited to a business, or a platform user, who acts in
 * none, could never be found. Which door a user may enter is decided
 * afterwards, by User::canAccessPanel() and User::canAccessPos().
 */
class SignInUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(BusinessScope::class);
    }
}
