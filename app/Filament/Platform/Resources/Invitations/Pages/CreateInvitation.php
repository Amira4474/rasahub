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
        $invitation = TenantInvitation::create([...$data, 'created_by' => auth()->id()]);

        app(Audit::class)->record(AuditEvent::InvitationCreated, properties: $invitation->auditProperties());

        return $invitation;
    }

    protected function getRedirectUrl(): string
    {
        return InvitationResource::getUrl('view', ['record' => $this->record]);
    }
}
