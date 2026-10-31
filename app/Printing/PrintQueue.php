<?php

namespace App\Printing;

use App\Enums\PrintJobStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Refund;
use App\Models\Shift;
use App\Models\Station;
use App\Models\Terminal;
use Illuminate\Support\Collection;

/**
 * Queues print jobs for the branch's print gateway to collect. When there
 * is no printer for something, nothing is queued: the browser prints
 * receipts, and the kitchen works from its screens.
 */
class PrintQueue
{
    public function queue(Printer $printer, string $type, Document $document, ?string $reference = null): PrintJob
    {
        return PrintJob::create([
            'branch_id' => $printer->branch_id,
            'printer_id' => $printer->id,
            'type' => $type,
            'reference' => $reference,
            'document' => $document->toArray(),
        ]);
    }

    /**
     * One ticket per station for the lines just sent, at the printer that
     * serves the station at the order's branch.
     *
     * @param  Collection<int, OrderItem>  $lines
     */
    public function kitchenTickets(Order $order, Collection $lines): int
    {
        $queued = 0;

        foreach ($lines->groupBy('station_id') as $stationId => $stationLines) {
            $printer = $stationId ? Station::find($stationId)?->printerAt($order->branch_id) : null;

            if ($printer === null) {
                continue;
            }

            $this->queue($printer, 'kitchen_ticket', Tickets::kitchen($order, $stationLines->first()->station_name ?? 'Kitchen', $stationLines), $order->number_label);
            $queued++;
        }

        return $queued;
    }

    public function voidTicket(Order $order, OrderItem $line): void
    {
        $printer = $line->station_id ? Station::withTrashed()->find($line->station_id)?->printerAt($order->branch_id) : null;

        if ($printer !== null) {
            $this->queue($printer, 'void_ticket', Tickets::void($order, $line->station_name ?? 'Kitchen', $line), $order->number_label);
        }
    }

    /**
     * Print the receipt at the terminal's receipt printer. False when it has
     * none, so that the browser prints it instead.
     */
    public function receipt(Order $order, ?Terminal $terminal): bool
    {
        $printer = $this->receiptPrinter($terminal);

        if ($printer === null) {
            return false;
        }

        $this->queue($printer, 'receipt', Tickets::receipt($order), $order->receiptLabel());

        return true;
    }

    public function refundSlip(Refund $refund, ?Terminal $terminal): bool
    {
        $printer = $this->receiptPrinter($terminal);

        if ($printer === null) {
            return false;
        }

        $this->queue($printer, 'refund_slip', Tickets::refund($refund), $refund->number_label);

        return true;
    }

    public function shiftReport(Shift $shift): bool
    {
        $printer = $this->receiptPrinter($shift->terminal);

        if ($printer === null) {
            return false;
        }

        $this->queue($printer, 'shift_report', Tickets::shift($shift), ($shift->isOpen() ? 'X ' : 'Z ').$shift->terminal->name);

        return true;
    }

    /**
     * Print it again: a new job with the same document.
     */
    public function reprint(PrintJob $job): PrintJob
    {
        return PrintJob::create([
            ...$job->only(['branch_id', 'printer_id', 'type', 'reference', 'document']),
            'status' => PrintJobStatus::Queued,
        ]);
    }

    private function receiptPrinter(?Terminal $terminal): ?Printer
    {
        $printer = $terminal?->receiptPrinter;

        return $printer?->is_active ? $printer : null;
    }
}
