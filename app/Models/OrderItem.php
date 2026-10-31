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
#[Fillable(['order_id', 'parent_id', 'product_id', 'name', 'unit_price', 'quantity', 'modifiers_total', 'line_total', 'note', 'kitchen_status', 'sent_at'])]
class OrderItem extends Model
{
    use HasUlids, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'modifiers_total' => '0.00',
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

    public function isSent(): bool
    {
        return $this->kitchen_status !== KitchenStatus::Pending;
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
            'kitchen_status' => KitchenStatus::class,
            'sent_at' => 'datetime',
        ];
    }
}
