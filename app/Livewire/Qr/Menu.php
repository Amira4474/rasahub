<?php

namespace App\Livewire\Qr;

use App\Enums\KitchenStatus;
use App\Enums\OrderStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantSetting;
use App\Models\TableRequest;
use App\Sales\QrOrdering;
use App\Sales\SaleException;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The customer's menu on their phone: browse, choose, send the order, and
 * follow it to the kitchen. Nobody is signed in, so every request finds the
 * business and the table again from the QR link and works inside them.
 */
class Menu extends Component
{
    #[Locked]
    public string $business;

    #[Locked]
    public string $token;

    public ?int $categoryId = null;

    /**
     * The basket: product, quantity, choices and note per line.
     *
     * @var array<string, array{product_id: int, quantity: int, modifiers: list<int>, parts: array<int, list<int>>, note: ?string}>
     */
    public array $basket = [];

    // The product being chosen.
    public ?int $pickingId = null;

    /** @var array<int, list<int>> */
    public array $pickedModifiers = [];

    /** @var array<int, list<int>> */
    public array $pickedParts = [];

    public int $pickQuantity = 1;

    public string $pickNote = '';

    public bool $basketOpen = false;

    // Optional details.
    public string $name = '';

    public string $phone = '';

    public bool $consent = false;

    public string $message = '';

    public string $messageTone = 'info';

    public function mount(string $business, string $token): void
    {
        $this->business = $business;
        $this->token = $token;

        $this->inTable(fn () => null);
    }

    public function pick(int $productId): void
    {
        $this->inTable(function (DiningTable $table) use ($productId): void {
            $product = $this->onSale($table)->firstWhere('id', $productId) ?? throw new SaleException('That is no longer on the menu.');

            if ($product->modifierGroups->isEmpty() && ! $product->isSet()) {
                $this->addToBasket($product->id, 1, [], [], null);
                $this->say("{$product->name} added.", 'success');

                return;
            }

            $this->pickingId = $product->id;
            $this->pickQuantity = 1;
            $this->pickNote = '';
            $this->pickedModifiers = $product->modifierGroups->mapWithKeys(fn ($group) => [$group->id => $group->modifiers->where('is_default', true)->pluck('id')->all()])->all();
            $this->pickedParts = $product->setGroups->mapWithKeys(fn ($group) => [$group->id => $group->items->where('is_default', true)->pluck('product_id')->all()])->all();
        });
    }

    public function toggleModifier(int $groupId, int $modifierId, int $max): void
    {
        $this->pickedModifiers[$groupId] = $this->toggle($this->pickedModifiers[$groupId] ?? [], $modifierId, $max);
    }

    public function togglePart(int $groupId, int $productId, int $max): void
    {
        $this->pickedParts[$groupId] = $this->toggle($this->pickedParts[$groupId] ?? [], $productId, $max);
    }

    public function confirmPick(): void
    {
        $this->inTable(function (DiningTable $table): void {
            $product = $this->onSale($table)->firstWhere('id', $this->pickingId) ?? throw new SaleException('That is no longer on the menu.');

            foreach ($product->modifierGroups as $group) {
                $count = count($this->pickedModifiers[$group->id] ?? []);

                if ($count < $group->min_select || $count > $group->max_select) {
                    throw new SaleException("{$group->name}: {$group->rule()}.");
                }
            }

            foreach ($product->setGroups as $group) {
                $count = count($this->pickedParts[$group->id] ?? []);

                if ($count < $group->min_select || $count > $group->max_select) {
                    throw new SaleException("{$group->name}: choose ".($group->min_select === $group->max_select ? $group->max_select : "{$group->min_select} to {$group->max_select}").'.');
                }
            }

            $this->addToBasket($product->id, max(1, min(QrOrdering::MAX_QUANTITY, $this->pickQuantity)), array_merge(...array_values($this->pickedModifiers ?: [[]])), $this->pickedParts, $this->pickNote ?: null);
            $this->say("{$product->name} added.", 'success');
            $this->pickingId = null;
        });
    }

    public function changeQuantity(string $key, int $by): void
    {
        if (! isset($this->basket[$key])) {
            return;
        }

        $quantity = $this->basket[$key]['quantity'] + $by;

        if ($quantity < 1) {
            unset($this->basket[$key]);
        } else {
            $this->basket[$key]['quantity'] = min(QrOrdering::MAX_QUANTITY, $quantity);
        }
    }

    public function send(): void
    {
        $key = 'qr-order:'.$this->token.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->say('You have sent several orders just now. Please wait a few minutes, or ask the staff.', 'error');

            return;
        }

