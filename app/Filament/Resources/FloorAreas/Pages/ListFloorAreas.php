<?php

namespace App\Filament\Resources\FloorAreas\Pages;

use App\Filament\Resources\FloorAreas\FloorAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFloorAreas extends ListRecords
{
    protected static string $resource = FloorAreaResource::class;

    protected ?string $subheading = 'The parts of each branch\'s dining room, such as Indoor or Patio, and their tables.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
