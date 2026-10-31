<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer at a table, from the QR ordering page, calling staff or asking
 * for the bill. Open until staff deal with it.
 */
#[Fillable(['branch_id', 'dining_table_id', 'type', 'resolved_at', 'resolved_by'])]
class TableRequest extends Model
{
    use UsesTenantDatabase;

    public const CALL_STAFF = 'call_staff';

    public const BILL = 'bill';

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id')->withTrashed();
    }

    public function label(): string
    {
        return $this->type === self::BILL ? 'Asks for the bill' : 'Calls staff';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
