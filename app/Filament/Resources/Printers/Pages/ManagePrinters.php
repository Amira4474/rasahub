<?php

namespace App\Filament\Resources\Printers\Pages;

use App\Filament\Resources\Printers\PrinterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePrinters extends ManageRecords
{
    protected static string $resource = PrinterResource::class;

    protected ?string $subheading = 'Network thermal printers. Each branch runs the RasaHub print gateway, which needs the branch\'s gateway key from Branches. Choose each terminal\'s receipt printer under POS terminals.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
