<?php

namespace App\Livewire\Pos;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TableRequest;
use App\Models\Terminal;
use App\Sales\SaleException;
use App\Sales\Till;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The QR orders waiting for staff at the branch, and customers calling for
 * staff or the bill. A badge in the POS header that checks every few
 * seconds and chimes when something new arrives.
 */
class QrInbox extends Component
{
    #[Locked]
    public int $terminalId;

    public bool $open = false;

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public string $message = '';

    /** @var list<string>|null */
    public ?array $known = null;

    public function mount(Terminal $terminal): void
    {
        $this->terminalId = $terminal->id;
    }

    public static function included(): bool
    {
        return app(TenantContext::class)->business()?->subscription?->plan->includes('qr_ordering') ?? false;
    }

    #[Computed]
    public function terminal(): Terminal
    {
        return Terminal::findOrFail($this->terminalId);
    }

    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function waiting(): Collection
    {
        return Order::where('branch_id', $this->terminal->branch_id)
            ->where('source', 'qr')
            ->where('status', OrderStatus::Pending)
            ->with(['diningTable', 'lines.modifiers', 'lines.parts', 'customer'])
            ->oldest('id')
            ->get();
    }

    /**
     * @return Collection<int, TableRequest>
     */
    #[Computed]
    public function requests(): Collection
    {
        return TableRequest::where('branch_id', $this->terminal->branch_id)
            ->whereNull('resolved_at')
            ->with('table')
            ->oldest('id')
            ->get();
    }

    public function confirm(int $orderId): void
    {
        $this->attempt(function () use ($orderId): void {
            $order = app(Till::class)->confirmQrOrder($this->order($orderId), auth()->user(), $this->terminal);
            $this->message = "Sent to the kitchen on {$order->number_label}.";
            $this->dispatch('qr-confirmed');
        });
    }

    public function reject(): void
    {
        $this->attempt(function (): void {
            app(Till::class)->rejectQrOrder($this->order($this->rejectingId), auth()->user(), $this->rejectReason);
            $this->reset('rejectingId', 'rejectReason');
            $this->message = 'The customer has been told.';
        });
    }

    public function resolve(int $requestId): void
    {
        TableRequest::where('branch_id', $this->terminal->branch_id)->whereKey($requestId)->update(['resolved_at' => now(), 'resolved_by' => auth()->id()]);
        unset($this->requests);
        $this->dispatch('qr-confirmed');
    }

    public function render()
    {
        $ids = [...$this->waiting->map(fn ($o) => 'o'.$o->id), ...$this->requests->map(fn ($r) => 'r'.$r->id)];

        if ($this->known !== null && array_diff($ids, $this->known) !== []) {
            $this->dispatch('qr-arrived');
        }

        $this->known = $ids;

        return view('livewire.pos.qr-inbox');
    }

    private function order(?int $id): Order
    {
        return Order::where('branch_id', $this->terminal->branch_id)->findOrFail($id);
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
        } catch (SaleException $exception) {
            $this->message = $exception->getMessage();
        } catch (AuthorizationException) {
            $this->message = 'Your role does not allow that.';
        }

        unset($this->waiting, $this->requests);
    }
}
