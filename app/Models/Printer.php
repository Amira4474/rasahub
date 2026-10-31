<?php

namespace App\Models;

use App\Enums\PrinterRole;
use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A network thermal printer at a branch, at a fixed address. RasaHub queues
 * print jobs for it; the branch's print gateway sends them to it.
 */
#[Fillable(['branch_id', 'name', 'role', 'host', 'port', 'paper_width', 'is_active'])]
class Printer extends Model
{
    use HasUlids, SoftDeletes, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'port' => 9100,
        'paper_width' => 80,
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
     * @return BelongsToMany<Station, $this>
     */
    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(Station::class);
    }

    /**
     * @return HasMany<PrintJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PrinterRole::class,
            'port' => 'integer',
            'paper_width' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
