<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money given back to a customer for a paid order: for some of its lines,
 * or as an amount. Paid in cash from the drawer of the shift it was given on.
 */
#[Fillable(['order_id', 'branch_id', 'shift_id', 'number', 'number_label', 'amount', 'method_name', 'reason', 'items', 'refunded_by', 'approved_by'])]
class Refund extends Model
{
    use HasUlids, UsesTenantDatabase;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'items' => 'array',
        ];
    }
}
