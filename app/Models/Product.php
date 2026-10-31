<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Tenancy\UsesTenantDatabase;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Something on the menu: an item on its own, or a set meal made up of
 * other products. Its availability and price can differ per branch.
 */
#[Fillable(['category_id', 'station_id', 'type', 'name', 'sku', 'description', 'image_path', 'price', 'sort_order', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUlids, SoftDeletes, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'single',
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * Every change of price is kept, with who made it.
     */
    protected static function booted(): void
    {
        static::created(function (self $product): void {
            ProductPriceChange::record($product, null, null, $product->price);
        });

        static::updated(function (self $product): void {
            if ($product->wasChanged('price')) {
                ProductPriceChange::record($product, null, $product->getOriginal('price'), $product->price);
            }
        });
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Station, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class)->withTrashed();
    }

    /**
     * Where the product is made: its own station, or its category's (or
     * the parent category's).
     */
    public function effectiveStation(): ?Station
    {
        return $this->station ?? $this->category?->station ?? $this->category?->parent?->station;
    }

    /**
     * @return BelongsToMany<ModifierGroup, $this>
     */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class)->orderBy('sort_order');
    }

    /**
     * The choices that make up a set meal.
     *
     * @return HasMany<SetGroup, $this>
     */
    public function setGroups(): HasMany
    {
        return $this->hasMany(SetGroup::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<BranchProduct, $this>
     */
    public function branchSettings(): HasMany
    {
        return $this->hasMany(BranchProduct::class);
    }

    /**
     * @return HasMany<ProductPriceChange, $this>
     */
    public function priceChanges(): HasMany
    {
        return $this->hasMany(ProductPriceChange::class)->latest('changed_at')->latest('id');
    }

    public function isSet(): bool
    {
        return $this->type === ProductType::Set;
    }

    /**
     * What the product sells for at a branch: the branch's own price if it
     * has one, otherwise the product's.
     */
    public function priceAt(Branch|int $branch): string
    {
        return $this->settingsFor($branch)?->price ?? $this->price;
    }

    public function isAvailableAt(Branch|int $branch): bool
    {
        return $this->is_active && ($this->settingsFor($branch)?->is_available ?? true);
    }

    private function settingsFor(Branch|int $branch): ?BranchProduct
    {
        $branchId = $branch instanceof Branch ? $branch->getKey() : $branch;

        return $this->branchSettings->firstWhere('branch_id', $branchId);
    }

    /**
     * Products that can be chosen as part of a set meal: single items only,
     * since a set cannot contain another set.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSingleItems(Builder $query): Builder
    {
        return $query->where('type', ProductType::Single);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
