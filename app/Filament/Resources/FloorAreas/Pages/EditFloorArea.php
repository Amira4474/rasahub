<?php

namespace App\Filament\Resources\FloorAreas\Pages;

use App\Filament\Resources\FloorAreas\FloorAreaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditFloorArea extends EditRecord
{
    protected static string $resource = FloorAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('plan')
                ->label('Floor plan')
                ->icon(Heroicon::OutlinedMap)
                ->url(fn (): string => FloorAreaResource::getUrl('plan', ['record' => $this->record])),
            Action::make('qrCodes')
                ->label('Print QR codes')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->visible(fn (): bool => FloorAreaResource::qrOrderingIncluded())
                ->url(fn (): string => route('qr.codes', $this->record->ulid), shouldOpenInNewTab: true),
            DeleteAction::make()
                ->modalDescription('Its tables are removed too.'),
        ];
    }
}
