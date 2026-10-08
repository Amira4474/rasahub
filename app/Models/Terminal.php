<?php

namespace App\Models;

use App\Tenancy\UsesTenantDatabase;
use Database\Factories\TerminalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical point-of-sale device. Terminals are first-class identities so
 * that records they create can later be attributed, de-duplicated and synced.
 *
 * A terminal is a POS terminal only. Kitchen display screens and QR ordering
 * are different kinds of device with their own tables, so that each can be
 * counted and limited separately.
 */
#[Fillable(['branch_id', 'name', 'code', 'is_active'])]
class Terminal extends Model
{
    /** @use HasFactory<TerminalFactory> */
    use HasFactory, HasUlids, SoftDeletes, UsesTenantDatabase;

    /**
     * Mirror the column default so a new model is active before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The ULID is a secondary public identifier; the primary key stays an integer.
     *
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
        return $this->belongsTo(Branch::class);
    }

    /**
     * Limit the query to terminals in branches the given user may work in.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->whereHas('branch', fn (Builder $branches) => $branches->visibleTo($user));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }
}
