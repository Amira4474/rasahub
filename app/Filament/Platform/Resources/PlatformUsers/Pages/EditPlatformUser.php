<?php

namespace App\Filament\Platform\Resources\PlatformUsers\Pages;

use App\Filament\Concerns\AuditsRoleChanges;
use App\Filament\Platform\Resources\PlatformUsers\PlatformUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlatformUser extends EditRecord
{
    use AuditsRoleChanges;

    protected static string $resource = PlatformUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
