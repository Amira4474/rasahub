<?php

namespace App\Livewire\Pos;

use App\Enums\DiscountType;
use App\Enums\KitchenStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethodType;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\DiscountPreset;
use App\Models\FloorArea;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Station;
use App\Models\TableRequest;
use App\Models\Terminal;
use App\Models\User;
use App\Printing\PrintQueue;
use App\Sales\ApprovalRequired;
use App\Sales\SaleException;
use App\Sales\Till;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The POS screen of one terminal: tables, the menu, the order being taken,
 * payment and the shift. All the selling itself is done by App\Sales\Till.
 */
class Register extends Component
{
    #[Locked]
    public int $terminalId;

    /** sell, transactions or kitchen: the sections in the header menu */
    public string $screen = 'sell';

    /** tables, menu or orders, within Sell */
    public string $tab = 'menu';

    // Kitchen display: one station, or all of them.
    public ?int $kitchenStationId = null;

    // Transactions.
    public string $transactionsDate = '';

    public string $transactionsSearch = '';

    public ?int $orderId = null;

    public ?int $categoryId = null;

    public string $message = '';

    public string $messageTone = 'info';

    // Starting a dine-in order at a table.
    public ?int $seatingTableId = null;

    public ?int $guests = null;

    // The product being chosen, with its modifiers and set meal parts.
    public ?int $pickingProductId = null;

    /** @var array<int, list<int>> */
    public array $pickedModifiers = [];

    /** @var array<int, list<int>> */
    public array $pickedParts = [];

    public int $pickQuantity = 1;

    public string $pickNote = '';

    // Payment: the pop-up behind the Pay button, and the method chosen in it.
    public bool $paying = false;

    public ?int $payingWithId = null;

    public string $payAmount = '';

    public string $payReference = '';

    public string $paymentKey = '';

    // Customer.
    public bool $choosingCustomer = false;

    public string $customerPhone = '';

    public string $customerName = '';

    // Moving or merging.
    public bool $moving = false;

    // Corrections: discounts, voids, cancelling and refunds. A dialog that
    // needs a supervisor asks for their PIN in place.
    /** null, 'order', or the id of the line being discounted */
    public string|int|null $discounting = null;

    public string $discountType = 'percent';

    public string $discountValue = '';

    public string $discountReason = '';

    public ?int $voidingItemId = null;

    public string $voidReason = '';

    public bool $cancelling = false;

    public string $cancelReason = '';

    public bool $refunding = false;

    /** items or amount */
    public string $refundMode = 'items';

    /** @var array<int, int> */
    public array $refundQuantities = [];

    public string $refundAmount = '';

    public string $refundReason = '';

    public bool $needsApproval = false;

    public string $approvalPin = '';

    // Shift: the pop-up behind the shift button in the header, and its
    // closing step.
    public bool $shiftPanel = false;

    public string $openingFloat = '';

    public bool $closingShift = false;

    public string $countedCash = '';

    public string $closingNote = '';

    public function mount(Terminal $terminal): void
    {
        abort_unless(auth()->user()->canAccessBranch($terminal->branch), 403);

        $this->terminalId = $terminal->id;
        $this->tab = $this->tables()->isEmpty() ? 'menu' : 'tables';
        $this->transactionsDate = $terminal->branch->businessDateFor(now())->toDateString();

        // Kitchen staff take no orders: they start at the kitchen display.
        if (! $this->allowed(Permission::TakeOrders) && $this->allowed(Permission::UseKitchenDisplay)) {
            $this->screen = 'kitchen';
        }
    }

    #[Computed]
    public function terminal(): Terminal
    {
        return Terminal::with('branch')->findOrFail($this->terminalId);
    }

    #[Computed]
    public function branch(): Branch
    {
        return $this->terminal->branch;
    }

    #[Computed]
    public function shift(): ?Shift
    {
        return Shift::openOn($this->terminalId);
    }

    #[Computed]
    public function order(): ?Order
    {
        if ($this->orderId === null) {
            return null;
        }

        $order = Order::with(['lines.parts', 'lines.modifiers', 'lines.discounts', 'discounts', 'payments', 'refunds', 'diningTable', 'customer', 'branch'])
            ->where('branch_id', $this->branch->id)
            ->find($this->orderId);

        return $order;
    }

