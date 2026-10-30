<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Database\Factories\ModifierGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable set of choices, such as "Sugar level" or "Size", attached to
 * any number of products.
 */
#[Fillable(['name', 'min_select', 'max_select', 'sort_order', 'is_active'])]
class ModifierGroup extends Model
{
    /** @use HasFactory<ModifierGroupFactory> */
    use HasFactory, HasUlids, SoftDeletes, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'min_select' => 0,
        'max_select' => 1,
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return HasMany<Modifier, $this>
     */
    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * The rule in words, as the POS shows it: "Choose 1", "Up to 3".
     */
    public function rule(): string
    {
        return match (true) {
            $this->min_select === $this->max_select => "Choose {$this->max_select}",
            $this->min_select === 0 => "Optional, up to {$this->max_select}",
            default => "Choose {$this->min_select} to {$this->max_select}",
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_select' => 'integer',
            'max_select' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
