<?php

namespace App\Filament\Resources\ModifierGroups\Pages;

use App\Filament\Resources\ModifierGroups\ModifierGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModifierGroups extends ListRecords
{
    protected static string $resource = ModifierGroupResource::class;

    protected ?string $subheading = 'Reusable sets of choices, such as Size or Sugar level, attached to products on the product form.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New modifier group'),
        ];
    }
}
