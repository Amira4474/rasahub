<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order at a branch, from the first item to the last payment. It keeps
 * the tax settings it was sold under, and copies of every name and price.
 */
#[Fillable([
    'branch_id', 'terminal_id', 'shift_id', 'business_date', 'number', 'number_label', 'receipt_number',
    'type', 'source', 'dining_table_id', 'table_label', 'guests', 'customer_id', 'status', 'payment_status', 'note',
    'cancel_reason', 'cancelled_by', 'cancel_approved_by', 'cancelled_at',
    'sst_enabled', 'sst_rate', 'prices_include_sst', 'service_charge_rate', 'round_to_five_sen',
    'subtotal', 'discount_total', 'service_charge', 'sst', 'rounding', 'total', 'paid', 'refunded', 'opened_by', 'completed_at',
])]
class Order extends Model
{
    use HasUlids, UsesTenantDatabase;

    /**
     * Mirror the column defaults, so a new order has them before it is
     * reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'pos',
        'subtotal' => '0.00',
        'discount_total' => '0.00',
        'refunded' => '0.00',
        'service_charge' => '0.00',
        'sst' => '0.00',
        'rounding' => '0.00',
        'total' => '0.00',
        'paid' => '0.00',
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class)->withTrashed();
    }

    /**
     * Named so as not to clash with Eloquent's own $table property.
     *
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * The lines of the order, set meal parts under their set.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function lines(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }

    /**
     * Every discount on the order, on lines and on the whole order.
     *
     * @return HasMany<OrderDiscount, $this>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class)->orderBy('id');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('id');
    }

    /**
     * What can still be refunded: what was paid, less what was refunded.
     */
    public function refundable(): string
    {
        return bcsub($this->paid, $this->refunded, 2);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * Orders still being served or paid for.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled]);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function balance(): string
    {
        return bcsub($this->total, $this->paid, 2);
    }

    /**
     * Where the order is: its table, or Takeaway.
     */
    public function placeLabel(): string
    {
        return match (true) {
            $this->type === OrderType::Takeaway => 'Takeaway',
            $this->diningTable !== null => "Table {$this->diningTable->name}",
            filled($this->table_label) => "Table {$this->table_label}",
            default => 'Dine-in',
        };
    }

    /**
     * The receipt number as printed: the branch code and a running number.
     */
    public function receiptLabel(): ?string
    {
        return $this->receipt_number === null ? null : $this->branch->code.'-R'.str_pad((string) $this->receipt_number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'sst_enabled' => 'boolean',
            'sst_rate' => 'decimal:2',
            'prices_include_sst' => 'boolean',
            'service_charge_rate' => 'decimal:2',
            'round_to_five_sen' => 'boolean',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'refunded' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'service_charge' => 'decimal:2',
            'sst' => 'decimal:2',
            'rounding' => 'decimal:2',
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }
}
