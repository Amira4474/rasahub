<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\AuditsRoleChanges;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use AuditsRoleChanges;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
