<?php

namespace App\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use App\Tenancy\SuperImpose;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the audit trail of privileged actions.
 */
class Audit
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  bool|null  $superImpose  Leave null to detect it from the session.
     */
    public function record(
        AuditEvent $event,
        ?Business $business = null,
        array $properties = [],
        ?User $actor = null,
        ?bool $superImpose = null,
    ): AuditLog {
        $actor ??= Auth::hasUser() ? Auth::user() : null;

        return AuditLog::create([
            'event' => $event,
            'user_id' => $actor?->getKey(),
            'business_id' => $business?->getKey() ?? app(TenantContext::class)->businessId(),
            'super_impose' => $superImpose ?? ($actor !== null && app(SuperImpose::class)->businessIdFor($actor) !== null),
            'ip_address' => request()->ip(),
            'properties' => $properties === [] ? null : $properties,
        ]);
    }
}
