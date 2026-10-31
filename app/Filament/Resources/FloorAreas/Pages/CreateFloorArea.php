<?php

namespace App\Filament\Resources\FloorAreas\Pages;

use App\Filament\Resources\FloorAreas\FloorAreaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFloorArea extends CreateRecord
{
    protected static string $resource = FloorAreaResource::class;

    /**
     * Straight on to adding its tables.
     */
    protected function getRedirectUrl(): string
    {
        return FloorAreaResource::getUrl('edit', ['record' => $this->record]);
    }
}
