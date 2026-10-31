<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property Order $record
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return "Order {$this->record->number_label}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receipt')
                ->label('Print receipt')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn (): string => route('pos.receipt', $this->record->ulid), shouldOpenInNewTab: true),
        ];
    }
}
