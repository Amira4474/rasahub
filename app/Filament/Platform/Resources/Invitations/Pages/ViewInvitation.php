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
    public const LINK_SESSION_KEY = 'invitation_link';

    protected static string $resource = InvitationResource::class;

    /**
     * The registration link, present only straight after it was made.
     */
    public ?string $link = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->link = session(self::LINK_SESSION_KEY);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reissue')
                ->label('Make a new link')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (TenantInvitation $record): bool => auth()->user()->can('reissue', $record))
                ->requiresConfirmation()
                ->modalDescription('The old link stops working, and the invitation is valid for another '.TenantInvitation::DEFAULT_DAYS_VALID.' days.')
                ->action(function (TenantInvitation $record): void {
                    $token = $record->issueToken();
                    $record->expires_at = now()->addDays(TenantInvitation::DEFAULT_DAYS_VALID);
                    $record->save();

                    app(Audit::class)->record(AuditEvent::InvitationReissued, properties: ['email' => $record->email]);

                    $this->link = $record->registrationUrl($token);
                })
                ->successNotificationTitle('New link made'),
            Action::make('revoke')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (TenantInvitation $record): bool => auth()->user()->can('update', $record))
                ->requiresConfirmation()
                ->modalDescription('The link stops working straight away. This cannot be undone.')
                ->action(function (TenantInvitation $record): void {
                    $record->update(['revoked_at' => now()]);

                    app(Audit::class)->record(AuditEvent::InvitationRevoked, properties: ['email' => $record->email]);

                    $this->link = null;
                })
                ->successNotificationTitle('Invitation revoked'),
        ];
    }
}
