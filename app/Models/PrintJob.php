<?php

namespace App\Models;

use App\Enums\PrintJobStatus;
use App\Tenancy\UsesTenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something to print on a printer: a receipt, a kitchen ticket. The
 * document describes the lines to print without depending on a printer
 * brand; the print gateway turns it into the printer's own commands.
 */
#[Fillable(['branch_id', 'printer_id', 'type', 'reference', 'document', 'status', 'attempts', 'error', 'claimed_at', 'printed_at'])]
class PrintJob extends Model
{
    use HasUlids, UsesTenantDatabase;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'attempts' => 0,
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsTo<Printer, $this>
     */
    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class)->withTrashed();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'receipt' => 'Receipt',
            'kitchen_ticket' => 'Kitchen ticket',
            'void_ticket' => 'Void ticket',
            'refund_slip' => 'Refund slip',
            'shift_report' => 'Shift report',
            'test' => 'Test print',
            default => $this->type,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PrintJobStatus::class,
            'document' => 'array',
            'attempts' => 'integer',
            'claimed_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }
}
