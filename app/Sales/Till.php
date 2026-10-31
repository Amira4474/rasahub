<?php

namespace App\Sales;

use App\Audit\Audit;
use App\Enums\AuditEvent;
use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Enums\KitchenStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\DiscountPreset;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RestaurantSetting;
use App\Models\SetGroup;
use App\Models\SetGroupItem;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
use App\Printing\PrintQueue;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything done at the till: shifts, orders, items and payments. Each
 * method checks the user may do it, so the POS screen cannot be talked into
 * more than the user is allowed.
 *
 * All of it runs inside the business being acted in, on its own database.
 */
class Till
{
    public function openShift(Terminal $terminal, User $user, string $float): Shift
    {
        $this->authorize($user, Permission::ManageShifts);

        try {
            return Shift::create([
                'branch_id' => $terminal->branch_id,
                'terminal_id' => $terminal->id,
                'open_on_terminal_id' => $terminal->id,
                'opened_by' => $user->id,
                'opened_at' => now(),
                'opening_float' => $float,
            ]);
        } catch (QueryException) {
            throw new SaleException('A shift is already open on this terminal.');
        }
    }

    public function closeShift(Shift $shift, User $user, string $counted, ?string $note = null): Shift
    {
        $this->authorize($user, Permission::ManageShifts);

        if (! $shift->isOpen()) {
            throw new SaleException('This shift is already closed.');
        }

        $expected = $shift->expectedCash();

        $shift->update([
            'open_on_terminal_id' => null,
            'closed_by' => $user->id,
            'closed_at' => now(),
            'expected_cash' => $expected,
            'counted_cash' => $counted,
            'variance' => bcsub($counted, $expected, 2),
            'closing_note' => $note,
        ]);

        app(PrintQueue::class)->shiftReport($shift);

        if (bccomp($shift->variance, '0', 2) !== 0) {
            app(Audit::class)->record(AuditEvent::CashDrawerDifference, properties: array_filter([
                'terminal' => $shift->terminal->name,
                'expected' => $expected,
                'counted' => $counted,
                'difference' => $shift->variance,
                'note' => $note,
            ]));
        }

        return $shift;
    }

    /**
     * Start an order at a terminal. It takes the restaurant's tax settings
     * and the branch's service charge as they are now, and its number for
     * the branch's business day.
     */
    public function startOrder(Terminal $terminal, User $user, OrderType $type, ?DiningTable $table = null, ?int $guests = null): Order
    {
        $this->authorize($user, Permission::TakeOrders);

        $branch = $terminal->branch;

        if ($table !== null) {
            if ($table->area->branch_id !== $branch->id) {
                throw new SaleException('That table is at another branch.');
            }

            if (($open = $this->openOrderAt($table)) !== null) {
                throw new SaleException("Table {$table->name} already has order {$open->number_label}.");
            }
        }

        return $this->createOrder($branch, $terminal, $user, $type, $table, $guests);
    }

    /**
     * A new order with the restaurant's tax settings and the branch's service
     * charge as they are now, and its number for the branch's business day.
     * No permission is checked here: the callers do.
     */
    public function createOrder(Branch $branch, ?Terminal $terminal, ?User $user, OrderType $type, ?DiningTable $table = null, ?int $guests = null, string $source = 'pos', OrderStatus $status = OrderStatus::Draft): Order
    {
        $settings = RestaurantSetting::current();
        $businessDate = $branch->businessDateFor(now());
        $number = Sequence::next("order:{$branch->id}:{$businessDate->toDateString()}");

        $chargesService = $branch->chargesServiceCharge()
            && ($type === OrderType::DineIn || ! $branch->service_charge_dine_in_only);

        return Order::create([
            'branch_id' => $branch->id,
            'terminal_id' => $terminal?->id,
            'shift_id' => $terminal ? Shift::openOn($terminal)?->id : null,
            'source' => $source,
            'business_date' => $businessDate->toDateString(),
            'number' => $number,
            'number_label' => $branch->code.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'type' => $type,
            'dining_table_id' => $type === OrderType::DineIn ? $table?->id : null,
            'guests' => $type === OrderType::DineIn ? $guests : null,
            'status' => $status,
            'payment_status' => PaymentStatus::Unpaid,
            'sst_enabled' => $settings->sst_enabled,
            'sst_rate' => $settings->sst_rate,
            'prices_include_sst' => $settings->prices_include_sst,
            'service_charge_rate' => $chargesService ? $branch->service_charge_rate : Money::ZERO,
            'round_to_five_sen' => $settings->round_to_five_sen,
            'opened_by' => $user?->id,
        ]);
    }

