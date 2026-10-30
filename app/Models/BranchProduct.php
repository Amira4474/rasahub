<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product's availability and price at one branch. Without one of these,
 * the product is available at the branch at its own price.
 */
#[Fillable(['branch_id', 'product_id', 'is_available', 'price'])]
class BranchProduct extends Model
{
    use UsesTenantDatabase;

    protected $table = 'branch_product';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_available' => true,
    ];

    protected static function booted(): void
    {
        static::created(function (self $setting): void {
            if ($setting->price !== null) {
                ProductPriceChange::record($setting->product, $setting->branch_id, null, $setting->price);
            }
        });

        static::updated(function (self $setting): void {
            if ($setting->wasChanged('price')) {
                ProductPriceChange::record($setting->product, $setting->branch_id, $setting->getOriginal('price'), $setting->price);
            }
        });

        static::deleted(function (self $setting): void {
            if ($setting->price !== null) {
                ProductPriceChange::record($setting->product, $setting->branch_id, $setting->price, null);
            }
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
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
            'is_available' => 'boolean',
            'price' => 'decimal:2',
        ];
    }
}
