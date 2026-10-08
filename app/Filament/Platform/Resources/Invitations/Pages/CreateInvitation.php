<?php

namespace App\Filament\Platform\Resources\Invitations\Pages;

use App\Audit\Audit;
use App\Enums\AuditEvent;
use App\Filament\Platform\Resources\Invitations\InvitationResource;
use App\Models\TenantInvitation;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInvitation extends CreateRecord
{
    protected static string $resource = InvitationResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $invitation = new TenantInvitation([...$data, 'created_by' => auth()->id()]);

        $token = $invitation->issueToken();

        $invitation->save();

        app(Audit::class)->record(AuditEvent::InvitationCreated, properties: [
            'email' => $invitation->email,
            'plan' => $invitation->plan->value,
        ]);

        // The next page shows the link, once.
        session()->flash(ViewInvitation::LINK_SESSION_KEY, $invitation->registrationUrl($token));

        return $invitation;
    }

    protected function getRedirectUrl(): string
    {
        return InvitationResource::getUrl('view', ['record' => $this->record]);
    }
}
