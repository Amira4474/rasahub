<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Where food or drink is made, such as Kitchen or Bar. Orders are split by
 * station on the kitchen screens and on printed tickets.
 */
#[Fillable(['name', 'sort_order', 'is_active'])]
class Station extends Model
{
    use HasUlids, SoftDeletes, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
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
     * @return BelongsToMany<Printer, $this>
     */
    public function printers(): BelongsToMany
    {
        return $this->belongsToMany(Printer::class);
    }

    /**
     * The printer that prints this station's tickets at a branch.
     */
    public function printerAt(int $branchId): ?Printer
    {
        return $this->printers()->where('printers.branch_id', $branchId)->where('printers.is_active', true)->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
