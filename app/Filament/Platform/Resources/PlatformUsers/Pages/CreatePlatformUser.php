<?php

namespace App\Filament\Platform\Resources\PlatformUsers\Pages;

use App\Filament\Concerns\AuditsRoleChanges;
use App\Filament\Platform\Resources\PlatformUsers\PlatformUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformUser extends CreateRecord
{
    use AuditsRoleChanges;

    protected static string $resource = PlatformUserResource::class;
}
