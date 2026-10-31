<?php

namespace App\Models;

use App\Enums\TableShape;
use App\Tenancy\UsesTenantDatabase;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A table in a floor area. Its status (available, occupied, bill
 * requested) will come from its orders once there are orders.
 */
#[Fillable(['floor_area_id', 'qr_token', 'name', 'seats', 'shape', 'pos_x', 'pos_y', 'sort_order', 'is_active'])]
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory, HasUlids, SoftDeletes, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'seats' => 4,
        'shape' => 'square',
        'pos_x' => 0,
        'pos_y' => 0,
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
     * The table's QR code link, made the first time it is needed.
     */
    public function qrUrl(string $acronym): string
    {
        if ($this->qr_token === null) {
            $this->newQrCode();
        }

        return route('qr.order', ['business' => strtolower($acronym), 'token' => $this->qr_token]);
    }

    /**
     * A new QR code: the printed one stops working.
     */
    public function newQrCode(): void
    {
        $this->forceFill(['qr_token' => Str::random(40)])->save();
    }

    /**
     * @return BelongsTo<FloorArea, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(FloorArea::class, 'floor_area_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'shape' => TableShape::class,
            'pos_x' => 'integer',
            'pos_y' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
