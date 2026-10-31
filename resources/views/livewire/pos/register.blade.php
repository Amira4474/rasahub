@php
    use App\Enums\Permission;
    use App\Enums\PaymentMethodType;

    $order = $this->order;
    $shift = $this->shift;
    $mayPay = $this->allowed(Permission::TakePayments);
    $mayRunShift = $this->allowed(Permission::ManageShifts);
    $mayDiscount = $this->allowed(Permission::ApplyDiscounts);
    $button = 'rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-4';
    $primary = $button.' bg-chili-500 text-white hover:bg-chili-600 focus:ring-chili-500/30 disabled:opacity-40';
    $secondary = $button.' bg-white/10 text-cream-50 hover:bg-white/20 focus:ring-white/20';
    $light = $button.' bg-cream-200 text-ink-900 hover:bg-cream-300 focus:ring-ink-900/10';
    $input = 'block w-full rounded-xl border border-cream-300 bg-white px-4 py-2.5 text-ink-900 focus:border-chili-500 focus:outline-none focus:ring-4 focus:ring-chili-500/20';
@endphp

<div
    class="flex min-h-0 flex-1 flex-col"
    x-data
    x-on:print-receipt.window="window.open($event.detail.url, 'receipt', 'width=420,height=640')"
>
    {{-- Top bar --}}
    <header class="border-b border-white/10">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <x-marketing.logo class="text-white" />
                <span class="truncate rounded-full bg-white/10 px-3 py-1 text-sm font-semibold text-cream-100">
                    {{ $this->branch->name }} · {{ $this->terminal->name }}
                </span>
                {{-- A running clock in the branch's own time. wire:ignore keeps
                     Livewire's updates from resetting it. --}}
                <time
                    wire:ignore
                    x-data="{
                        now: '',
                        tick() {
                            this.now = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: @js($this->branch->effectiveTimezone()) }).format(new Date());
                        },
                    }"
                    x-init="tick(); setInterval(() => tick(), 1000)"
                    x-text="now"
                    aria-label="Current time"
                    class="rounded-full bg-white/10 px-3 py-1 font-mono text-sm font-semibold tabular-nums text-cream-50"
                ></time>
                {{-- The shift, the cash drawer and the terminal are all behind
                     this one button. --}}
                @if ($shift)
                    <button type="button" wire:click="$set('shiftPanel', true)" class="rounded-full bg-pandan-500/25 px-3 py-1 text-xs font-semibold text-pandan-100 transition hover:bg-pandan-500/40">
                        Shift open since {{ $shift->opened_at->timezone($this->branch->effectiveTimezone())->format('H:i') }} ▾
                    </button>
                @elseif ($mayRunShift)
                    <button type="button" wire:click="$set('shiftPanel', true)" class="{{ $primary }} py-1.5">Open shift</button>
                @else
                    <button type="button" wire:click="$set('shiftPanel', true)" class="rounded-full bg-saffron-400/20 px-3 py-1 text-xs font-semibold text-saffron-400 transition hover:bg-saffron-400/30">No shift open ▾</button>
                @endif
            </div>

            <div class="flex items-center gap-2 text-sm text-cream-100">
                <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                <nav class="flex gap-1 rounded-xl bg-white/5 p-1" aria-label="POS sections">
                    {{-- Icons only; the name is the button's label and its tooltip. --}}
                    @foreach (array_filter([
                        'sell' => $this->allowed(Permission::TakeOrders) ? ['Sell', 'heroicon-o-shopping-cart'] : null,
                        'transactions' => $this->allowed(Permission::TakeOrders) ? ['Transactions', 'heroicon-o-receipt-percent'] : null,
                        'kitchen' => $this->allowed(Permission::UseKitchenDisplay) ? ['Kitchen display', 'heroicon-o-fire'] : null,
                    ]) as $key => [$label, $icon])
                        <button type="button" wire:click="showScreen('{{ $key }}')" aria-label="{{ $label }}" title="{{ $label }}" @class(['grid size-10 place-items-center rounded-lg transition focus:outline-none focus:ring-2 focus:ring-white/40', 'bg-chili-500 text-white' => $screen === $key, 'text-cream-100 hover:bg-white/10' => $screen !== $key]) @if ($screen === $key) aria-current="page" @endif>
                            @svg($icon, 'size-6')
                        </button>
                    @endforeach
                </nav>
                {{-- A platform user in Super Impose leaves through the banner. --}}
                @unless (app(\App\Tenancy\SuperImpose::class)->isActive())
                    <form method="POST" action="{{ route('pos.logout') }}">
                        @csrf
                        <button type="submit" class="{{ $button }} bg-white text-ink-900 hover:bg-cream-200 focus:ring-white/30">Sign out</button>
                    </form>
                @endunless
            </div>
        </div>
    </header>

    {{-- Message: a centred alert. Good news closes by itself after a few
         seconds; a problem stays until it is acknowledged. --}}
    @if ($message !== '')
        @php($autoClose = in_array($messageTone, ['success', 'info'], true))
        <div
            wire:key="message-{{ md5($message.microtime()) }}"
            x-data
            @if ($autoClose) x-init="setTimeout(() => $wire.set('message', ''), 2500)" @endif
            x-on:keydown.escape.window="$wire.set('message', '')"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-ink-900/50 p-4"
            wire:click.self="$set('message', '')"
        >
            <div role="alertdialog" aria-modal="true" aria-labelledby="alert-message" class="w-full max-w-sm rounded-3xl bg-cream-50 px-8 py-8 text-center text-ink-900 shadow-2xl">
                <div @class([
                    'mx-auto flex size-20 items-center justify-center rounded-full border-4',
                    'border-pandan-500 text-pandan-500' => $messageTone === 'success',
                    'border-chili-500 text-chili-500' => $messageTone === 'error',
                    'border-saffron-400 text-saffron-500' => $messageTone === 'warning',
                    'border-ink-500 text-ink-500' => $messageTone === 'info',
                ]) aria-hidden="true">
                    @switch($messageTone)
                        @case('success') @svg('heroicon-o-check', 'size-10') @break
                        @case('error') @svg('heroicon-o-x-mark', 'size-10') @break
                        @case('warning') @svg('heroicon-o-exclamation-triangle', 'size-10') @break
                        @default @svg('heroicon-o-information-circle', 'size-10')
                    @endswitch
                </div>
                <p class="mt-5 font-display text-xl font-bold">
                    @switch($messageTone)
                        @case('success') Done @break
                        @case('error') Not done @break
                        @case('warning') Please check @break
                        @default Note
                    @endswitch
                </p>
                <p id="alert-message" class="mt-2 text-ink-700">{{ $message }}</p>
                <button type="button" wire:click="$set('message', '')" class="{{ $primary }} mt-6 w-full" autofocus>OK</button>
                @if ($autoClose)
                    <div class="mt-4 h-1 overflow-hidden rounded-full bg-cream-200" aria-hidden="true">
                        <div class="h-full rounded-full bg-pandan-500" style="animation: pos-alert-countdown 2.5s linear forwards;"></div>
                    </div>
                @endif
            </div>
        </div>
        <style>@keyframes pos-alert-countdown { from { width: 100%; } to { width: 0%; } }</style>
    @endif

    <div @class(["grid min-h-0 flex-1 grid-rows-[minmax(0,1fr)] gap-3 p-3", "md:grid-cols-[minmax(0,1fr)_22rem] xl:grid-cols-[minmax(0,1fr)_26rem]" => $screen !== "kitchen"])>
        @if ($screen === 'transactions')
            @include('livewire.pos.partials.transactions')
        @elseif ($screen === 'kitchen')
            @include('livewire.pos.partials.kitchen')
        @else
        {{-- Left: tables, menu, orders --}}
        <section class="flex min-h-0 flex-col rounded-2xl bg-white/5">
            <nav class="flex gap-1 border-b border-white/10 p-2" aria-label="POS views">
                @foreach (['tables' => 'Tables', 'menu' => 'Menu', 'orders' => 'Open orders ('.$this->openOrders->count().')'] as $key => $label)
                    <button type="button" wire:click="$set('tab', '{{ $key }}')" @class(['rounded-lg px-4 py-2 text-sm font-semibold transition', 'bg-cream-50 text-ink-900' => $tab === $key, 'text-cream-100 hover:bg-white/10' => $tab !== $key]) @if ($tab === $key) aria-current="page" @endif>
                        {{ $label }}
                    </button>
                @endforeach
                @if ($moving)
                    <span class="ml-auto self-center text-sm font-semibold text-saffron-400">Choose a table to move to</span>
                    <button type="button" wire:click="$set('moving', false)" class="{{ $secondary }}">Cancel move</button>
                @endif
            </nav>

            <div class="min-h-0 flex-1 overflow-y-auto p-4">
                @if ($tab === 'tables')
                    @forelse ($this->tables as $area)
                        <h2 class="mb-2 text-sm font-semibold uppercase tracking-[0.15em] text-saffron-400">{{ $area->name }}</h2>
                        <div class="relative mb-6 w-full overflow-hidden rounded-xl border border-white/10 bg-white/5" style="aspect-ratio: 16 / 10;">
                            @foreach ($area->tables as $diningTable)
                                @php($open = $this->openOrders->firstWhere('dining_table_id', $diningTable->id))
                                <button
                                    type="button"
                                    wire:key="table-{{ $diningTable->id }}"
                                    wire:click="selectTable({{ $diningTable->id }})"
                                    @class([
                                        'absolute flex flex-col items-center justify-center border-2 text-center leading-tight transition hover:scale-105 focus:outline-none focus:ring-4 focus:ring-white/40',
                                        'rounded-full' => $diningTable->shape->value === 'round',
                                        'rounded-lg' => $diningTable->shape->value !== 'round',
                                        'border-pandan-500 bg-pandan-500/20 text-cream-50' => ! $open,
                                        'border-chili-400 bg-chili-500/80 text-white' => $open && $open->balance() !== '0.00' && $open->paid === '0.00',
                                        'border-saffron-400 bg-saffron-400/80 text-ink-900' => $open && $open->paid !== '0.00',
                                        'ring-4 ring-white' => $order && $open && $open->is($order),
                                    ])
                                    style="left: {{ $diningTable->pos_x }}%; top: {{ $diningTable->pos_y }}%; width: {{ $diningTable->shape->value === 'rectangle' ? 14 : 8.5 }}%; aspect-ratio: {{ $diningTable->shape->value === 'rectangle' ? '2 / 1' : '1 / 1' }};"
                                >
                                    <span class="text-sm font-bold">{{ $diningTable->name }}</span>
                                    <span class="text-[0.65rem] opacity-80">{{ $open ? $open->number_label : $diningTable->seats.' seats' }}</span>
                                </button>
                            @endforeach
                        </div>
                    @empty
                        <p class="text-cream-200/70">This branch has no tables set up. They are added under Floor plans in the admin. Dine-in orders can still be taken from the Menu tab.</p>
                    @endforelse
                    <p class="text-xs text-cream-200/60">Green: free. Red: occupied. Amber: partly paid.</p>
                @elseif ($tab === 'orders')
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @forelse ($this->openOrders as $open)
                            <button type="button" wire:key="open-{{ $open->id }}" wire:click="showOrder({{ $open->id }})" class="rounded-xl bg-cream-50 p-4 text-left transition hover:bg-cream-100">
                                <span class="flex items-baseline justify-between gap-3">
                                    <span class="font-display text-lg font-bold text-ink-900">{{ $open->number_label }}</span>
                                    <span class="font-semibold text-ink-900">{{ $this->money($open->total) }}</span>
                                </span>
                                <span class="mt-1 block text-sm text-ink-500">{{ $open->placeLabel() }} · {{ $open->status->label() }} · {{ $open->payment_status->label() }}</span>
                            </button>
                        @empty
                            <p class="text-cream-200/70">No open orders.</p>
                        @endforelse
                    </div>
                @else
                    @if ($this->categories->isEmpty())
                        <p class="text-cream-200/70">Nothing is on sale at this branch yet. Products are added under Menu in the admin.</p>
                    @else
                        <div class="mb-4 flex flex-wrap gap-2">
                            @foreach ($this->categories as $category)
                                @php($active = ($categoryId ?? $this->categories->first()->id) === $category->id)
                                <button type="button" wire:key="cat-{{ $category->id }}" wire:click="$set('categoryId', {{ $category->id }})" @class(['rounded-full border-2 px-4 py-2 text-sm font-semibold transition', 'bg-cream-50 text-ink-900' => $active, 'text-cream-100 hover:bg-white/10' => ! $active]) style="border-color: {{ $category->colour ?? '#f1e4d0' }};">
                                    {{ $category->name }}
                                </button>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-3">
                            @foreach ($this->products as $product)
                                <button
                                    type="button"
                                    wire:key="product-{{ $product->id }}"
                                    wire:click="pick({{ $product->id }})"
                                    class="flex flex-col overflow-hidden rounded-xl border-b-4 bg-cream-50 text-left transition hover:bg-cream-100 focus:outline-none focus:ring-4 focus:ring-white/40"
                                    style="border-bottom-color: {{ $product->category?->colour ?? '#e3d2b9' }};"
                                >
                                    @if ($product->image_path)
                                        <img src="{{ $this->imageUrl($product->image_path) }}" alt="" loading="lazy" class="aspect-[4/3] w-full object-cover">
                                    @else
                                        {{-- No photo: the category colour and the initial instead. --}}
                                        <span class="flex aspect-[4/3] w-full items-center justify-center font-display text-3xl font-bold text-white/90" style="background-color: {{ $product->category?->colour ?? '#7b6d63' }};" aria-hidden="true">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                                    @endif
                                    <span class="px-3 pt-2 font-semibold leading-tight text-ink-900">{{ $product->name }}</span>
                                    <span class="mt-auto flex items-center justify-between px-3 pb-2 pt-1 text-sm text-ink-500">
                                        <span>{{ $this->money($product->priceAt($this->branch->id)) }}</span>
                                        @if ($product->isSet())
                                            <span class="rounded-full bg-saffron-100 px-2 text-xs font-semibold text-ink-900">Set</span>
                                        @elseif ($product->modifierGroups->isNotEmpty())
                                            <span class="text-xs">+ options</span>
                                        @endif
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </section>

        @endif

        @if ($screen !== 'kitchen')
        {{-- Right: the order --}}
        <aside class="flex min-h-0 flex-col rounded-2xl bg-cream-50 text-ink-900">
            @if (! $order)
                <div class="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
                    <p class="font-display text-xl font-bold">No order open</p>
                    <p class="text-sm text-ink-500">Choose a table for dine-in, or start a takeaway.</p>
                    <button type="button" wire:click="startTakeaway" class="{{ $primary }}">New takeaway</button>
                    @if ($this->tables->isEmpty())
                        <button type="button" wire:click="startDineIn" class="{{ $light }}">New dine-in</button>
                    @endif
                </div>
            @else
                <div class="border-b border-cream-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-display text-2xl font-bold">{{ $order->number_label }}</p>
                            <p class="text-sm text-ink-500">{{ $order->placeLabel() }}@if ($order->guests) · {{ $order->guests }} guests @endif · {{ $order->status->label() }}</p>
                        </div>
                        <button type="button" wire:click="closeOrder" class="{{ $light }}" aria-label="Put the order aside">Done</button>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2 text-sm">
                        @if ($order->customer)
                            <span class="rounded-full bg-cream-200 px-3 py-1">{{ $order->customer->displayName() }} · {{ $order->customer->phone }}</span>
                            <button type="button" wire:click="detachCustomer" class="text-chili-600 underline underline-offset-4">Remove</button>
                        @else
                            <button type="button" wire:click="$set('choosingCustomer', true)" class="text-chili-600 underline underline-offset-4">Add customer</button>
                        @endif
                        @if ($this->tables->isNotEmpty())
                            <button type="button" wire:click="startMoving" class="ml-auto text-chili-600 underline underline-offset-4">Move or merge table</button>
                        @endif
                    </div>
                </div>

                <ul class="min-h-0 flex-1 divide-y divide-cream-200 overflow-y-auto px-4">
                    @forelse ($order->lines as $line)
                        <li wire:key="line-{{ $line->id }}" @class(['py-3', 'opacity-60' => $line->isVoided()])>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p @class(['font-semibold', 'line-through' => $line->isVoided()])>{{ $line->quantity }} × {{ $line->name }}</p>
                                    @foreach ($line->modifiers as $modifier)
                                        <p class="text-sm text-ink-500">{{ $modifier->name }}@if ($modifier->price_delta !== '0.00') ({{ $modifier->price_delta > 0 ? '+' : '' }}{{ $modifier->price_delta }})@endif</p>
                                    @endforeach
                                    @foreach ($line->parts as $part)
                                        <p class="text-sm text-ink-500">– {{ $part->name }}@if ($part->unit_price !== '0.00') (+{{ $part->unit_price }})@endif</p>
                                    @endforeach
                                    @if ($line->note)
                                        <p class="text-sm italic text-ink-500">“{{ $line->note }}”</p>
                                    @endif
                                    @if ($line->isVoided())
                                        <p class="text-xs font-semibold uppercase tracking-wider text-chili-600">Void · {{ $line->void_reason }}</p>
                                    @else
                                        <p class="text-xs font-semibold uppercase tracking-wider {{ $line->isSent() ? 'text-pandan-600' : 'text-saffron-500' }}">{{ $line->isSent() ? 'Sent' : 'Not sent' }}@if ($line->refunded_quantity) · {{ $line->refunded_quantity }} refunded @endif</p>
                                    @endif
                                    @foreach ($line->discounts as $lineDiscount)
                                        <p class="flex items-center gap-2 text-sm text-pandan-600">
                                            {{ $lineDiscount->label() }} −{{ $lineDiscount->amount }}
                                            @if ($order->isOpen() && $mayDiscount)
                                                <button type="button" wire:click="removeDiscount({{ $lineDiscount->id }})" class="text-chili-600" aria-label="Remove {{ $lineDiscount->name }}">×</button>
                                            @endif
                                        </p>
                                    @endforeach
                                </div>
                                <div class="shrink-0 text-right">
                                    <p @class(['font-semibold', 'line-through' => $line->isVoided()])>{{ number_format((float) $line->grossTotal(), 2) }}</p>
                                    @if ($order->isOpen() && ! $line->isVoided())
                                        <div class="mt-1 flex items-center justify-end gap-1">
                                            @if ($mayDiscount)
                                                <button type="button" wire:click="openDiscount({{ $line->id }})" class="grid size-8 place-items-center rounded-lg bg-cream-200" aria-label="Discount {{ $line->name }}" title="Discount">@svg('heroicon-o-tag', 'size-4')</button>
                                            @endif
                                            @if (! $line->isSent())
                                                <button type="button" wire:click="changeQuantity({{ $line->id }}, -1)" class="size-8 rounded-lg bg-cream-200 font-bold" aria-label="One fewer {{ $line->name }}">−</button>
                                                <button type="button" wire:click="changeQuantity({{ $line->id }}, 1)" class="size-8 rounded-lg bg-cream-200 font-bold" aria-label="One more {{ $line->name }}">+</button>
                                                <button type="button" wire:click="removeItem({{ $line->id }})" class="size-8 rounded-lg bg-chili-100 font-bold text-chili-600" aria-label="Remove {{ $line->name }}">×</button>
                                            @else
                                                <button type="button" wire:click="openVoid({{ $line->id }})" class="rounded-lg bg-chili-100 px-2 py-1.5 text-xs font-semibold text-chili-600" aria-label="Void {{ $line->name }}">Void</button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="py-8 text-center text-sm text-ink-500">Tap products on the menu to add them.</li>
                    @endforelse
                </ul>

                <div class="border-t border-cream-200 p-4">
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ $this->money($order->subtotal) }}</dd></div>
                        @foreach ($order->discounts->whereNull('order_item_id') as $orderDiscount)
                            <div class="flex justify-between text-pandan-600">
                                <dt class="flex items-center gap-2">
                                    {{ $orderDiscount->label() }}
                                    @if ($order->isOpen() && $mayDiscount)
                                        <button type="button" wire:click="removeDiscount({{ $orderDiscount->id }})" class="text-chili-600" aria-label="Remove {{ $orderDiscount->name }}">×</button>
                                    @endif
                                </dt>
                                <dd>−{{ $this->money($orderDiscount->amount) }}</dd>
                            </div>
                        @endforeach
                        @if ($order->discount_total !== '0.00' && $order->discounts->whereNotNull('order_item_id')->isNotEmpty())
                            <div class="flex justify-between text-pandan-600"><dt>Item discounts</dt><dd>−{{ $this->money($order->discounts->whereNotNull('order_item_id')->sum('amount')) }}</dd></div>
                        @endif
                        @if ($order->service_charge !== '0.00')
                            <div class="flex justify-between"><dt>Service charge {{ rtrim(rtrim($order->service_charge_rate, '0'), '.') }}%</dt><dd>{{ $this->money($order->service_charge) }}</dd></div>
                        @endif
                        @if ($order->sst_enabled)
                            <div class="flex justify-between text-ink-500"><dt>SST {{ rtrim(rtrim($order->sst_rate, '0'), '.') }}%{{ $order->prices_include_sst ? ' (included)' : '' }}</dt><dd>{{ $this->money($order->sst) }}</dd></div>
                        @endif
                        @if ($order->rounding !== '0.00')
                            <div class="flex justify-between text-ink-500"><dt>Rounding</dt><dd>{{ $order->rounding }}</dd></div>
                        @endif
                        <div class="flex justify-between pt-1 font-display text-xl font-bold"><dt>Total</dt><dd>{{ $this->money($order->total) }}</dd></div>
                        @foreach ($order->payments as $payment)
                            <div class="flex justify-between text-pandan-600"><dt>Paid · {{ $payment->method_name }}</dt><dd>{{ $this->money($payment->amount) }}</dd></div>
                        @endforeach
                        @if ($order->paid !== '0.00' && $order->isOpen())
                            <div class="flex justify-between font-semibold"><dt>Balance</dt><dd>{{ $this->money($order->balance()) }}</dd></div>
                        @endif
                        @foreach ($order->refunds as $refund)
                            <div class="flex justify-between text-chili-600">
                                <dt>Refunded · {{ $refund->number_label }} <a href="{{ route('pos.refund-slip', $refund->ulid) }}" target="receipt" class="underline underline-offset-2">slip</a></dt>
                                <dd>−{{ $this->money($refund->amount) }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @if ($order->isOpen())
                        {{-- Kept to two rows so the item list keeps its room. --}}
                        <div class="mt-3 grid gap-2">
                            <div @class(['grid gap-2', 'grid-cols-3' => $mayDiscount, 'grid-cols-2' => ! $mayDiscount])>
                                <button type="button" wire:click="sendToKitchen" class="{{ $light }} px-2">Send</button>
                                @if ($mayDiscount)
                                    <button type="button" wire:click="openDiscount('order')" @disabled($order->lines->isEmpty()) class="{{ $light }} px-2">Discount</button>
                                @endif
                                <button type="button" wire:click="openCancel" class="{{ $light }} px-2">Cancel</button>
                            </div>
                            @if ($mayPay)
                                <button type="button" wire:click="openPayment" @disabled($order->lines->isEmpty()) class="{{ $primary }} flex items-center justify-center gap-2 py-3 text-base">
                                    @svg('heroicon-o-banknotes', 'size-5')
                                    Pay {{ $this->money($order->balance()) }}
                                </button>
                            @else
                                <p class="text-center text-sm text-ink-500">A cashier takes payment.</p>
                            @endif
                        </div>
                    @else
                        <div class="mt-4 grid gap-2">
                            @if ($order->status === \App\Enums\OrderStatus::Cancelled)
                                <p class="text-center font-semibold text-chili-600">Cancelled · {{ $order->cancel_reason }}</p>
                            @else
                                <p class="text-center font-semibold text-pandan-600">{{ $order->payment_status->label() }} · Receipt {{ $order->receiptLabel() }}</p>
                                <a href="{{ route('pos.receipt', $order->ulid) }}" target="receipt" class="{{ $light }} text-center">Print receipt</a>
                                @if (\App\Support\Money::isPositive($order->refundable()))
                                    <button type="button" wire:click="openRefund" class="{{ $light }}">Refund</button>
                                @endif
                            @endif
                            <button type="button" wire:click="closeOrder" class="{{ $primary }}">Next order</button>
                        </div>
                    @endif
                </div>
            @endif
        </aside>
        @endif
    </div>

    {{-- Seating at a table --}}
    @if ($seatingTableId !== null)
        <x-pos.modal heading="New order" wire:click.self="$set('seatingTableId', null)">
            <form wire:submit="seat" class="space-y-4">
                <div>
                    <label for="guests" class="block text-sm font-semibold">Guests</label>
                    <input id="guests" type="number" min="1" max="99" wire:model="guests" class="{{ $input }} mt-1">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('seatingTableId', null)" class="{{ $light }}">Cancel</button>
                    <button type="submit" class="{{ $primary }}">Start order</button>
                </div>
            </form>
        </x-pos.modal>
    @endif

    {{-- Choosing modifiers or set meal parts --}}
    @if ($this->picking)
        @php($picking = $this->picking)
        <x-pos.modal :heading="$picking->name">
            <form wire:submit="confirmPick" class="space-y-5">
                @foreach ($picking->setGroups as $group)
                    <fieldset wire:key="set-group-{{ $group->id }}">
                        <legend class="font-semibold">{{ $group->name }} <span class="text-sm font-normal text-ink-500">choose {{ $group->min_select === $group->max_select ? $group->max_select : $group->min_select.' to '.$group->max_select }}</span></legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($group->items as $setItem)
                                @php($on = in_array($setItem->product_id, $pickedParts[$group->id] ?? [], true))
                                <button type="button" wire:key="part-{{ $setItem->id }}" wire:click="togglePart({{ $group->id }}, {{ $setItem->product_id }})" aria-pressed="{{ $on ? 'true' : 'false' }}" @class(['rounded-xl border-2 px-3 py-2 text-sm font-semibold', 'border-chili-500 bg-chili-500 text-white' => $on, 'border-cream-300' => ! $on])>
                                    {{ $setItem->product?->name }}@if ($setItem->extra_price !== '0.00') +{{ $setItem->extra_price }}@endif
                                </button>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                @foreach ($picking->modifierGroups as $group)
                    <fieldset wire:key="mod-group-{{ $group->id }}">
                        <legend class="font-semibold">{{ $group->name }} <span class="text-sm font-normal text-ink-500">{{ $group->rule() }}</span></legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($group->modifiers as $modifier)
                                @php($on = in_array($modifier->id, $pickedModifiers[$group->id] ?? [], true))
                                <button type="button" wire:key="mod-{{ $modifier->id }}" wire:click="toggleModifier({{ $group->id }}, {{ $modifier->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}" @class(['rounded-xl border-2 px-3 py-2 text-sm font-semibold', 'border-chili-500 bg-chili-500 text-white' => $on, 'border-cream-300' => ! $on])>
                                    {{ $modifier->name }}@if ($modifier->price_delta !== '0.00') {{ $modifier->price_delta > 0 ? '+' : '' }}{{ $modifier->price_delta }}@endif
                                </button>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <div class="grid grid-cols-[7rem_1fr] gap-3">
                    <div>
                        <label for="pick-quantity" class="block text-sm font-semibold">Quantity</label>
                        <input id="pick-quantity" type="number" min="1" max="999" wire:model="pickQuantity" class="{{ $input }} mt-1">
                    </div>
                    <div>
                        <label for="pick-note" class="block text-sm font-semibold">Note for the kitchen</label>
                        <input id="pick-note" type="text" maxlength="200" wire:model="pickNote" placeholder="e.g. less spicy" class="{{ $input }} mt-1">
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('pickingProductId', null)" class="{{ $light }}">Cancel</button>
                    <button type="submit" class="{{ $primary }}">Add to order</button>
                </div>
            </form>
        </x-pos.modal>
    @endif

    {{-- Customer --}}
    @if ($choosingCustomer && $order)
        <x-pos.modal heading="Add customer">
            <form wire:submit="attachCustomer" class="space-y-4">
                <div>
                    <label for="customer-phone" class="block text-sm font-semibold">Phone number</label>
                    <input id="customer-phone" type="tel" wire:model="customerPhone" required autofocus class="{{ $input }} mt-1">
                </div>
                <div>
                    <label for="customer-name" class="block text-sm font-semibold">Name <span class="font-normal text-ink-500">(optional)</span></label>
                    <input id="customer-name" type="text" maxlength="255" wire:model="customerName" class="{{ $input }} mt-1">
                </div>
                <p class="text-sm text-ink-500">A returning customer is found by their number.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('choosingCustomer', false)" class="{{ $light }}">Cancel</button>
                    <button type="submit" class="{{ $primary }}">Add</button>
                </div>
            </form>
        </x-pos.modal>
    @endif

    {{-- Payment: what is owed, the ways to pay, and the details for the
         way chosen. --}}
    @if (($paying || $this->payingWith) && $order)
        @php($method = $this->payingWith)
        <x-pos.modal :heading="'Pay order '.$order->number_label">
            <dl class="space-y-1 rounded-xl bg-cream-100 p-4 text-sm">
                <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ $this->money($order->subtotal) }}</dd></div>
                @if ($order->discount_total !== '0.00')
                    <div class="flex justify-between text-pandan-600"><dt>Discounts</dt><dd>−{{ $this->money($order->discount_total) }}</dd></div>
                @endif
                @if ($order->service_charge !== '0.00')
                    <div class="flex justify-between"><dt>Service charge {{ rtrim(rtrim($order->service_charge_rate, '0'), '.') }}%</dt><dd>{{ $this->money($order->service_charge) }}</dd></div>
                @endif
                @if ($order->sst_enabled)
                    <div class="flex justify-between text-ink-500"><dt>SST {{ rtrim(rtrim($order->sst_rate, '0'), '.') }}%{{ $order->prices_include_sst ? ' (included)' : '' }}</dt><dd>{{ $this->money($order->sst) }}</dd></div>
                @endif
                @if ($order->rounding !== '0.00')
                    <div class="flex justify-between text-ink-500"><dt>Rounding</dt><dd>{{ $order->rounding }}</dd></div>
                @endif
                <div class="flex justify-between font-semibold"><dt>Total</dt><dd>{{ $this->money($order->total) }}</dd></div>
                @foreach ($order->payments as $payment)
                    <div class="flex justify-between text-pandan-600"><dt>Paid · {{ $payment->method_name }}</dt><dd>{{ $this->money($payment->amount) }}</dd></div>
                @endforeach
                <div class="flex justify-between border-t border-cream-300 pt-2 font-display text-2xl font-bold"><dt>To pay</dt><dd>{{ $this->money($order->balance()) }}</dd></div>
            </dl>

            <p class="mt-4 text-sm font-semibold">Payment method</p>
            <div class="mt-2 grid grid-cols-2 gap-2" role="radiogroup" aria-label="Payment method">
                @foreach ($this->methods as $option)
                    <button
                        type="button"
                        role="radio"
                        aria-checked="{{ $method?->is($option) ? 'true' : 'false' }}"
                        wire:key="method-{{ $option->id }}"
                        wire:click="startPayment({{ $option->id }})"
                        @class([
                            'flex items-center gap-2 rounded-xl border-2 px-3 py-3 text-left text-sm font-semibold transition',
                            'border-chili-500 bg-chili-500 text-white' => $method?->is($option),
                            'border-cream-300 hover:border-ink-500' => ! $method?->is($option),
                        ])
                    >
                        @svg(match ($option->type) {
                            PaymentMethodType::Cash => 'heroicon-o-banknotes',
                            PaymentMethodType::Card => 'heroicon-o-credit-card',
                            PaymentMethodType::Qr => 'heroicon-o-qr-code',
                            default => 'heroicon-o-ellipsis-horizontal-circle',
                        }, 'size-5 shrink-0')
                        {{ $option->name }}
                    </button>
                @endforeach
            </div>

            @if ($method)
                <form wire:submit="takePayment" class="mt-4 space-y-4">
                    @if ($method->type === PaymentMethodType::Qr)
                        @if ($url = $this->qrImageUrl($method))
                            <img src="{{ $url }}" alt="{{ $method->name }} QR code for the customer to scan" class="mx-auto max-h-60 rounded-xl border border-cream-200 bg-white p-2">
                            <p class="text-center text-sm text-ink-500">Ask the customer to scan the code, then confirm once the payment shows on your phone.</p>
                        @else
                            <p class="rounded-xl bg-saffron-100 p-3 text-sm">No QR code image has been added for {{ $method->name }}. The owner adds it under Payment methods in the admin.</p>
                        @endif
                    @endif

                    <div>
                        <label for="pay-amount" class="block text-sm font-semibold">{{ $method->isCash() ? 'Cash received' : 'Amount' }}</label>
                        <input id="pay-amount" type="text" inputmode="decimal" wire:model="payAmount" required class="{{ $input }} mt-1 text-2xl font-bold">
                        @if ($method->isCash())
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach (['10', '20', '50', '100'] as $note)
                                    <button type="button" wire:click="$set('payAmount', '{{ $note }}.00')" class="{{ $light }}">{{ $note }}</button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if (! $method->isCash())
                        <div>
                            <label for="pay-reference" class="block text-sm font-semibold">Reference @unless ($method->requires_reference)<span class="font-normal text-ink-500">(optional)</span>@endunless</label>
                            <input id="pay-reference" type="text" maxlength="64" wire:model="payReference" @required($method->requires_reference) placeholder="Approval code or transaction ID" class="{{ $input }} mt-1">
                        </div>
                    @endif

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closePayment" class="{{ $light }}">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" class="{{ $primary }}">Take payment</button>
                    </div>
                </form>
            @else
                <div class="mt-4 flex justify-end">
                    <button type="button" wire:click="closePayment" class="{{ $light }}">Cancel</button>
                </div>
            @endif
        </x-pos.modal>
    @endif

    {{-- Corrections. Each asks for a supervisor's PIN in place when the
         user may not do it alone. --}}
    @php($pinField = '<div class="rounded-xl bg-saffron-100 p-3"><label for="approval-pin" class="block text-sm font-semibold">Supervisor PIN</label><input id="approval-pin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" wire:model="approvalPin" autofocus required class="'.$input.' mt-1 text-center text-2xl tracking-[0.5em]"><p class="mt-1 text-xs text-ink-500">A Supervisor, Manager or Owner approves with their PIN.</p></div>')

    @if ($discounting !== null && $order)
        @php($forLine = is_int($discounting) ? $order->lines->firstWhere('id', $discounting) : null)
        <x-pos.modal :heading="$forLine ? 'Discount '.$forLine->name : 'Discount order '.$order->number_label">
            @php($presets = $this->presets->where('scope', $forLine ? \App\Enums\DiscountScope::Item : \App\Enums\DiscountScope::Order))
            @if ($presets->isNotEmpty())
                <p class="text-sm font-semibold">Discounts</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($presets as $preset)
                        <button type="button" wire:key="preset-{{ $preset->id }}" wire:click="applyPreset({{ $preset->id }})" class="rounded-xl border-2 border-pandan-500 px-3 py-2 text-sm font-semibold text-pandan-600 hover:bg-pandan-100">
                            {{ $preset->name }} · {{ $preset->valueLabel(app(\App\Tenancy\TenantContext::class)->business()->currency) }}
                        </button>
                    @endforeach
                </div>
                <p class="mt-5 text-sm font-semibold">Or a discount of your own</p>
            @endif
            <form wire:submit="applyManualDiscount" class="mt-2 space-y-4">
                <div class="grid grid-cols-[auto_1fr] gap-2">
                    <div class="flex rounded-xl bg-cream-200 p-1">
                        @foreach (['percent' => '%', 'amount' => app(\App\Tenancy\TenantContext::class)->business()->currency] as $type => $sign)
                            <button type="button" wire:click="$set('discountType', '{{ $type }}')" @class(['rounded-lg px-3 py-1.5 text-sm font-semibold', 'bg-white shadow' => $discountType === $type])>{{ $sign }}</button>
                        @endforeach
                    </div>
                    <input type="text" inputmode="decimal" wire:model="discountValue" placeholder="{{ $discountType === 'percent' ? 'e.g. 10' : 'e.g. 5.00' }}" aria-label="Discount" class="{{ $input }}">
                </div>
                <div>
                    <label for="discount-reason" class="block text-sm font-semibold">Reason</label>
                    <input id="discount-reason" type="text" maxlength="200" wire:model="discountReason" placeholder="e.g. complaint, regular customer" class="{{ $input }} mt-1">
                </div>
                @if ($needsApproval) {!! $pinField !!} @endif
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeCorrection" class="{{ $light }}">Cancel</button>
                    <button type="submit" class="{{ $primary }}">Give discount</button>
                </div>
            </form>
        </x-pos.modal>
    @endif

    @if ($voidingItemId !== null && $order)
        @php($voiding = $order->lines->firstWhere('id', $voidingItemId))
        <x-pos.modal :heading="'Void '.($voiding?->name ?? 'item')">
            <form wire:submit="confirmVoid" class="space-y-4">
                <p class="text-sm text-ink-500">It has been sent to the kitchen. It stays on the order crossed out, and is not charged.</p>
                <div>
                    <label for="void-reason" class="block text-sm font-semibold">Reason</label>
                    <input id="void-reason" type="text" maxlength="200" wire:model="voidReason" required placeholder="e.g. customer changed their mind" class="{{ $input }} mt-1">
                </div>
                @if ($needsApproval) {!! $pinField !!} @endif
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeCorrection" class="{{ $light }}">Back</button>
                    <button type="submit" class="{{ $primary }}">Void</button>
                </div>
            </form>
        </x-pos.modal>
    @endif

    @if ($cancelling && $order)
        <x-pos.modal :heading="'Cancel order '.$order->number_label">
            <form wire:submit="confirmCancel" class="space-y-4">
                <p class="text-sm text-ink-500">Nothing on it will be charged, and its table is freed. An order with payments cannot be cancelled; refund it instead.</p>
                <div>
                    <label for="cancel-reason" class="block text-sm font-semibold">Reason</label>
                    <input id="cancel-reason" type="text" maxlength="200" wire:model="cancelReason" required placeholder="e.g. customer left" class="{{ $input }} mt-1">
                </div>
                @if ($needsApproval) {!! $pinField !!} @endif
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeCorrection" class="{{ $light }}">Back</button>
                    <button type="submit" class="{{ $primary }}">Cancel order</button>
                </div>
            </form>
        </x-pos.modal>
    @endif

    @if ($refunding && $order)
        <x-pos.modal :heading="'Refund order '.$order->number_label">
            <form wire:submit="confirmRefund" class="space-y-4">
                <p class="text-sm text-ink-500">Paid {{ $this->money($order->paid) }}; up to {{ $this->money($order->refundable()) }} can still be refunded. Refunds are given in cash from this shift's drawer.</p>
                <div class="flex rounded-xl bg-cream-200 p-1 text-sm font-semibold">
                    <button type="button" wire:click="$set('refundMode', 'items')" @class(['flex-1 rounded-lg px-3 py-1.5', 'bg-white shadow' => $refundMode === 'items'])>Items</button>
                    <button type="button" wire:click="$set('refundMode', 'amount')" @class(['flex-1 rounded-lg px-3 py-1.5', 'bg-white shadow' => $refundMode === 'amount'])>Amount</button>
                </div>
                @if ($refundMode === 'items')
                    <ul class="divide-y divide-cream-200 text-sm">
                        @foreach ($order->lines->reject->isVoided() as $refundLine)
                            @php($left = $refundLine->quantity - $refundLine->refunded_quantity)
                            <li wire:key="refund-line-{{ $refundLine->id }}" class="flex items-center justify-between gap-3 py-2">
                                <span>{{ $refundLine->name }} <span class="text-ink-500">· {{ $left }} of {{ $refundLine->quantity }} left</span></span>
                                <span class="flex items-center gap-1">
                                    <button type="button" wire:click="changeRefundQuantity({{ $refundLine->id }}, -1)" class="size-8 rounded-lg bg-cream-200 font-bold" aria-label="One fewer {{ $refundLine->name }}">−</button>
                                    <span class="w-6 text-center font-semibold tabular-nums">{{ $refundQuantities[$refundLine->id] ?? 0 }}</span>
                                    <button type="button" wire:click="changeRefundQuantity({{ $refundLine->id }}, 1)" @disabled($left === 0) class="size-8 rounded-lg bg-cream-200 font-bold disabled:opacity-40" aria-label="One more {{ $refundLine->name }}">+</button>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="text-xs text-ink-500">Each item is refunded at its share of what was paid, with SST, service charge and discounts.</p>
                @else
                    <div>
                        <label for="refund-amount" class="block text-sm font-semibold">Amount</label>
                        <input id="refund-amount" type="text" inputmode="decimal" wire:model="refundAmount" class="{{ $input }} mt-1 text-2xl font-bold">
                    </div>
                @endif
                <div>
                    <label for="refund-reason" class="block text-sm font-semibold">Reason</label>
                    <input id="refund-reason" type="text" maxlength="200" wire:model="refundReason" required placeholder="e.g. cold food" class="{{ $input }} mt-1">
                </div>
                @if ($needsApproval) {!! $pinField !!} @endif
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeCorrection" class="{{ $light }}">Back</button>
                    <button type="submit" class="{{ $primary }}">Refund in cash</button>
                </div>
            </form>
        </x-pos.modal>
    @endif

    {{-- The shift: who opened it, the cash drawer, what was taken, and the
         buttons to close it or switch terminal. With no shift open, the
         place to open one. --}}
    @if ($shiftPanel)
        @php($summary = $this->shiftSummary)
        <x-pos.modal :heading="$shift ? 'Shift' : ($mayRunShift ? 'Open shift' : 'No shift open')">
            <p class="-mt-2 mb-4 text-sm text-ink-500">{{ $this->branch->name }} · {{ $this->terminal->name }}</p>

            @if ($shift && ! $closingShift)
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-cream-100 p-3">
                        <dt class="text-ink-500">Opened by</dt>
                        <dd class="font-semibold">{{ $summary['opened_by'] }}</dd>
                    </div>
                    <div class="rounded-xl bg-cream-100 p-3">
                        <dt class="text-ink-500">Opened at</dt>
                        <dd class="font-semibold">{{ $summary['opened_at'] }} <span class="font-normal text-ink-500">({{ $summary['duration'] }})</span></dd>
                    </div>
                    <div class="rounded-xl bg-cream-100 p-3">
                        <dt class="text-ink-500">Opening float</dt>
                        <dd class="font-semibold">{{ $this->money($shift->opening_float) }}</dd>
                    </div>
                    <div class="rounded-xl bg-pandan-100 p-3">
                        <dt class="text-ink-700">Cash in drawer now</dt>
                        <dd class="font-display text-lg font-bold">{{ $this->money($summary['expected_cash']) }}</dd>
                    </div>
                </dl>

                <h3 class="mt-5 text-sm font-semibold uppercase tracking-wider text-ink-500">Taken this shift</h3>
                <dl class="mt-2 divide-y divide-cream-200 text-sm">
                    @forelse ($summary['methods'] as $method)
                        <div class="flex justify-between py-1.5">
                            <dt>{{ $method['name'] }} <span class="text-ink-500">· {{ $method['count'] }} {{ Str::plural('payment', $method['count']) }}</span></dt>
                            <dd class="font-semibold tabular-nums">{{ $this->money($method['total']) }}</dd>
                        </div>
                    @empty
                        <p class="py-1.5 text-ink-500">No payments yet.</p>
                    @endforelse
                    <div class="flex justify-between py-1.5 font-semibold">
                        <dt>Total taken</dt>
                        <dd class="tabular-nums">{{ $this->money($summary['total_taken']) }}</dd>
                    </div>
                </dl>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl border border-cream-200 p-3">
                        <dt class="text-ink-500">Orders paid</dt>
                        <dd class="font-semibold">{{ $summary['orders_paid'] }}</dd>
                    </div>
                    <div class="rounded-xl border border-cream-200 p-3">
                        <dt class="text-ink-500">Open orders at the branch</dt>
                        <dd class="font-semibold">{{ $summary['open_orders'] }} · {{ $this->money($summary['open_value']) }} unpaid</dd>
                    </div>
                </dl>

                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" wire:click="$set('shiftPanel', false)" class="{{ $light }}">Back</button>
                    <a href="{{ route('pos.terminal', ['switch' => 1]) }}" class="{{ $light }}">Switch terminal</a>
                    @if ($mayRunShift)
                        <button type="button" wire:click="$set('closingShift', true)" class="{{ $primary }}">Close shift</button>
                    @endif
                </div>
            @elseif ($shift && $closingShift)
                <form wire:submit="closeShift" class="space-y-4">
                    <p class="text-sm text-ink-500">Count the cash in the drawer. It should hold <span class="font-semibold text-ink-900">{{ $this->money($summary['expected_cash']) }}</span>: the float of {{ $this->money($shift->opening_float) }} plus {{ $this->money($summary['cash_taken']) }} cash taken.</p>
                    @if ($summary['open_orders'] > 0)
                        <p class="rounded-xl bg-saffron-100 p-3 text-sm">{{ $summary['open_orders'] }} {{ Str::plural('order', $summary['open_orders']) }} at the branch are still open. They can be paid on the next shift.</p>
                    @endif
                    <div>
                        <label for="counted-cash" class="block text-sm font-semibold">Cash counted</label>
                        <input id="counted-cash" type="text" inputmode="decimal" wire:model="countedCash" required autofocus class="{{ $input }} mt-1 text-2xl font-bold">
                    </div>
                    <div>
                        <label for="closing-note" class="block text-sm font-semibold">Note <span class="font-normal text-ink-500">(optional)</span></label>
                        <input id="closing-note" type="text" maxlength="500" wire:model="closingNote" class="{{ $input }} mt-1">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('closingShift', false)" class="{{ $light }}">Back</button>
                        <button type="submit" class="{{ $primary }}">Close shift</button>
                    </div>
                </form>
            @elseif ($mayRunShift)
                <form wire:submit="openShift" class="space-y-4">
                    <p class="text-sm text-ink-500">Count the cash in the drawer and enter it as the float. Payments can be taken once the shift is open.</p>
                    <div>
                        <label for="opening-float" class="block text-sm font-semibold">Opening float</label>
                        <input id="opening-float" type="text" inputmode="decimal" wire:model="openingFloat" placeholder="e.g. 100.00" autofocus required class="{{ $input }} mt-1 text-2xl font-bold">
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" wire:click="$set('shiftPanel', false)" class="{{ $light }}">Back</button>
                        <a href="{{ route('pos.terminal', ['switch' => 1]) }}" class="{{ $light }}">Switch terminal</a>
                        <button type="submit" class="{{ $primary }}">Open shift</button>
                    </div>
                </form>
            @else
                <p class="text-sm text-ink-700">No shift is open on this terminal. Orders can be taken, and a cashier opens the shift before payment.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="$set('shiftPanel', false)" class="{{ $light }}">Back</button>
                    <a href="{{ route('pos.terminal', ['switch' => 1]) }}" class="{{ $light }}">Switch terminal</a>
                </div>
            @endif
        </x-pos.modal>
    @endif
</div>
