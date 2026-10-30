<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product that may be chosen in a set meal choice, with any extra charge:
 * "Iced latte +2.00".
 */
#[Fillable(['set_group_id', 'product_id', 'extra_price', 'is_default', 'sort_order'])]
class SetGroupItem extends Model
{
    use UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'extra_price' => '0.00',
        'is_default' => false,
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<SetGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SetGroup::class, 'set_group_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extra_price' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }
}
