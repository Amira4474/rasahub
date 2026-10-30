<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change of a product's own price or of its price at a branch. Never
 * edited once written.
 */
#[Fillable(['product_id', 'branch_id', 'old_price', 'new_price', 'changed_by', 'changed_at'])]
class ProductPriceChange extends Model
{
    use UsesTenantDatabase;

    public $timestamps = false;

    public static function record(Product $product, ?int $branchId, ?string $old, ?string $new): void
    {
        $product->priceChanges()->create([
            'branch_id' => $branchId,
            'old_price' => $old,
            'new_price' => $new,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * Users are in the central database, so they are looked up there.
     */
    public function changedBy(): ?User
    {
        return $this->changed_by === null ? null : User::acrossBusinesses()->find($this->changed_by);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }
}
