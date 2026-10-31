<?php

namespace App\Filament\Resources\Stations\Pages;

use App\Filament\Resources\Stations\StationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStations extends ManageRecords
{
    protected static string $resource = StationResource::class;

    protected ?string $subheading = 'Where food and drink are made. Choose each category\'s station under Menu → Categories.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
