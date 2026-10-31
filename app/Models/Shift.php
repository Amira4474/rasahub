<?php

namespace App\Models;

use App\Enums\PaymentMethodType;
use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A terminal's cash drawer session: opened with a float, closed with a cash
 * count. Every payment belongs to one.
 */
#[Fillable(['branch_id', 'terminal_id', 'open_on_terminal_id', 'opened_by', 'opened_at', 'opening_float', 'closed_by', 'closed_at', 'expected_cash', 'counted_cash', 'variance', 'closing_note'])]
class Shift extends Model
{
    use HasUlids, UsesTenantDatabase;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public static function openOn(Terminal|int $terminal): ?self
    {
        return self::where('open_on_terminal_id', $terminal instanceof Terminal ? $terminal->getKey() : $terminal)->first();
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class)->withTrashed();
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * The cash that should be in the drawer: the float plus cash taken.
     */
    public function expectedCash(): string
    {
        $cash = (string) $this->payments()->where('method_type', PaymentMethodType::Cash)->sum('amount');

        return bcadd($this->opening_float, $cash, 2);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'variance' => 'decimal:2',
        ];
    }
}
