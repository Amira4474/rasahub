<?php

namespace App\Filament\Resources\DiscountPresets\Pages;

use App\Filament\Resources\DiscountPresets\DiscountPresetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDiscountPresets extends ManageRecords
{
    protected static string $resource = DiscountPresetResource::class;

    protected ?string $subheading = 'Discounts cashiers give with one tap. Any other discount needs a reason, and a supervisor\'s PIN unless the cashier may give one.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