        $this->inTable(function (DiningTable $table) use ($key): void {
            if (filled($this->phone) && strlen(preg_replace('/\D/', '', $this->phone)) < 8) {
                throw new SaleException('Enter your phone number in full, or leave it empty.');
            }

            RateLimiter::hit($key, 600);

            $order = app(QrOrdering::class)->place($table, array_values($this->basket), $this->name ?: null, $this->phone ?: null, $this->consent);

            // This phone follows the order's lines, wherever they end up.
            $mine = session("qr.{$this->token}", []);
            $mine[] = ['ulid' => $order->ulid, 'items' => OrderItem::where('order_id', $order->id)->whereNull('parent_id')->pluck('id')->all(), 'at' => now()->timestamp];
            session(["qr.{$this->token}" => array_slice($mine, -5)]);

            $this->basket = [];
            $this->basketOpen = false;
            $this->say($order->status === OrderStatus::Pending ? 'Order sent. Staff will confirm it in a moment.' : 'Order sent to the kitchen.', 'success');
        });
    }

    public function callStaff(string $type): void
    {
        $key = 'qr-call:'.$this->token.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->say('Staff have been called. They will be with you shortly.', 'info');

            return;
        }

        $this->inTable(function (DiningTable $table) use ($key, $type): void {
            RateLimiter::hit($key, 300);
            app(QrOrdering::class)->request($table, $type === TableRequest::BILL ? TableRequest::BILL : TableRequest::CALL_STAFF);
            $this->say($type === TableRequest::BILL ? 'Staff will bring your bill.' : 'Staff have been called.', 'success');
        });
    }

    public function render()
    {
        return view('livewire.qr.menu', $this->inTable(fn (DiningTable $table): array => $this->viewData($table)));
    }

    // ---------------------------------------------------------------------

    /**
     * Run the callback inside the business and for the table of the link,
     * turning a till refusal into a message for the customer.
     */
    private function inTable(Closure $callback): mixed
    {
        $business = QrOrdering::business($this->business) ?? abort(404);

        return $business->run(function () use ($business, $callback): mixed {
            $table = QrOrdering::table($this->token) ?? abort(404);

            try {
                return $callback($table, $business);
            } catch (SaleException $exception) {
                $this->say($exception->getMessage(), 'error');

                return null;
            }
        });
    }

    /**
     * @return Collection<int, Product>
     */
    private function onSale(DiningTable $table): Collection
    {
        $branchId = $table->area->branch_id;

        return Product::with([
            'branchSettings', 'category.parent',
            'modifierGroups' => fn ($q) => $q->where('is_active', true),
            'modifierGroups.modifiers' => fn ($q) => $q->where('is_active', true),
            'setGroups.items.product',
        ])
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product): bool => $product->isAvailableAt($branchId))
            ->values();
    }

    /**
     * @param  list<int>  $modifiers
     * @param  array<int, list<int>>  $parts
     */
    private function addToBasket(int $productId, int $quantity, array $modifiers, array $parts, ?string $note): void
    {
        $key = md5(json_encode([$productId, $modifiers, $parts, $note]));

        if (isset($this->basket[$key])) {
            $this->basket[$key]['quantity'] = min(QrOrdering::MAX_QUANTITY, $this->basket[$key]['quantity'] + $quantity);

            return;
        }

        if (count($this->basket) >= QrOrdering::MAX_LINES) {
            throw new SaleException('Your basket is full. Please send this order first.');
        }

        $this->basket[$key] = ['product_id' => $productId, 'quantity' => $quantity, 'modifiers' => $modifiers, 'parts' => $parts, 'note' => $note];
    }

    /**
     * @param  list<int>  $chosen
     * @return list<int>
     */
    private function toggle(array $chosen, int $id, int $max): array
    {
        if (in_array($id, $chosen, true)) {
            return array_values(array_diff($chosen, [$id]));
        }

        return $max === 1 ? [$id] : (count($chosen) >= $max ? $chosen : [...$chosen, $id]);
    }

    private function say(string $message, string $tone = 'info'): void
    {
        $this->message = $message;
        $this->messageTone = $tone;
    }

    /**
     * Everything the page shows, worked out inside the tenant, as plain
     * values: the page is drawn after this request has left the tenant.
     *
     * @return array<string, mixed>
     */
    private function viewData(DiningTable $table): array
    {
        $settings = RestaurantSetting::current();
        $branch = $table->area->branch;
        $menu = $this->onSale($table);
        $image = fn (?string $path): ?string => $path ? Storage::disk('public')->url($path) : null;
        $categories = Category::whereIn('id', $menu->pluck('category_id')->unique())->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $categoryId = $this->categoryId ?? $categories->first()?->id;

        $price = fn (Product $product): string => $product->priceAt($branch->id);

        $lines = [];
        $subtotal = Money::ZERO;

        foreach ($this->basket as $key => $line) {
            $product = $menu->firstWhere('id', $line['product_id']);

            if ($product === null) {
                unset($this->basket[$key]);

                continue;
            }

            $modifiers = $product->modifierGroups->flatMap->modifiers->whereIn('id', $line['modifiers']);
            $parts = $product->setGroups->flatMap->items->filter(fn ($item) => in_array($item->product_id, $line['parts'][$item->set_group_id] ?? [], true));
            $unit = Money::sum([$price($product), ...$modifiers->pluck('price_delta'), ...$parts->pluck('extra_price')]);
            $total = bcmul($unit, (string) $line['quantity'], 2);
            $subtotal = bcadd($subtotal, $total, 2);

            $lines[$key] = [
                'name' => $product->name,
                'quantity' => $line['quantity'],
                'details' => collect([...$modifiers->pluck('name'), ...$parts->map(fn ($p) => $p->product?->name)])->filter()->implode(', '),
                'note' => $line['note'],
                'total' => $total,
            ];
        }

        $picking = $this->pickingId ? $menu->firstWhere('id', $this->pickingId) : null;

        return [
            'design' => [
                'title' => $settings->qr_heading ?: app(TenantContext::class)->business()->name,
                'welcome' => $settings->qr_welcome_text,
                'footer' => $settings->qr_footer_text,
                'logo' => $image($settings->qr_logo_path),
                'banner' => $image($settings->qr_banner_path),
                'primary' => $settings->qr_primary_color,
                'background' => $settings->qr_background_color,
                'photos' => $settings->qr_show_photos,
                'descriptions' => $settings->qr_show_descriptions,
            ],
            'currency' => app(TenantContext::class)->business()->currency,
            'tableName' => $table->name,
            'branchName' => $branch->name,
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'categoryId' => $categoryId,
            'products' => $menu->where('category_id', $categoryId)->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'price' => $price($p),
                'image' => $image($p->image_path),
                'options' => $p->isSet() || $p->modifierGroups->isNotEmpty(),
            ])->values()->all(),
            'picking' => $picking ? [
                'name' => $picking->name,
                'price' => $price($picking),
                'description' => $picking->description,
                'image' => $image($picking->image_path),
                'modifierGroups' => $picking->modifierGroups->map(fn ($g) => ['kind' => 'modifier', 'id' => $g->id, 'name' => $g->name, 'rule' => $g->rule(), 'max' => $g->max_select, 'options' => $g->modifiers->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'delta' => $m->price_delta])->values()->all()])->values()->all(),
                'setGroups' => $picking->setGroups->map(fn ($g) => ['kind' => 'set', 'id' => $g->id, 'name' => $g->name, 'max' => $g->max_select, 'rule' => $g->min_select === $g->max_select ? "Choose {$g->max_select}" : "Choose {$g->min_select} to {$g->max_select}", 'options' => $g->items->filter(fn ($i) => $i->product?->is_active)->map(fn ($i) => ['id' => $i->product_id, 'name' => $i->product->name, 'extra' => $i->extra_price])->values()->all()])->values()->all(),
            ] : null,
            'lines' => $lines,
            'estimate' => app(QrOrdering::class)->estimate($branch, $subtotal),
            'count' => array_sum(array_column($this->basket, 'quantity')),
            'myOrders' => $this->myOrders(),
        ];
    }

    /**
     * Where this phone's orders are, newest first.
     *
     * @return list<array{label: string, status: string, tone: string, lines: list<string>}>
     */
    private function myOrders(): array
    {
        $statuses = [];

        foreach (array_reverse(session("qr.{$this->token}", [])) as $mine) {
            $order = Order::where('ulid', $mine['ulid'])->first();

            if ($order === null) {
                continue;
            }

            $items = OrderItem::whereKey($mine['items'])->get();
            $kitchen = $items->pluck('kitchen_status');

            [$status, $tone] = match (true) {
                $order->status === OrderStatus::Pending => ['Waiting for staff to confirm', 'info'],
                $order->reject_reason !== null => ['Not accepted: '.$order->reject_reason, 'error'],
                $kitchen->isNotEmpty() && $kitchen->every(fn ($s) => in_array($s, [KitchenStatus::Served, KitchenStatus::Voided], true)) => ['Served. Enjoy!', 'success'],
                $kitchen->isNotEmpty() && $kitchen->every(fn ($s) => in_array($s, [KitchenStatus::Ready, KitchenStatus::Served, KitchenStatus::Voided], true)) => ['Ready', 'success'],
                $kitchen->contains(KitchenStatus::Preparing) || $kitchen->contains(KitchenStatus::Ready) => ['Being prepared', 'info'],
                default => ['Sent to the kitchen', 'info'],
            };

            $statuses[] = [
                'label' => date('H:i', $mine['at']),
                'status' => $status,
                'tone' => $tone,
                'lines' => $items->map(fn (OrderItem $item) => "{$item->quantity} × {$item->name}")->all(),
            ];
        }

        return $statuses;
    }
}
