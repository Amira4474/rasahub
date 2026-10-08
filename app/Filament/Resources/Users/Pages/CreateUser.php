<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\AuditsRoleChanges;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * The new user is placed in the current business by the User model itself.
 */
class CreateUser extends CreateRecord
{
    use AuditsRoleChanges;

    protected static string $resource = UserResource::class;
}
