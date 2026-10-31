<?php

namespace App\Sales;

use App\Enums\KitchenStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RestaurantSetting;
use App\Models\SetGroup;
use App\Models\SetGroupItem;
use App\Models\Shift;
use App\Models\Terminal;
use App\Models\User;
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

        $settings = RestaurantSetting::current();
        $businessDate = $branch->businessDateFor(now());
        $number = Sequence::next("order:{$branch->id}:{$businessDate->toDateString()}");

        $chargesService = $branch->chargesServiceCharge()
            && ($type === OrderType::DineIn || ! $branch->service_charge_dine_in_only);

        return Order::create([
            'branch_id' => $branch->id,
            'terminal_id' => $terminal->id,
            'shift_id' => Shift::openOn($terminal)?->id,
            'business_date' => $businessDate->toDateString(),
            'number' => $number,
            'number_label' => $branch->code.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'type' => $type,
            'dining_table_id' => $type === OrderType::DineIn ? $table?->id : null,
            'guests' => $type === OrderType::DineIn ? $guests : null,
            'status' => OrderStatus::Draft,
            'payment_status' => PaymentStatus::Unpaid,
            'sst_enabled' => $settings->sst_enabled,
            'sst_rate' => $settings->sst_rate,
            'prices_include_sst' => $settings->prices_include_sst,
            'service_charge_rate' => $chargesService ? $branch->service_charge_rate : Money::ZERO,
            'round_to_five_sen' => $settings->round_to_five_sen,
            'opened_by' => $user->id,
        ]);
    }

    public function openOrderAt(DiningTable $table): ?Order
    {
        return Order::open()->where('dining_table_id', $table->id)->first();
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
        $this->ensureOpen($order);

        $product->loadMissing(['modifierGroups.modifiers', 'setGroups.items.product', 'branchSettings']);

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
            $item = $order->items()->create([
                'product_id' => $product->id,
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
                $order->items()->create([
                    'parent_id' => $item->id,
                    'product_id' => $part->product_id,
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

        $sent = $order->items()->where('kitchen_status', KitchenStatus::Pending)->update([
            'kitchen_status' => KitchenStatus::Sent,
            'sent_at' => now(),
        ]);

        if ($sent === 0 && ! $order->items()->exists()) {
            throw new SaleException('Add something to the order first.');
        }

        if ($order->status === OrderStatus::Draft) {
            $order->update(['status' => OrderStatus::Confirmed]);
        }

        return $sent;
    }

    /**
     * Move an order's kitchen lines from one stage to the next: sent to
     * preparing, preparing to ready, ready to served. The order's own status
     * follows while it is open.
     */
    public function advanceKitchen(Order $order, User $user, KitchenStatus $from): int
    {
        $this->authorize($user, Permission::UseKitchenDisplay);

        $to = match ($from) {
            KitchenStatus::Sent => KitchenStatus::Preparing,
            KitchenStatus::Preparing => KitchenStatus::Ready,
            KitchenStatus::Ready => KitchenStatus::Served,
            default => throw new SaleException('Those lines cannot be moved on.'),
        };

        $moved = $order->items()->where('kitchen_status', $from)->update(['kitchen_status' => $to]);

        if ($moved === 0) {
            throw new SaleException("Order {$order->number_label} has nothing at that stage.");
        }

        if ($order->isOpen() && in_array($to, [KitchenStatus::Preparing, KitchenStatus::Ready], true)) {
            $stillCooking = $order->items()->whereIn('kitchen_status', [KitchenStatus::Sent, KitchenStatus::Preparing])->exists();

            $order->update(['status' => $to === KitchenStatus::Ready && ! $stillCooking ? OrderStatus::Ready : OrderStatus::Preparing]);
        }

        return $moved;
    }

    /**
     * Attach a customer by phone number, adding them if they are new.
     */
    public function attachCustomer(Order $order, User $user, string $phone, ?string $name = null): Customer
    {
        $this->authorize($user, Permission::TakeOrders);
        $this->ensureOpen($order);

        $digits = Customer::normalisePhone($phone);

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw new SaleException('Enter a phone number of 8 to 15 digits.');
        }

        $customer = Customer::withTrashed()->where('phone', $digits)->first();

        if ($customer === null) {
            $customer = Customer::create(['phone' => $digits, 'name' => filled($name) ? $name : null]);
        } else {
            $customer->restore();

            if (blank($customer->name) && filled($name)) {
                $customer->update(['name' => $name]);
            }
        }

        $order->update(['customer_id' => $customer->id]);

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
                $this->complete($order);
            }

            return $payment;
        });
    }

    private function complete(Order $order): void
    {
        // A takeaway paid before it was sent goes to the kitchen now.
        $order->items()->where('kitchen_status', KitchenStatus::Pending)->update([
            'kitchen_status' => KitchenStatus::Sent,
            'sent_at' => now(),
        ]);

        $order->update([
            'status' => OrderStatus::Completed,
            'receipt_number' => Sequence::next("receipt:{$order->branch_id}"),
            'completed_at' => now(),
        ]);
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
