<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A discount given on an order, or on one line of it. The amount is worked
 * out again whenever the order changes.
 */
#[Fillable(['order_id', 'order_item_id', 'discount_preset_id', 'name', 'type', 'value', 'amount', 'reason', 'applied_by', 'approved_by'])]
class OrderDiscount extends Model
{
    use UsesTenantDatabase;

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function label(): string
    {
        return $this->type === DiscountType::Percent
            ? "{$this->name} (".rtrim(rtrim($this->value, '0'), '.').'%)'
            : $this->name;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
