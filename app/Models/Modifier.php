<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Database\Factories\ModifierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One choice in a modifier group, such as "Less sugar" or "Large +2.00".
 */
#[Fillable(['modifier_group_id', 'name', 'price_delta', 'is_default', 'is_active', 'sort_order'])]
class Modifier extends Model
{
    /** @use HasFactory<ModifierFactory> */
    use HasFactory, HasUlids, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'price_delta' => '0.00',
        'is_default' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsTo<ModifierGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
