<?php

namespace App\Filament\Platform\Resources\Subscriptions\Pages;

use App\Audit\Audit;
use App\Enums\AuditEvent;
use App\Filament\Platform\Resources\Subscriptions\SubscriptionResource;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    public function getTitle(): string
    {
        return "Edit {$this->record->business->name}'s subscription";
    }

    protected function afterSave(): void
    {
        app(Audit::class)->record(AuditEvent::SubscriptionUpdated, $this->record->business, [
            'plan' => $this->record->plan->value,
            'starts_at' => $this->record->starts_at->toDateString(),
            'ends_at' => $this->record->ends_at->toDateString(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return SubscriptionResource::getUrl('index');
    }
}