    /**
     * The table's open order. A QR order still waiting for staff does not
     * count: it joins this order when it is confirmed.
     */
    public function openOrderAt(DiningTable $table): ?Order
    {
        return Order::open()
            ->where('dining_table_id', $table->id)
            ->where('status', '!=', OrderStatus::Pending)
            ->first();
    }

    /**
     * Add a product to an order with its chosen modifiers and, for a set
     * meal, the products chosen for each of its choices.
     *
     * @param  list<int>  $modifierIds
     * @param  array<int, list<int>>  $setChoices  Products chosen, by set group.
     */
    public function addItem(Order $order, User $user, Product $product, int $quantity = 1, array $modifierIds = [], array $setChoices = [], ?string $note = null): OrderItem
    {
        $this->authorize($user, Permission::TakeOrders);

        return $this->addLine($order, $product, $quantity, $modifierIds, $setChoices, $note);
    }

    /**
     * Add a line, checking the product is on sale and the choices allowed,
     * at the branch's price. No permission is checked here: the callers do.
     *
     * @param  list<int>  $modifierIds
     * @param  array<int, list<int>>  $setChoices
     */
    public function addLine(Order $order, Product $product, int $quantity = 1, array $modifierIds = [], array $setChoices = [], ?string $note = null): OrderItem
    {
        $this->ensureOpen($order);

        $product->loadMissing(['modifierGroups.modifiers', 'setGroups.items.product.station', 'setGroups.items.product.category.station', 'setGroups.items.product.category.parent.station', 'branchSettings', 'station', 'category.station', 'category.parent.station']);

        if (! $product->isAvailableAt($order->branch_id)) {
            throw new SaleException("{$product->name} is not available.");
        }

        if ($quantity < 1 || $quantity > 999) {
            throw new SaleException('The quantity must be between 1 and 999.');
        }

        $modifiers = $this->chosenModifiers($product, $modifierIds);
        $parts = $product->isSet() ? $this->chosenParts($product, $setChoices) : collect();

        $unitPrice = $product->priceAt($order->branch_id);
        $modifiersTotal = Money::sum($modifiers->pluck('price_delta'));

        return DB::connection($order->getConnectionName())->transaction(function () use ($order, $product, $quantity, $note, $modifiers, $parts, $unitPrice, $modifiersTotal): OrderItem {
            $station = $product->effectiveStation();

            $item = $order->items()->create([
                'product_id' => $product->id,
                'station_id' => $station?->id,
                'station_name' => $station?->name,
                'name' => $product->name,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'modifiers_total' => $modifiersTotal,
                'line_total' => bcmul(bcadd($unitPrice, $modifiersTotal, 2), (string) $quantity, 2),
                'note' => filled($note) ? $note : null,
            ]);

            foreach ($modifiers as $modifier) {
                $item->modifiers()->create([
                    'modifier_id' => $modifier->id,
                    'group_name' => $modifier->group->name,
                    'name' => $modifier->name,
                    'price_delta' => $modifier->price_delta,
                ]);
            }

            foreach ($parts as $part) {
                $partStation = $part->product->effectiveStation();

                $order->items()->create([
                    'parent_id' => $item->id,
                    'product_id' => $part->product_id,
                    'station_id' => $partStation?->id,
                    'station_name' => $partStation?->name,
                    'name' => $part->product->name,
                    'unit_price' => $part->extra_price,
                    'quantity' => $quantity,
                    'line_total' => bcmul($part->extra_price, (string) $quantity, 2),
                ]);
            }

            OrderTotals::apply($order);

            return $item;
        });
    }

    /**
     * Change how many of a line not yet sent to the kitchen.
     */
    public function setQuantity(OrderItem $item, User $user, int $quantity): void
    {
        $this->authorize($user, Permission::TakeOrders);
        $this->ensureOpen($item->order);
        $this->ensureNotSent($item);

        if ($quantity < 1) {
            $this->removeItem($item, $user);

            return;
        }

        $item->update([
            'quantity' => $quantity,
            'line_total' => bcmul(bcadd($item->unit_price, $item->modifiers_total, 2), (string) $quantity, 2),
        ]);

        foreach ($item->parts as $part) {
            $part->update(['quantity' => $quantity, 'line_total' => bcmul($part->unit_price, (string) $quantity, 2)]);
        }

        OrderTotals::apply($item->order);
    }

