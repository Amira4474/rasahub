<?php

namespace App\Filament\Resources\PaymentMethods\Pages;

use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Models\PaymentMethod;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentMethods extends ListRecords
{
    protected static string $resource = PaymentMethodResource::class;

    protected ?string $subheading = 'The ways the restaurant takes payment, in the order the POS shows them. Drag to reorder.';

    public function mount(): void
    {
        // A restaurant that has never opened this page starts with Cash,
        // Card, QR payment and Other.
        PaymentMethod::available();

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
