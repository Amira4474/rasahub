<?php

namespace App\Filament\Platform\Resources\Invitations\Pages;

use App\Audit\Audit;
use App\Enums\AuditEvent;
use App\Filament\Platform\Resources\Invitations\InvitationResource;
use App\Models\TenantInvitation;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewInvitation extends ViewRecord
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revoke')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (TenantInvitation $record): bool => auth()->user()->can('update', $record))
                ->requiresConfirmation()
                ->modalDescription('The token stops working straight away. This cannot be undone.')
                ->action(function (TenantInvitation $record): void {
                    $record->update(['revoked_at' => now()]);

                    app(Audit::class)->record(AuditEvent::InvitationRevoked, properties: $record->auditProperties());
                })
                ->successNotificationTitle('Invitation revoked'),
        ];
    }
}
