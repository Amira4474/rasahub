<?php

namespace App\Filament\Resources\KitchenScreens\Pages;

use App\Filament\Resources\KitchenScreens\KitchenScreenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKitchenScreens extends ManageRecords
{
    protected static string $resource = KitchenScreenResource::class;

    protected ?string $subheading = 'Screens in the kitchen or bar. Kitchen staff sign in to the POS on the screen\'s device and choose it.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
