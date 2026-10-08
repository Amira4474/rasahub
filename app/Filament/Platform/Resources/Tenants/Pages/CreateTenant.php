<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Filament\Platform\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;
}
