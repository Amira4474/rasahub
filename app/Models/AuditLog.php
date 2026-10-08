<?php

namespace App\Models;

use App\Enums\AuditEvent;
use App\Tenancy\BusinessScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in the audit trail. Entries are written once and never edited.
 * This is platform data: it is not limited to a business.
 */
#[Fillable(['event', 'user_id', 'business_id', 'super_impose', 'ip_address', 'properties'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * The user who acted, whichever business they belong to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(BusinessScope::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'super_impose' => 'boolean',
            'properties' => 'array',
        ];
    }
}
