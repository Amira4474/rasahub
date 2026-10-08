<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Filament\Platform\Actions\SuperImposeAction;
use App\Filament\Platform\Resources\Tenants\TenantResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * Everything the platform knows about one tenant, and the way into it.
 */
class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SuperImposeAction::make(),
            EditAction::make(),
        ];
    }
}