    /**
     * Take a line off the order. Once sent to the kitchen, a line can only
     * be voided, which comes with v0.4.0.
     */
    public function removeItem(OrderItem $item, User $user): void
    {
        $this->authorize($user, Permission::TakeOrders);
        $this->ensureOpen($item->order);
        $this->ensureNotSent($item);

        $order = $item->order;
        $item->delete();

        OrderTotals::apply($order);
    }

    /**
     * Send the lines not yet sent to the kitchen.
     */
    public function sendToKitchen(Order $order, User $user): int
    {
        $this->authorize($user, Permission::TakeOrders);
        $this->ensureOpen($order);

        if (! $order->items()->exists()) {
            throw new SaleException('Add something to the order first.');
        }

        $sent = $this->markSent($order);

        if ($order->status === OrderStatus::Draft) {
            $order->update(['status' => OrderStatus::Confirmed]);
        }

        return $sent;
    }

    /**
     * Send the lines not yet sent, and print a ticket for each station that
     * has a printer. A set meal's own line is not printed: its parts are,
     * each at its own station.
     */
    private function markSent(Order $order): int
    {
        $pending = $order->items()
            ->where('kitchen_status', KitchenStatus::Pending)
            ->with(['modifiers', 'parts'])
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        $order->items()->whereKey($pending->modelKeys())->update([
            'kitchen_status' => KitchenStatus::Sent,
            'sent_at' => now(),
        ]);

        app(PrintQueue::class)->kitchenTickets($order, $pending->filter(fn (OrderItem $line): bool => $line->parts->isEmpty())->values());

        return $pending->whereNull('parent_id')->count();
    }

    /**
     * Mark one line ready, or not ready again, from a kitchen screen. When
     * every line of an open order is ready, so is the order.
     */
    public function setItemReady(OrderItem $item, User $user, bool $ready = true): void
    {
        $this->authorize($user, Permission::UseKitchenDisplay);

        $cooking = [KitchenStatus::Sent, KitchenStatus::Preparing];

        if ($ready && ! in_array($item->kitchen_status, $cooking, true)) {
            throw new SaleException("{$item->name} is not being made.");
        }

        if (! $ready && $item->kitchen_status !== KitchenStatus::Ready) {
            throw new SaleException("{$item->name} is not ready.");
        }

        $item->update($ready
            ? ['kitchen_status' => KitchenStatus::Ready, 'ready_at' => now()]
            : ['kitchen_status' => KitchenStatus::Preparing, 'ready_at' => null]);

        $this->followKitchen($item->order);
    }

    /**
     * Move the lines of some stations (or all) from one stage to the next.
     *
     * @param  list<int>  $stationIds  Empty for every station.
     */
    public function advanceStations(Order $order, User $user, KitchenStatus $from, array $stationIds = []): int
    {
        $this->authorize($user, Permission::UseKitchenDisplay);

        $to = match ($from) {
            KitchenStatus::Sent => KitchenStatus::Preparing,
            KitchenStatus::Preparing => KitchenStatus::Ready,
            KitchenStatus::Ready => KitchenStatus::Served,
            KitchenStatus::Served => KitchenStatus::Ready,
            default => throw new SaleException('Those lines cannot be moved on.'),
        };

        $lines = $order->items()
            ->whereDoesntHave('parts')
            ->when($from === KitchenStatus::Sent && $to === KitchenStatus::Preparing, fn ($q) => $q->where('kitchen_status', KitchenStatus::Sent))
            ->when($to === KitchenStatus::Ready && $from !== KitchenStatus::Served, fn ($q) => $q->whereIn('kitchen_status', [KitchenStatus::Sent, KitchenStatus::Preparing]))
            ->when($from === KitchenStatus::Ready || $from === KitchenStatus::Served, fn ($q) => $q->where('kitchen_status', $from))
            ->when($stationIds !== [], fn ($q) => $q->whereIn('station_id', $stationIds));

        // MySQL cannot update a table while reading it in a subquery, so the
        // lines are found first.
        $ids = $lines->pluck('id');

        $moved = $ids->isEmpty() ? 0 : OrderItem::whereKey($ids)->update(array_filter([
            'kitchen_status' => $to,
            'ready_at' => $to === KitchenStatus::Ready ? now() : null,
        ], fn ($value) => $value !== null));

        if ($moved === 0) {
            throw new SaleException("Order {$order->number_label} has nothing at that stage.");
        }

        $this->followKitchen($order);

        return $moved;
    }

