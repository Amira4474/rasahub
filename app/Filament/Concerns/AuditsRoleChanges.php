<?php

namespace App\Filament\Concerns;

use App\Audit\Audit;
use App\Enums\AuditEvent;

/**
 * For create and edit pages of users: records in the audit log when the
 * roles a user holds are different after saving.
 */
trait AuditsRoleChanges
{
    /** @var list<string> */
    protected array $rolesBeforeSave = [];

    protected function beforeSave(): void
    {
        $this->rolesBeforeSave = $this->currentRoleNames();
    }

    protected function afterSave(): void
    {
        $this->auditRoleChange();
    }

    protected function afterCreate(): void
    {
        $this->auditRoleChange();
    }

    private function auditRoleChange(): void
    {
        $after = $this->currentRoleNames();

        if ($after === $this->rolesBeforeSave) {
            return;
        }

        app(Audit::class)->record(AuditEvent::UserRolesChanged, $this->record->business, [
            'user_id' => $this->record->getKey(),
            'email' => $this->record->email,
            'from' => $this->rolesBeforeSave,
            'to' => $after,
        ]);
    }

    /**
     * @return list<string>
     */
    private function currentRoleNames(): array
    {
        return $this->record->roles()->pluck('name')->sort()->values()->all();
    }
}
