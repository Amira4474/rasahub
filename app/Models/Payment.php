<?php

namespace App\Models;

use App\Enums\PaymentMethodType;
use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received for an order. An order may be paid in several parts.
 */
#[Fillable(['ulid', 'order_id', 'shift_id', 'payment_method_id', 'method_name', 'method_type', 'amount', 'tendered', 'change', 'reference', 'received_by', 'received_at'])]
class Payment extends Model
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method_type' => PaymentMethodType::class,
            'amount' => 'decimal:2',
            'tendered' => 'decimal:2',
            'change' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }
}