    /**
     * The order's own status follows its lines while it is open: preparing
     * while anything is still being made, ready once everything is.
     */
    private function followKitchen(Order $order): void
    {
        if (! $order->isOpen() || $order->status === OrderStatus::Draft) {
            return;
        }

        $lines = $order->items()->whereDoesntHave('parts')->whereNotIn('kitchen_status', [KitchenStatus::Voided, KitchenStatus::Pending])->pluck('kitchen_status');

        if ($lines->isEmpty()) {
            return;
        }

        $status = match (true) {
            $lines->every(fn (KitchenStatus $s): bool => in_array($s, [KitchenStatus::Ready, KitchenStatus::Served], true)) => OrderStatus::Ready,
            $lines->contains(fn (KitchenStatus $s): bool => $s !== KitchenStatus::Sent) => OrderStatus::Preparing,
            default => OrderStatus::Confirmed,
        };

        if ($order->status !== $status) {
            $order->update(['status' => $status]);
        }
    }

    /**
     * Move an order's kitchen lines from one stage to the next: sent to
     * preparing, preparing to ready, ready to served. The order's own status
     * follows while it is open.
     */
    public function advanceKitchen(Order $order, User $user, KitchenStatus $from): int
    {
        return $this->advanceStations($order, $user, $from);
    }

    /**
     * Confirm a QR order: it joins the table's open order if there is one,
     * or becomes it, and goes to the kitchen. With no user, the branch
     * confirmed it automatically.
     */
    public function confirmQrOrder(Order $order, ?User $user, ?Terminal $terminal = null): Order
    {
        if ($user !== null) {
            $this->authorize($user, Permission::TakeOrders);
        }

        if ($order->status !== OrderStatus::Pending || $order->source !== 'qr') {
            throw new SaleException("Order {$order->number_label} is not waiting to be confirmed.");
        }

        return DB::connection($order->getConnectionName())->transaction(function () use ($order, $user, $terminal): Order {
            $into = $order->diningTable ? $this->openOrderAt($order->diningTable) : null;

            if ($into !== null) {
                // The table already has an order: this round joins it.
                $order->items()->update(['order_id' => $into->id]);

                if ($into->customer_id === null && $order->customer_id !== null) {
                    $into->update(['customer_id' => $order->customer_id]);
                }

                $order->update(['status' => OrderStatus::Cancelled, 'note' => "Joined {$into->number_label}."]);
                OrderTotals::apply($order);
                OrderTotals::apply($into);
            } else {
                $order->update([
                    'status' => OrderStatus::Draft,
                    'terminal_id' => $order->terminal_id ?? $terminal?->id,
                    'shift_id' => $order->shift_id ?? ($terminal ? Shift::openOn($terminal)?->id : null),
                    'opened_by' => $order->opened_by ?? $user?->id,
                ]);
                $into = $order;
            }

            $this->markSent($into);

            if ($into->status === OrderStatus::Draft) {
                $into->update(['status' => OrderStatus::Confirmed]);
            }

            return $into->refresh();
        });
    }