    /**
     * Active categories that have something on sale at this branch.
     *
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        $productIds = $this->menu()->pluck('category_id')->unique();

        return Category::where('is_active', true)->whereIn('id', $productIds)->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Everything on sale at this branch now.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function menu(): Collection
    {
        return Product::with(['branchSettings', 'modifierGroups', 'setGroups', 'category'])
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product): bool => $product->isAvailableAt($this->branch->id))
            ->values();
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        $categoryId = $this->categoryId ?? $this->categories->first()?->id;

        return $this->menu->where('category_id', $categoryId)->values();
    }

    #[Computed]
    public function picking(): ?Product
    {
        return $this->pickingProductId === null
            ? null
            : Product::with(['modifierGroups' => fn ($q) => $q->where('is_active', true), 'modifierGroups.modifiers' => fn ($q) => $q->where('is_active', true), 'setGroups.items.product', 'branchSettings'])->find($this->pickingProductId);
    }

    /**
     * The branch's floor areas with their active tables.
     *
     * @return Collection<int, FloorArea>
     */
    #[Computed]
    public function tables(): Collection
    {
        return FloorArea::where('branch_id', $this->branch->id)
            ->where('is_active', true)
            ->with(['tables' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Open orders at this branch, by table where they have one.
     *
     * @return Collection<int, Order>
     */
    #[Computed]
    public function openOrders(): Collection
    {
        // QR orders waiting for staff are in the QR inbox, not here.
        return Order::open()
            ->where('branch_id', $this->branch->id)
            ->where('status', '!=', OrderStatus::Pending)
            ->whereHas('items')
            ->with('diningTable')
            ->latest('id')
            ->get();
    }

    /**
     * The branch's orders on the chosen business day, newest first.
     *
     * @return Collection<int, Order>
     */
    #[Computed]
    public function transactions(): Collection
    {
        $search = trim($this->transactionsSearch);

        return Order::where('branch_id', $this->branch->id)
            ->whereDate('business_date', $this->transactionsDate ?: today())
            ->whereHas('items')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number_label', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($q) => $q->where('phone', 'like', '%'.preg_replace('/\D/', '', $search).'%')->orWhere('name', 'like', "%{$search}%"))))
            ->with(['diningTable', 'customer', 'payments'])
            ->latest('id')
            ->get();
    }

    /**
     * Orders with lines in the kitchen, by stage: sent, preparing and ready.
     * Paid takeaways stay here until they are served.
     *
     * @return array<string, Collection<int, Order>>
     */
    #[Computed]
    public function kitchenOrders(): array
    {
        $orders = Order::where('branch_id', $this->branch->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereHas('items', fn ($q) => $q
                ->whereIn('kitchen_status', [KitchenStatus::Sent, KitchenStatus::Preparing, KitchenStatus::Ready])
                ->when($this->kitchenStationId, fn ($q, int $station) => $q->where('station_id', $station)))
            ->with(['diningTable', 'lines.parts', 'lines.modifiers'])
            ->oldest('id')
            ->get();

        $stage = function (Order $order): string {
            $statuses = $order->lines->flatMap(fn (OrderItem $line) => [$line, ...$line->parts])->pluck('kitchen_status');

            return match (true) {
                $statuses->contains(KitchenStatus::Sent) => KitchenStatus::Sent->value,
                $statuses->contains(KitchenStatus::Preparing) => KitchenStatus::Preparing->value,
                default => KitchenStatus::Ready->value,
            };
        };

        return [
            KitchenStatus::Sent->value => $orders->filter(fn (Order $order) => $stage($order) === KitchenStatus::Sent->value)->values(),
            KitchenStatus::Preparing->value => $orders->filter(fn (Order $order) => $stage($order) === KitchenStatus::Preparing->value)->values(),
            KitchenStatus::Ready->value => $orders->filter(fn (Order $order) => $stage($order) === KitchenStatus::Ready->value)->values(),
        ];
    }

    /**
     * What the shift pop-up shows: who opened the shift and when, the cash
     * that should be in the drawer, and what was taken by each method.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function shiftSummary(): array
    {
        $shift = $this->shift;
        $open = Order::open()->where('branch_id', $this->branch->id)->whereHas('items')->get(['total', 'paid']);

        $summary = [
            'open_orders' => $open->count(),
            'open_value' => Money::sum($open->map(fn (Order $order): string => $order->balance())),
        ];

        if ($shift === null) {
            return $summary;
        }

        $payments = $shift->payments()->get(['method_name', 'method_type', 'amount', 'order_id']);
        $cash = Money::sum($payments->where('method_type', PaymentMethodType::Cash)->pluck('amount'));

        return [
            ...$summary,
            'opened_by' => User::acrossBusinesses()->find($shift->opened_by)?->name ?? 'Removed user',
            'opened_at' => $shift->opened_at->timezone($this->branch->effectiveTimezone())->format('H:i'),
            'duration' => $shift->opened_at->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE),
            'cash_taken' => $cash,
            'expected_cash' => bcadd($shift->opening_float, $cash, 2),
            'total_taken' => Money::sum($payments->pluck('amount')),
            // Orders settled by payments taken on this shift.
            'orders_paid' => Order::whereIn('id', $payments->pluck('order_id')->unique())->where('status', OrderStatus::Completed)->count(),
            'methods' => $payments->groupBy('method_name')->map(fn ($group, $name): array => [
                'name' => $name,
                'count' => $group->count(),
                'total' => Money::sum($group->pluck('amount')),
            ])->values()->all(),
        ];
    }

    /**
     * Calls from tables for staff or the bill, not yet dealt with.
     *
     * @return Collection<int, TableRequest>
     */
    #[Computed]
    public function tableRequests(): Collection
    {
        return TableRequest::where('branch_id', $this->branch->id)->whereNull('resolved_at')->get();
    }

    /**
     * A QR order was confirmed or a call dealt with in the header: the
     * tables and open orders are worked out again.
     */
    #[On('qr-confirmed')]
    public function refreshFromInbox(): void
    {
        unset($this->openOrders, $this->tableRequests, $this->order);
    }

    /**
     * @return Collection<int, DiscountPreset>
     */
    #[Computed]
    public function presets(): Collection
    {
        return DiscountPreset::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Station>
     */
    #[Computed]
    public function stations(): Collection
    {
        return Station::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function kitchenIncluded(): bool
    {
        return app(TenantContext::class)->business()?->subscription?->plan->includes('kitchen_display') ?? false;
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    #[Computed]
    public function methods(): Collection
    {
        return PaymentMethod::available();
    }

    #[Computed]
    public function payingWith(): ?PaymentMethod
    {
        return $this->payingWithId === null ? null : $this->methods->firstWhere('id', $this->payingWithId);
    }

    public function qrImageUrl(PaymentMethod $method): ?string
    {
        return $method->qr_image_path ? $this->imageUrl($method->qr_image_path) : null;
    }

    public function imageUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    public function money(?string $amount): string
    {
        return app(TenantContext::class)->business()->currency.' '.number_format((float) $amount, 2);
    }

    public function allowed(Permission $permission): bool
    {
        return $this->user()->can($permission);
    }

    // Sections --------------------------------------------------------------

    public function showScreen(string $screen): void
    {
        if (! in_array($screen, ['sell', 'transactions', 'kitchen'], true)) {
            return;
        }

        $this->screen = $screen;
        $this->reset('pickingProductId', 'paying', 'payingWithId', 'choosingCustomer', 'moving', 'seatingTableId');
    }

    /**
     * Open an earlier order from Transactions: its lines, payments and
     * receipt show in the order panel.
     */
    public function showTransaction(int $orderId): void
    {
        $this->showOrder($orderId);
        $this->screen = 'transactions';
    }

    public function advanceKitchen(int $orderId, string $from): void
    {
        $order = Order::where('branch_id', $this->branch->id)->findOrFail($orderId);
        $stage = KitchenStatus::from($from);

        $this->attempt(function () use ($order, $stage): void {
            app(Till::class)->advanceKitchen($order, $this->user(), $stage);

            $this->say(match ($stage) {
                KitchenStatus::Sent => "Order {$order->number_label} is being prepared.",
                KitchenStatus::Preparing => "Order {$order->number_label} is ready.",
                default => "Order {$order->number_label} is served.",
            }, 'success');
        });

        unset($this->kitchenOrders);
    }

    // Shift ---------------------------------------------------------------

    public function openShift(): void
    {
        $float = Money::parse($this->openingFloat === '' ? '0' : $this->openingFloat);

        if ($float === null) {
            $this->say('Enter the float as an amount, such as 100 or 100.00.', 'error');

            return;
        }

        $this->attempt(function () use ($float): void {
            app(Till::class)->openShift($this->terminal, $this->user(), $float);
            $this->openingFloat = '';
            $this->shiftPanel = false;
            $this->say('Shift opened.');
        });
    }

    public function closeShift(): void
    {
        $counted = Money::parse($this->countedCash);

        if ($counted === null || $this->shift === null) {
            $this->say('Enter the cash counted in the drawer.', 'error');

            return;
        }

        $this->attempt(function () use ($counted): void {
            $shift = app(Till::class)->closeShift($this->shift, $this->user(), $counted, $this->closingNote ?: null);

            $this->closingShift = false;
            $this->shiftPanel = false;
            $this->countedCash = $this->closingNote = '';

            $variance = $shift->variance;
            $this->say(bccomp($variance, '0', 2) === 0
                ? 'Shift closed. The drawer is exact.'
                : 'Shift closed. The drawer is '.(bccomp($variance, '0', 2) > 0 ? 'over' : 'short').' by '.$this->money(ltrim($variance, '-')).'.', bccomp($variance, '0', 2) === 0 ? 'success' : 'warning');
        });
    }

    // Orders --------------------------------------------------------------

    public function startTakeaway(): void
    {
        $this->attempt(function (): void {
            $order = app(Till::class)->startOrder($this->terminal, $this->user(), OrderType::Takeaway);
            $this->showOrder($order->id);
        });
    }

    /**
     * A dine-in order without a table, for branches with no floor plan.
     */
    public function startDineIn(): void
    {
        $this->attempt(function (): void {
            $order = app(Till::class)->startOrder($this->terminal, $this->user(), OrderType::DineIn);
            $this->showOrder($order->id);
        });
    }

    /**
     * Tapping a table: its open order, or a new order there.
     */
    public function selectTable(int $tableId): void
    {
        $table = $this->table($tableId);

        if ($this->moving && $this->order) {
            $this->moveTo($table);

            return;
        }

        if (($open = app(Till::class)->openOrderAt($table)) !== null) {
            $this->showOrder($open->id);

            return;
        }

        $this->seatingTableId = $table->id;
        $this->guests = $table->seats;
    }

    public function seat(): void
    {
        $this->attempt(function (): void {
            $order = app(Till::class)->startOrder($this->terminal, $this->user(), OrderType::DineIn, $this->table($this->seatingTableId), $this->guests ?: null);
            $this->seatingTableId = null;
            $this->showOrder($order->id);
        });
    }

    public function showOrder(int $orderId): void
    {
        $this->reset('pickingProductId', 'paying', 'payingWithId', 'choosingCustomer', 'moving', 'seatingTableId');
        $this->orderId = $orderId;
        $this->screen = 'sell';
        $this->tab = 'menu';
        unset($this->order);
    }

    public function closeOrder(): void
    {
        $this->reset('orderId', 'pickingProductId', 'paying', 'payingWithId', 'choosingCustomer', 'moving');
        $this->tab = $this->tables()->isEmpty() ? 'menu' : 'tables';
    }

    public function pick(int $productId): void
    {
        if ($this->order === null) {
            $this->say('Start an order first: choose a table, or Takeaway.', 'error');

            return;
        }

        $product = $this->menu->firstWhere('id', $productId);

        if ($product === null) {
            return;
        }

        if ($product->modifierGroups->isEmpty() && ! $product->isSet()) {
            $this->attempt(fn () => app(Till::class)->addItem($this->order, $this->user(), $product));

            return;
        }

        $this->pickingProductId = $productId;
        $this->pickQuantity = 1;
        $this->pickNote = '';
        $this->pickedModifiers = [];
        $this->pickedParts = [];

        // Start with the defaults chosen.
        foreach ($this->picking->modifierGroups as $group) {
            $this->pickedModifiers[$group->id] = $group->modifiers->where('is_default', true)->pluck('id')->all();
        }

        foreach ($this->picking->setGroups as $group) {
            $this->pickedParts[$group->id] = $group->items->where('is_default', true)->pluck('product_id')->all();
        }
    }

    /**
     * Choose or unchoose an option. Where only one may be chosen, choosing
     * another replaces it.
     */
    public function toggleModifier(int $groupId, int $modifierId): void
    {
        $group = $this->picking?->modifierGroups->firstWhere('id', $groupId);

        if ($group === null) {
            return;
        }

        $this->pickedModifiers[$groupId] = $this->toggle($this->pickedModifiers[$groupId] ?? [], $modifierId, $group->max_select);
    }

    public function togglePart(int $groupId, int $productId): void
    {
        $group = $this->picking?->setGroups->firstWhere('id', $groupId);

        if ($group === null) {
            return;
        }

        $this->pickedParts[$groupId] = $this->toggle($this->pickedParts[$groupId] ?? [], $productId, $group->max_select);
    }

    public function confirmPick(): void
    {
        $this->attempt(function (): void {
            app(Till::class)->addItem(
                $this->order,
                $this->user(),
                $this->picking,
                max(1, $this->pickQuantity),
                array_merge(...array_values($this->pickedModifiers ?: [[]])),
                $this->pickedParts,
                $this->pickNote ?: null,
            );

            $this->pickingProductId = null;
        });
    }

    public function changeQuantity(int $itemId, int $by): void
    {
        $item = $this->item($itemId);

        $this->attempt(fn () => app(Till::class)->setQuantity($item, $this->user(), $item->quantity + $by));
    }

    public function removeItem(int $itemId): void
    {
        $this->attempt(fn () => app(Till::class)->removeItem($this->item($itemId), $this->user()));
    }

    public function sendToKitchen(): void
    {
        $this->attempt(function (): void {
            $sent = app(Till::class)->sendToKitchen($this->order, $this->user());
            $this->say($sent === 0
                ? "Everything on order {$this->order->number_label} has already been sent."
                : "Order {$this->order->number_label}: {$sent} ".Str::plural('line', $sent).' sent to the kitchen.', $sent === 0 ? 'info' : 'success');
        });
    }

    public function attachCustomer(): void
    {
        $this->attempt(function (): void {
            $customer = app(Till::class)->attachCustomer($this->order, $this->user(), $this->customerPhone, $this->customerName ?: null);
            $this->reset('choosingCustomer', 'customerPhone', 'customerName');
            $this->say("{$customer->displayName()} added to the order.", 'success');
        });
    }

    public function detachCustomer(): void
    {
        $this->attempt(fn () => app(Till::class)->detachCustomer($this->order, $this->user()));
    }

    public function startMoving(): void
    {
        $this->moving = true;
        $this->tab = 'tables';
        $this->say('Choose the table to move to. A table with an order merges the two.');
    }

    private function moveTo(DiningTable $table): void
    {
        $this->attempt(function () use ($table): void {
            $till = app(Till::class);
            $there = $till->openOrderAt($table);

            if ($there !== null && ! $there->is($this->order)) {
                // Guests joining a table: this order joins that one.
                $till->merge($there, $this->order, $this->user());
                $this->say("Merged into {$there->number_label} at table {$table->name}.", 'success');
                $this->showOrder($there->id);

                return;
            }

            $till->moveToTable($this->order, $this->user(), $table);
            $this->say("Moved to table {$table->name}.", 'success');
            $this->showOrder($this->order->id);
        });
    }

    // Corrections -----------------------------------------------------------

    public function openDiscount(string|int $target): void
    {
        $this->resetCorrections();
        $this->discounting = $target === 'order' ? 'order' : (int) $target;
    }

    public function applyPreset(int $presetId): void
    {
        $preset = $this->presets->firstWhere('id', $presetId);

        $this->attempt(function () use ($preset): void {
            app(Till::class)->applyDiscount($this->order, $this->user(), $this->discountedLine(), $preset);
            $this->say("{$preset->name} given on order {$this->order->number_label}.", 'success');
            $this->resetCorrections();
        });
    }

    public function applyManualDiscount(): void
    {
        $value = Money::parse($this->discountValue);

        if ($value === null) {
            $this->say('Enter the discount as a number, such as 10 or 5.50.', 'error');

            return;
        }

        $this->attemptWithApproval(function () use ($value): void {
            app(Till::class)->applyDiscount($this->order, $this->user(), $this->discountedLine(), null, DiscountType::from($this->discountType), $value, $this->discountReason, $this->approvalPin ?: null);
            $this->say("Discount given on order {$this->order->number_label}.", 'success');
            $this->resetCorrections();
        });
    }

    public function removeDiscount(int $discountId): void
    {
        $discount = OrderDiscount::where('order_id', $this->orderId)->findOrFail($discountId);

        $this->attempt(fn () => app(Till::class)->removeDiscount($discount, $this->user()));
    }

    public function openVoid(int $itemId): void
    {
        $this->resetCorrections();
        $this->voidingItemId = $this->item($itemId)->id;
    }

    public function confirmVoid(): void
    {
        $item = $this->item($this->voidingItemId);

        $this->attemptWithApproval(function () use ($item): void {
            app(Till::class)->voidItem($item, $this->user(), $this->voidReason, $this->approvalPin ?: null);
            $this->say("{$item->name} voided on order {$this->order->number_label}.", 'success');
            $this->resetCorrections();
        });
    }

    public function openCancel(): void
    {
        $this->resetCorrections();
        $this->cancelling = true;
    }

    public function confirmCancel(): void
    {
        $this->attemptWithApproval(function (): void {
            $label = $this->order->number_label;

            app(Till::class)->cancelOrder($this->order, $this->user(), $this->cancelReason, $this->approvalPin ?: null);
            $this->resetCorrections();
            $this->say("Order {$label} cancelled.", 'success');
        });
    }

    public function openRefund(): void
    {
        $this->resetCorrections();
        $this->refunding = true;
        $this->refundQuantities = $this->order->lines
            ->reject(fn (OrderItem $line): bool => $line->isVoided())
            ->mapWithKeys(fn (OrderItem $line): array => [$line->id => 0])
            ->all();
    }

    public function changeRefundQuantity(int $lineId, int $by): void
    {
        $line = $this->order?->lines->firstWhere('id', $lineId);

        if ($line === null) {
            return;
        }

        $this->refundQuantities[$lineId] = max(0, min($line->quantity - $line->refunded_quantity, ($this->refundQuantities[$lineId] ?? 0) + $by));
    }

    public function confirmRefund(): void
    {
        if ($this->shift === null) {
            $this->say('Refunds are paid in cash, so open a shift first.', 'error');

            return;
        }

        $amount = $this->refundMode === 'amount' ? Money::parse($this->refundAmount) : null;

        if ($this->refundMode === 'amount' && $amount === null) {
            $this->say('Enter the amount to refund.', 'error');

            return;
        }

        $this->attemptWithApproval(function () use ($amount): void {
            $refund = app(Till::class)->refund(
                $this->order,
                $this->user(),
                $this->shift,
                $this->refundReason,
                $this->refundMode === 'items' ? array_filter($this->refundQuantities) : [],
                $amount,
                $this->approvalPin ?: null,
            );

            $this->resetCorrections();
            $this->say("Refund {$refund->number_label}: give the customer ".$this->money($refund->amount).' in cash.', 'success');
            $this->printOrOpen(route('pos.refund-slip', $refund->ulid), 'Refund slip sent to the printer.');
        });
    }

    /**
     * The shift's figures so far, without closing it: at the receipt
     * printer if there is one, otherwise from the browser.
     */
    public function printXReport(): void
    {
        if ($this->shift === null) {
            return;
        }

        if (app(PrintQueue::class)->shiftReport($this->shift)) {
            $this->say('X report sent to the printer.', 'success');

            return;
        }

        $this->dispatch('print-receipt', url: route('pos.shift-report', $this->shift->ulid));
    }

    public function closeCorrection(): void
    {
        $this->resetCorrections();
    }

    private function resetCorrections(): void
    {
        $this->reset('discounting', 'discountType', 'discountValue', 'discountReason', 'voidingItemId', 'voidReason', 'cancelling', 'cancelReason', 'refunding', 'refundMode', 'refundQuantities', 'refundAmount', 'refundReason', 'needsApproval', 'approvalPin');
    }

    private function discountedLine(): ?OrderItem
    {
        return is_int($this->discounting) ? $this->item($this->discounting) : null;
    }

    /**
     * Run a correction. If it needs a supervisor, the dialog stays open and
     * asks for their PIN; the next try sends it.
     */
    private function attemptWithApproval(callable $action): void
    {
        try {
            $action();
        } catch (ApprovalRequired $exception) {
            $this->needsApproval = true;
            $this->say($exception->getMessage(), 'warning');
        } catch (SaleException $exception) {
            $this->approvalPin = '';
            $this->say($exception->getMessage(), 'error');
        } catch (AuthorizationException) {
            $this->say('Your role does not allow that.', 'error');
        }

        unset($this->order, $this->openOrders, $this->shift, $this->shiftSummary, $this->transactions, $this->kitchenOrders);
    }

    // Payment -------------------------------------------------------------

    public function openPayment(): void
    {
        if ($this->shift === null) {
            $this->say('Open a shift before taking payment.', 'error');

            return;
        }

        $this->paying = true;
        $this->payingWithId = null;
    }

    public function closePayment(): void
    {
        $this->reset('paying', 'payingWithId', 'payAmount', 'payReference');
    }

    public function startPayment(int $methodId): void
    {
        if ($this->shift === null) {
            $this->say('Open a shift before taking payment.', 'error');

            return;
        }

        $this->payingWithId = $methodId;
        $this->payReference = '';
        $this->payAmount = $this->order?->balance() ?? '';
        // One key per payment, so a double tap records it once.
        $this->paymentKey = (string) Str::ulid();
    }

    public function takePayment(): void
    {
        $amount = Money::parse($this->payAmount);

        if ($amount === null) {
            $this->say('Enter the amount, such as 20 or 20.50.', 'error');

            return;
        }

        $this->attempt(function () use ($amount): void {
            $payment = app(Till::class)->takePayment($this->order, $this->user(), $this->shift, $this->payingWith, $amount, $this->payReference ?: null, $this->paymentKey);

            $this->payingWithId = null;
            $this->paying = false;
            unset($this->order);

            $done = ! $this->order->isOpen();
            $change = $payment->change !== null && Money::isPositive($payment->change) ? ' Change: '.$this->money($payment->change).'.' : '';

            $this->say(($done ? "Order {$this->order->number_label} paid in full." : "Payment taken for order {$this->order->number_label}. Balance: ".$this->money($this->order->balance()).'.').$change, 'success');

            if ($done) {
                $this->printOrOpen(route('pos.receipt', $this->order->ulid), 'Receipt sent to the printer.');
            }
        });
    }

    public function render()
    {
        return view('livewire.pos.register');
    }

    /**
     * With a receipt printer on this terminal, the till has already queued
     * the print for the gateway; without one, the browser prints it.
     */
    private function printOrOpen(string $url, string $note): void
    {
        if ($this->terminal->receiptPrinter()->where('is_active', true)->exists()) {
            $this->message = trim($this->message.' '.$note);

            return;
        }

        $this->dispatch('print-receipt', url: $url);
    }

    // ---------------------------------------------------------------------

    /**
     * @param  list<int>  $chosen
     * @return list<int>
     */
    private function toggle(array $chosen, int $id, int $max): array
    {
        if (in_array($id, $chosen, true)) {
            return array_values(array_diff($chosen, [$id]));
        }

        if ($max === 1) {
            return [$id];
        }

        return count($chosen) >= $max ? $chosen : [...$chosen, $id];
    }

    private function table(?int $id): DiningTable
    {
        return DiningTable::whereKey($id)
            ->whereHas('area', fn ($q) => $q->where('branch_id', $this->branch->id))
            ->firstOrFail();
    }

    private function item(int $id): OrderItem
    {
        return OrderItem::where('order_id', $this->orderId)->findOrFail($id);
    }

    private function user(): User
    {
        return auth()->user();
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
        } catch (SaleException $exception) {
            $this->say($exception->getMessage(), 'error');
        } catch (AuthorizationException) {
            $this->say('Your role does not allow that.', 'error');
        }

        // Computed values are kept for the rest of the request, so those that
        // depend on what just changed are worked out afresh for the screen.
        // Otherwise a dialog closed above would still show.
        unset($this->order, $this->openOrders, $this->shift, $this->shiftSummary, $this->picking, $this->payingWith, $this->transactions, $this->kitchenOrders);
    }

    private function say(string $message, string $tone = 'info'): void
    {
        $this->message = $message;
        $this->messageTone = $tone;
    }
}
