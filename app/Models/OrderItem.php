<?php

namespace App\Models;

use App\Enums\KitchenStatus;
use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A line of an order, or a part of a set meal under its set's line. Names
 * and prices are copies taken at the moment of sale.
 */
#[Fillable(['order_id', 'parent_id', 'product_id', 'name', 'unit_price', 'quantity', 'refunded_quantity', 'modifiers_total', 'line_total', 'discount_total', 'note', 'kitchen_status', 'sent_at', 'voided_at', 'void_reason', 'voided_by', 'void_approved_by'])]
class OrderItem extends Model
{
    use HasUlids, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'modifiers_total' => '0.00',
        'discount_total' => '0.00',
        'refunded_quantity' => 0,
        'kitchen_status' => 'pending',
    ];

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
     * The parts of a set meal.
     *
     * @return HasMany<self, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    /**
     * @return HasMany<OrderItemModifier, $this>
     */
    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderDiscount, $this>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class)->orderBy('id');
    }

    public function isSent(): bool
    {
        return $this->kitchen_status !== KitchenStatus::Pending;
    }

    public function isVoided(): bool
    {
        return $this->kitchen_status === KitchenStatus::Voided;
    }

    /**
     * The line with its set meal parts, before any discount.
     */
    public function grossTotal(): string
    {
        return bcadd($this->line_total, (string) $this->parts->sum('line_total'), 2);
    }

    /**
     * The line with its parts, after its own discounts.
     */
    public function netTotal(): string
    {
        return bcsub($this->grossTotal(), $this->discount_total, 2);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'modifiers_total' => 'decimal:2',
            'line_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'refunded_quantity' => 'integer',
            'voided_at' => 'datetime',
            'kitchen_status' => KitchenStatus::class,
            'sent_at' => 'datetime',
        ];
    }
}