    public function rejectQrOrder(Order $order, User $user, string $reason): void
    {
        $this->authorize($user, Permission::TakeOrders);

        if ($order->status !== OrderStatus::Pending || $order->source !== 'qr') {
            throw new SaleException("Order {$order->number_label} is not waiting to be confirmed.");
        }

        if (blank($reason)) {
            throw new SaleException('Give the customer a reason.');
        }

        $order->update([
            'status' => OrderStatus::Cancelled,
            'reject_reason' => $reason,
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Attach a customer by phone number, adding them if they are new.
     */
    public function attachCustomer(Order $order, User $user, string $phone, ?string $name = null): Customer
    {
        $this->authorize($user, Permission::TakeOrders);
        $this->ensureOpen($order);

        $customer = $this->customerFor($phone, $name);

        $order->update(['customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * The customer with this phone number, added if new. A consent given
     * now is recorded; it is never taken away here.
     */
    public function customerFor(string $phone, ?string $name = null, bool $consent = false): Customer
    {
        $digits = Customer::normalisePhone($phone);

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw new SaleException('Enter a phone number of 8 to 15 digits.');
        }

        $customer = Customer::withTrashed()->where('phone', $digits)->first();

        if ($customer === null) {
            return Customer::create(['phone' => $digits, 'name' => filled($name) ? $name : null, 'marketing_consent' => $consent]);
        }

        $customer->restore();
        $customer->fill(array_filter([
            'name' => blank($customer->name) && filled($name) ? $name : null,
            'marketing_consent' => $consent ? true : null,
        ], fn ($value) => $value !== null));
        $customer->save();

        return $customer;
    }

    public function detachCustomer(Order $order, User $user): void
    {
        $this->authorize($user, Permission::TakeOrders);
        $this->ensureOpen($order);

        $order->update(['customer_id' => null]);
    }

    /**
     * Move a dine-in order to another, free table at the same branch.
     */
    public function moveToTable(Order $order, User $user, DiningTable $table): void
    {
        $this->authorize($user, Permission::TakeOrders);
        $this->ensureOpen($order);

        if ($table->area->branch_id !== $order->branch_id) {
            throw new SaleException('That table is at another branch.');
        }

        if (($open = $this->openOrderAt($table)) !== null && ! $open->is($order)) {
            throw new SaleException("Table {$table->name} already has order {$open->number_label}. Merge the orders instead.");
        }

        $order->update(['type' => OrderType::DineIn, 'dining_table_id' => $table->id, 'table_label' => null]);
    }

    /**
     * Bring another open order's lines into this one, for guests who join
     * tables, and close the other order. An order that has taken payments
     * cannot be merged away.
     */
    public function merge(Order $into, Order $from, User $user): void
    {
        $this->authorize($user, Permission::TakeOrders);
        $this->ensureOpen($into);
        $this->ensureOpen($from);

        if ($into->is($from) || $from->branch_id !== $into->branch_id) {
            throw new SaleException('Those orders cannot be merged.');
        }

        if (Money::isPositive($from->paid)) {
            throw new SaleException("Order {$from->number_label} has payments, so it cannot be merged.");
        }

        DB::connection($into->getConnectionName())->transaction(function () use ($into, $from): void {
            $from->items()->update(['order_id' => $into->id]);

            $into->update([
                'guests' => ($into->guests || $from->guests) ? (int) $into->guests + (int) $from->guests : null,
                'customer_id' => $into->customer_id ?? $from->customer_id,
            ]);

            $from->update([
                'status' => OrderStatus::Cancelled,
                'dining_table_id' => null,
                'note' => trim(($from->note ? $from->note."\n" : '')."Merged into {$into->number_label}."),
            ]);

            OrderTotals::apply($from);
            OrderTotals::apply($into);
        });
    }

    /**
     * Record a payment. Paying the balance in full completes the order,
     * gives it its receipt number and frees its table. The key makes the
     * call safe to repeat: the same key never records a second payment.
     */
    public function takePayment(Order $order, User $user, Shift $shift, PaymentMethod $method, string $amount, ?string $reference, string $key): Payment
    {
        $this->authorize($user, Permission::TakePayments);

        if (($existing = Payment::where('ulid', $key)->first()) !== null) {
            return $existing;
        }

        if (! $shift->isOpen()) {
            throw new SaleException('Open a shift before taking payment.');
        }

        if (! $method->is_active) {
            throw new SaleException("{$method->name} is no longer accepted.");
        }

        if ($method->requires_reference && blank($reference)) {
            throw new SaleException("Enter the {$method->name} reference.");
        }

        return DB::connection($order->getConnectionName())->transaction(function () use ($order, $user, $shift, $method, $amount, $reference, $key): Payment {
            // Locked, so two terminals cannot both take the last payment.
            $order = Order::lockForUpdate()->findOrFail($order->id);

            $this->ensureOpen($order);

            if (! $order->items()->exists()) {
                throw new SaleException('There is nothing to pay for yet.');
            }

            $balance = $order->balance();

            if (! Money::isPositive($amount)) {
                throw new SaleException('Enter an amount.');
            }

            // Cash can be more than the balance, and change is given. Other
            // methods take the exact amount at most.
            if ($method->isCash()) {
                $paid = Money::min($amount, $balance);
                $tendered = $amount;
                $change = bcsub($amount, $paid, 2);
            } else {
                if (bccomp($amount, $balance, 2) > 0) {
                    throw new SaleException("{$method->name} can take at most the balance of {$balance}.");
                }

                $paid = $amount;
                $tendered = null;
                $change = null;
            }

            $payment = $order->payments()->create([
                'ulid' => $key,
                'shift_id' => $shift->id,
                'payment_method_id' => $method->id,
                'method_name' => $method->name,
                'method_type' => $method->type,
                'amount' => $paid,
                'tendered' => $tendered,
                'change' => $change,
                'reference' => filled($reference) ? $reference : null,
                'received_by' => $user->id,
                'received_at' => now(),
            ]);

            $totalPaid = bcadd($order->paid, $paid, 2);
            $settled = bccomp($totalPaid, $order->total, 2) >= 0;

            $order->update([
                'paid' => $totalPaid,
                'payment_status' => $settled ? PaymentStatus::Paid : PaymentStatus::PartiallyPaid,
            ]);

            if ($settled) {
                $this->complete($order, $shift);
            }

            return $payment;
        });
    }

    /**
     * Give a discount on a line or on the whole order: a preset, or one typed
     * in with a reason. A typed discount needs approval from someone who may
     * give one, unless the user may themselves.
     */
    public function applyDiscount(
        Order $order,
        User $user,
        ?OrderItem $item,
        ?DiscountPreset $preset,
        ?DiscountType $type = null,
        ?string $value = null,
        ?string $reason = null,
        ?string $pin = null,
    ): OrderDiscount {
        $this->authorize($user, Permission::ApplyDiscounts);
        $this->ensureOpen($order);

        if ($item !== null && ($item->order_id !== $order->id || $item->parent_id !== null || $item->isVoided())) {
            throw new SaleException('That line cannot be discounted.');
        }

        $approver = $user;

        if ($preset !== null) {
            if (! $preset->is_active || $preset->scope !== ($item ? DiscountScope::Item : DiscountScope::Order)) {
                throw new SaleException("{$preset->name} cannot be given here.");
            }

            [$name, $type, $value] = [$preset->name, $preset->type, $preset->value];
        } else {
            if ($type === null || $value === null || ! Money::isPositive($value)) {
                throw new SaleException('Enter the discount.');
            }

            if ($type === DiscountType::Percent && bccomp($value, '100', 2) > 0) {
                throw new SaleException('A discount cannot be more than 100%.');
            }

            if (blank($reason)) {
                throw new SaleException('Give a reason for the discount.');
            }

            $approver = $this->approval($user, Permission::ManualDiscounts, $pin);
            $name = 'Discount';
        }

        return DB::connection($order->getConnectionName())->transaction(function () use ($order, $user, $item, $preset, $type, $value, $reason, $approver, $name): OrderDiscount {
            $discount = $order->discounts()->create([
                'order_item_id' => $item?->id,
                'discount_preset_id' => $preset?->id,
                'name' => $name,
                'type' => $type,
                'value' => $value,
                'reason' => filled($reason) ? $reason : null,
                'applied_by' => $user->id,
                'approved_by' => $approver->is($user) ? null : $approver->id,
            ]);

            OrderTotals::apply($order);

            if (bccomp($order->total, $order->paid, 2) < 0) {
                throw new SaleException('The discount would take the total below what has already been paid.');
            }

            if ($preset === null) {
                $this->audit(AuditEvent::DiscountGiven, $order, $user, $approver, [
                    'line' => $item?->name,
                    'discount' => $type->value === 'percent' ? "{$value}%" : $value,
                    'amount' => $discount->fresh()->amount,
                    'reason' => $reason,
                ]);
            }

            return $discount;
        });
    }

    public function removeDiscount(OrderDiscount $discount, User $user): void
    {
        $this->authorize($user, Permission::ApplyDiscounts);

        $order = Order::findOrFail($discount->order_id);
        $this->ensureOpen($order);

        $discount->delete();
        OrderTotals::apply($order);
    }

    /**
     * Take back a line already sent to the kitchen. It stays on the order,
     * crossed out and at nothing. Needs approval unless the user may void.
     */
    public function voidItem(OrderItem $item, User $user, string $reason, ?string $pin = null): void
    {
        $this->authorize($user, Permission::TakeOrders);

        $order = $item->order;
        $this->ensureOpen($order);

        if ($item->parent_id !== null || $item->isVoided()) {
            throw new SaleException('That line cannot be voided.');
        }

        if (! $item->isSent()) {
            $this->removeItem($item, $user);

            return;
        }

        if (blank($reason)) {
            throw new SaleException('Give a reason for voiding.');
        }

        $approver = $this->approval($user, Permission::VoidItems, $pin);

        DB::connection($order->getConnectionName())->transaction(function () use ($item, $order, $user, $reason, $approver): void {
            $void = [
                'kitchen_status' => KitchenStatus::Voided,
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'void_approved_by' => $approver->is($user) ? null : $approver->id,
            ];

            $item->update($void);
            $item->parts()->update($void);

            foreach ($item->parts()->get()->whenEmpty(fn ($none) => collect([$item->fresh()])) as $line) {
                app(PrintQueue::class)->voidTicket($order, $line);
            }

            OrderTotals::apply($order);

            if (bccomp($order->total, $order->paid, 2) < 0) {
                throw new SaleException('Voiding this would take the total below what has already been paid. Refund instead.');
            }

            $this->audit(AuditEvent::ItemVoided, $order, $user, $approver, [
                'line' => "{$item->quantity} × {$item->name}",
                'amount' => $item->grossTotal(),
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Cancel an order nothing has been paid on. One with lines in the
     * kitchen needs approval unless the user may cancel orders.
     */
    public function cancelOrder(Order $order, User $user, string $reason, ?string $pin = null): void
    {
        $this->authorize($user, Permission::TakeOrders);
        $this->ensureOpen($order);

        if (Money::isPositive($order->paid)) {
            throw new SaleException("Order {$order->number_label} has payments. Pay it in full and refund instead.");
        }

        if (blank($reason)) {
            throw new SaleException('Give a reason for cancelling.');
        }

        $sent = $order->items()->whereNotIn('kitchen_status', [KitchenStatus::Pending, KitchenStatus::Voided])->exists();
        $approver = $sent ? $this->approval($user, Permission::CancelOrders, $pin) : $user;

        DB::connection($order->getConnectionName())->transaction(function () use ($order, $user, $reason, $approver): void {
            $order->items()->whereNot('kitchen_status', KitchenStatus::Voided)->update(['kitchen_status' => KitchenStatus::Voided, 'voided_at' => now()]);

            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancel_reason' => $reason,
                'cancelled_by' => $user->id,
                'cancel_approved_by' => $approver->is($user) ? null : $approver->id,
                'cancelled_at' => now(),
            ]);

            OrderTotals::apply($order);

            $this->audit(AuditEvent::OrderCancelled, $order, $user, $approver, ['reason' => $reason]);
        });
    }

    /**
     * Give money back for a paid order, in cash from this shift's drawer:
     * for some of its lines (their share of what was paid), or an amount.
     *
     * @param  array<int, int>  $quantities  Quantities to give back, by line.
     */
    public function refund(Order $order, User $user, Shift $shift, string $reason, array $quantities = [], ?string $amount = null, ?string $pin = null): Refund
    {
        $this->authorize($user, Permission::TakeOrders);

        if ($order->status !== OrderStatus::Completed) {
            throw new SaleException("Only a paid order can be refunded; order {$order->number_label} is {$order->status->label()}.");
        }

        $days = RestaurantSetting::current()->refund_days;

        if ($order->completed_at->lt(now()->subDays($days))) {
            throw new SaleException("Order {$order->number_label} was paid more than {$days} days ago, so it can no longer be refunded.");
        }

        if (! $shift->isOpen() || $shift->branch_id !== $order->branch_id) {
            throw new SaleException('Refunds are paid in cash, so a shift must be open on this terminal.');
        }

        if (blank($reason)) {
            throw new SaleException('Give a reason for the refund.');
        }

        $approver = $this->approval($user, Permission::IssueRefunds, $pin);

        return DB::connection($order->getConnectionName())->transaction(function () use ($order, $user, $shift, $reason, $quantities, $amount, $approver): Refund {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $order->load('lines.parts');

            [$value, $lines] = $quantities !== []
                ? $this->refundForLines($order, $quantities)
                : [$amount === null ? Money::ZERO : $amount, []];

            if (! Money::isPositive($value)) {
                throw new SaleException('Choose what to refund.');
            }

            if (bccomp($value, $order->refundable(), 2) > 0) {
                throw new SaleException('That is more than can still be refunded: '.$order->refundable().'.');
            }

            $number = Sequence::next("refund:{$order->branch_id}");

            $refund = $order->refunds()->create([
                'branch_id' => $order->branch_id,
                'shift_id' => $shift->id,
                'number' => $number,
                'number_label' => $order->branch->code.'-RF'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'amount' => $value,
                'method_name' => 'Cash',
                'reason' => $reason,
                'items' => $lines ?: null,
                'refunded_by' => $user->id,
                'approved_by' => $approver->is($user) ? null : $approver->id,
            ]);

            foreach ($lines as $line) {
                OrderItem::whereKey($line['id'])->increment('refunded_quantity', $line['quantity']);
            }

            $refunded = bcadd($order->refunded, $value, 2);

            $order->update([
                'refunded' => $refunded,
                'payment_status' => bccomp($refunded, $order->paid, 2) >= 0 ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ]);

            app(PrintQueue::class)->refundSlip($refund, $shift->terminal);

            $this->audit(AuditEvent::RefundIssued, $order, $user, $approver, [
                'refund' => $refund->number_label,
                'amount' => $value,
                'lines' => collect($lines)->map(fn (array $line): string => "{$line['quantity']} × {$line['name']}")->all() ?: null,
                'reason' => $reason,
            ]);

            return $refund;
        });
    }

    /**
     * The share of what was paid that belongs to the chosen lines, with
     * SST, service charge, order discounts and rounding in proportion.
     *
     * @param  array<int, int>  $quantities
     * @return array{0: string, 1: list<array{id: int, name: string, quantity: int}>}
     */
    private function refundForLines(Order $order, array $quantities): array
    {
        $goods = bcsub($order->subtotal, $order->discount_total, 2);
        $share = Money::ZERO;
        $lines = [];

        foreach ($quantities as $id => $quantity) {
            $quantity = (int) $quantity;

            if ($quantity < 1) {
                continue;
            }

            $line = $order->lines->firstWhere('id', (int) $id);

            if ($line === null || $line->isVoided() || $quantity > $line->quantity - $line->refunded_quantity) {
                throw new SaleException('Some of those items cannot be refunded.');
            }

            $share = bcadd($share, bcdiv(bcmul($line->netTotal(), (string) $quantity, 6), (string) $line->quantity, 6), 6);
            $lines[] = ['id' => $line->id, 'name' => $line->name, 'quantity' => $quantity];
        }

        if ($lines === [] || ! Money::isPositive($goods)) {
            return [Money::ZERO, []];
        }

        $value = Money::round(bcdiv(bcmul($share, $order->paid, 6), $goods, 6));

        // Cash is given back in 5 sen, as it was taken.
        if ($order->round_to_five_sen) {
            $value = Money::roundToFiveSen($value);
        }

        return [Money::min($value, $order->refundable()), $lines];
    }

    private function approval(User $user, Permission $permission, ?string $pin): User
    {
        $approver = app(Approval::class)->approver($user, $permission, $pin);

        if ($approver === null) {
            throw new ApprovalRequired;
        }

        return $approver;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(AuditEvent $event, Order $order, User $user, User $approver, array $details): void
    {
        app(Audit::class)->record($event, properties: array_filter([
            'order' => $order->number_label,
            ...$details,
            'approved_by' => $approver->is($user) ? null : $approver->name,
        ], fn ($value): bool => $value !== null));
    }

    private function complete(Order $order, Shift $shift): void
    {
        // A takeaway paid before it was sent goes to the kitchen now.
        $this->markSent($order);

        $order->update([
            'status' => OrderStatus::Completed,
            'receipt_number' => Sequence::next("receipt:{$order->branch_id}"),
            'completed_at' => now(),
        ]);

        app(PrintQueue::class)->receipt($order, $shift->terminal);
    }

    /**
     * @param  list<int>  $modifierIds
     * @return Collection<int, Modifier>
     */
    private function chosenModifiers(Product $product, array $modifierIds): Collection
    {
        $chosen = collect();

        foreach ($product->modifierGroups->where('is_active', true) as $group) {
            /** @var ModifierGroup $group */
            $picked = $group->modifiers->where('is_active', true)->whereIn('id', $modifierIds);

            if ($picked->count() < $group->min_select || $picked->count() > $group->max_select) {
                throw new SaleException("{$group->name}: {$group->rule()}.");
            }

            $chosen = $chosen->merge($picked->each(fn (Modifier $modifier) => $modifier->setRelation('group', $group)));
        }

        if ($chosen->count() !== count(array_unique($modifierIds))) {
            throw new SaleException("Some of the choices are not offered for {$product->name}.");
        }

        return $chosen->values();
    }

    /**
     * @param  array<int, list<int>>  $setChoices
     * @return Collection<int, SetGroupItem>
     */
    private function chosenParts(Product $product, array $setChoices): Collection
    {
        $parts = collect();

        foreach ($product->setGroups as $group) {
            /** @var SetGroup $group */
            $wanted = array_map('intval', $setChoices[$group->id] ?? []);
            $picked = $group->items
                ->filter(fn ($item): bool => in_array($item->product_id, $wanted, true) && $item->product?->is_active && $item->product->deleted_at === null);

            if ($picked->count() !== count(array_unique($wanted)) || $picked->count() < $group->min_select || $picked->count() > $group->max_select) {
                throw new SaleException("{$group->name}: choose ".($group->min_select === $group->max_select ? $group->max_select : "{$group->min_select} to {$group->max_select}").'.');
            }

            $parts = $parts->merge($picked);
        }

        return $parts->values();
    }

    private function ensureOpen(Order $order): void
    {
        if (! $order->isOpen()) {
            throw new SaleException("Order {$order->number_label} is already {$order->status->label()}.");
        }
    }

    private function ensureNotSent(OrderItem $item): void
    {
        if ($item->isSent() || $item->parent_id !== null) {
            throw new SaleException("{$item->name} has been sent to the kitchen. Voiding it comes with a later release.");
        }
    }

    private function authorize(User $user, Permission $permission): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException;
        }
    }
}
