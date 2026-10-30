<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One choice within a set meal, such as "Choose a drink".
 */
#[Fillable(['product_id', 'name', 'min_select', 'max_select', 'sort_order'])]
class SetGroup extends Model
{
    use UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'min_select' => 1,
        'max_select' => 1,
        'sort_order' => 0,
    ];

    /**
     * The set meal this choice belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<SetGroupItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SetGroupItem::class)->orderBy('sort_order');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_select' => 'integer',
            'max_select' => 'integer',
        ];
    }
}
