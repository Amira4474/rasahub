<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A screen in a branch's kitchen or bar, showing the orders of chosen
 * stations. Kitchen staff choose it when they sign in.
 */
#[Fillable(['branch_id', 'name', 'station_ids', 'is_active'])]
class KitchenScreen extends Model
{
    use HasUlids, SoftDeletes, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * The stations it shows; empty for all of them.
     *
     * @return list<int>
     */
    public function stationIds(): array
    {
        return array_map('intval', $this->station_ids ?? []);
    }

    public function shows(?int $stationId): bool
    {
        return $this->stationIds() === [] || in_array($stationId, $this->stationIds(), true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'station_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
