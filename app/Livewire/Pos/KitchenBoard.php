<?php

namespace App\Livewire\Pos;

use App\Enums\KitchenStatus;
use App\Enums\OrderStatus;
use App\Models\KitchenScreen;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Station;
use App\Models\User;
use App\Sales\SaleException;
use App\Sales\Till;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A kitchen screen: the orders for its stations, as cards. Lines are
 * marked ready one at a time or all at once; ready orders are bumped when
 * served, and can be recalled.
 */
class KitchenBoard extends Component
{
    #[Locked]
    public int $screenId;

    public string $message = '';

    /**
     * The orders on the screen at the last refresh, to tell when a new one
     * arrives.
     *
     * @var list<int>|null
     */
    public ?array $knownOrderIds = null;

    public function mount(KitchenScreen $screen): void
    {
        abort_unless(auth()->user()->canAccessBranch($screen->branch), 403);

        $this->screenId = $screen->id;
    }

    #[Computed]
    public function screen(): KitchenScreen
    {
        return KitchenScreen::with('branch')->findOrFail($this->screenId);
    }

    /**
     * @return list<int>
     */
    private function stationIds(): array
    {
        return $this->screen->stationIds();
    }

    /**
     * The lines this screen shows: of its stations, sent, being made or
     * ready. A set meal's own line is left out; its parts show.
     *
     * @param  list<KitchenStatus>  $statuses
     */
    private function linesQuery(array $statuses): \Closure
    {
        return fn (Builder|HasMany $query) => $query
            ->whereIn('kitchen_status', $statuses)
            ->whereDoesntHave('parts')
            ->when($this->stationIds() !== [], fn ($q) => $q->whereIn('station_id', $this->stationIds()));
    }

    /**
     * Orders with lines still to make or ready to serve here, oldest first,
     * with only this screen's lines loaded.
     *
     * @return Collection<int, Order>
     */
    #[Computed]
    public function orders(): Collection
    {
        $statuses = [KitchenStatus::Sent, KitchenStatus::Preparing, KitchenStatus::Ready];

        return Order::where('branch_id', $this->screen->branch_id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereHas('items', $this->linesQuery($statuses))
            ->with(['diningTable', 'items' => fn ($q) => $this->linesQuery([...$statuses, KitchenStatus::Voided])($q->with('modifiers')->orderBy('id'))])
            ->oldest('id')
            ->get();
    }

    /**
     * Orders served here in the last half hour, newest first, to recall a
     * bump made by mistake.
     *
     * @return Collection<int, Order>
     */
    #[Computed]
    public function recent(): Collection
    {
        return Order::where('branch_id', $this->screen->branch_id)
            ->whereHas('items', fn ($q) => $this->linesQuery([KitchenStatus::Served])($q)->where('updated_at', '>=', now()->subMinutes(30)))
            ->whereDoesntHave('items', $this->linesQuery([KitchenStatus::Sent, KitchenStatus::Preparing, KitchenStatus::Ready]))
            ->latest('updated_at')
            ->limit(6)
            ->get();
    }

    public function stationNames(): string
    {
        return $this->stationIds() === []
            ? 'All stations'
            : Station::whereKey($this->stationIds())->orderBy('sort_order')->pluck('name')->implode(', ');
    }

    /**
     * Tap a line: ready, or back to being made.
     */
    public function toggleLine(int $itemId): void
    {
        $item = OrderItem::whereHas('order', fn ($q) => $q->where('branch_id', $this->screen->branch_id))->findOrFail($itemId);

        $this->attempt(fn () => app(Till::class)->setItemReady($item, $this->user(), $item->kitchen_status !== KitchenStatus::Ready));
    }

    public function start(int $orderId): void
    {
        $this->attempt(fn () => app(Till::class)->advanceStations($this->order($orderId), $this->user(), KitchenStatus::Sent, $this->stationIds()));
    }

    public function allReady(int $orderId): void
    {
        $this->attempt(fn () => app(Till::class)->advanceStations($this->order($orderId), $this->user(), KitchenStatus::Preparing, $this->stationIds()));
    }

    public function served(int $orderId): void
    {
        $this->attempt(fn () => app(Till::class)->advanceStations($this->order($orderId), $this->user(), KitchenStatus::Ready, $this->stationIds()));
    }

    public function recall(int $orderId): void
    {
        $this->attempt(fn () => app(Till::class)->advanceStations($this->order($orderId), $this->user(), KitchenStatus::Served, $this->stationIds()));
    }

    public function render()
    {
        $ids = $this->orders->pluck('id')->all();

        if ($this->knownOrderIds !== null && array_diff($ids, $this->knownOrderIds) !== []) {
            $this->dispatch('kitchen-new-order');
        }

        $this->knownOrderIds = $ids;

        return view('livewire.pos.kitchen-board');
    }

    private function order(int $id): Order
    {
        return Order::where('branch_id', $this->screen->branch_id)->findOrFail($id);
    }

    private function user(): User
    {
        return auth()->user();
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
            $this->message = '';
        } catch (SaleException $exception) {
            $this->message = $exception->getMessage();
        } catch (AuthorizationException) {
            $this->message = 'Your role does not allow that.';
        }

        unset($this->orders, $this->recent);
    }
}
