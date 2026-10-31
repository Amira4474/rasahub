@php
    $money = fn (string $amount): string => $currency.' '.number_format((float) $amount, 2);
    $button = 'rounded-full px-5 py-3 text-base font-semibold text-white shadow-sm transition active:scale-95';
@endphp

<div class="mx-auto flex min-h-dvh max-w-lg flex-col pb-28" @if ($myOrders !== []) wire:poll.10s @endif>
    {{-- Header: the restaurant's banner, logo, heading and welcome text --}}
    <header class="relative">
        @if ($design['banner'])
            <img src="{{ $design['banner'] }}" alt="" class="h-40 w-full object-cover sm:h-48">
        @else
            <div class="h-24 w-full" style="background-color: var(--qr-primary);"></div>
        @endif
        {{-- Only the logo reaches up over the banner's edge; the name stays
             below it, on the page's own background, where it can be read. --}}
        <div class="relative px-4">
            <div class="flex items-start gap-3">
                @if ($design['logo'])
                    <img src="{{ $design['logo'] }}" alt="{{ $design['title'] }}" class="-mt-10 size-20 shrink-0 rounded-2xl border-4 border-white bg-white object-contain shadow-md">
                @endif
                <div class="min-w-0 pt-3">
                    <h1 class="truncate font-display text-2xl font-extrabold">{{ $design['title'] }}</h1>
                    <p class="text-sm text-ink-700">Table <span class="font-semibold">{{ $tableName }}</span> · {{ $branchName }}</p>
                </div>
            </div>
            @if ($design['welcome'])
                <p class="mt-3 text-ink-700">{{ $design['welcome'] }}</p>
            @endif
            <div class="mt-3 flex gap-2">
                <button type="button" wire:click="callStaff('call_staff')" class="flex items-center gap-1.5 rounded-full border border-ink-900/15 bg-white px-4 py-2 text-sm font-semibold">@svg('heroicon-o-bell-alert', 'size-4') Call staff</button>
                <button type="button" wire:click="callStaff('bill')" class="flex items-center gap-1.5 rounded-full border border-ink-900/15 bg-white px-4 py-2 text-sm font-semibold">@svg('heroicon-o-receipt-percent', 'size-4') Ask for the bill</button>
            </div>
        </div>
    </header>

    {{-- Message --}}
    @if ($message !== '')
        <div
            wire:key="message-{{ md5($message.microtime()) }}"
            x-data
            x-init="setTimeout(() => $wire.set('message', ''), {{ $messageTone === 'error' ? 6000 : 2500 }})"
            role="status"
            @class([
                'fixed inset-x-4 top-4 z-50 mx-auto max-w-md rounded-2xl px-4 py-3 text-center font-semibold shadow-lg',
                'bg-chili-600 text-white' => $messageTone === 'error',
                'bg-pandan-600 text-white' => $messageTone === 'success',
                'bg-ink-900 text-white' => $messageTone === 'info',
            ])
        >{{ $message }}</div>
    @endif

    {{-- This phone's orders --}}
    @if ($myOrders !== [])
        <section class="mx-4 mt-5 space-y-2" aria-label="Your orders">
            @foreach ($myOrders as $mine)
                <div class="rounded-2xl bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-ink-500">Your order at {{ $mine['label'] }}</p>
                        <p @class(['text-sm font-bold', 'text-chili-600' => $mine['tone'] === 'error', 'text-pandan-600' => $mine['tone'] === 'success', 'text-ink-900' => $mine['tone'] === 'info'])>{{ $mine['status'] }}</p>
                    </div>
                    <p class="mt-1 text-sm text-ink-700">{{ implode(', ', $mine['lines']) }}</p>
                </div>
            @endforeach
        </section>
    @endif

    {{-- Categories --}}
    <nav class="sticky top-0 z-30 mt-5 flex gap-2 overflow-x-auto px-4 py-3 backdrop-blur" style="background-color: color-mix(in srgb, var(--qr-background) 92%, transparent);" aria-label="Menu categories">
        @foreach ($categories as $category)
            <button type="button" wire:click="$set('categoryId', {{ $category['id'] }})" @class(['shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition', 'text-white' => $categoryId === $category['id'], 'bg-white text-ink-900' => $categoryId !== $category['id']]) @if ($categoryId === $category['id']) style="background-color: var(--qr-primary);" aria-current="true" @endif>
                {{ $category['name'] }}
            </button>
        @endforeach
    </nav>

    {{-- Products --}}
    <section class="grid gap-3 px-4" aria-label="Menu">
        @forelse ($products as $product)
            <button type="button" wire:key="p-{{ $product['id'] }}" wire:click="pick({{ $product['id'] }})" class="flex gap-3 rounded-2xl bg-white p-3 text-left shadow-sm transition active:scale-[0.99]">
                @if ($design['photos'] && $product['image'])
                    <img src="{{ $product['image'] }}" alt="" loading="lazy" class="size-24 shrink-0 rounded-xl object-cover">
                @endif
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="font-semibold">{{ $product['name'] }}</span>
                    @if ($design['descriptions'] && $product['description'])
                        <span class="mt-0.5 line-clamp-2 text-sm text-ink-500">{{ $product['description'] }}</span>
                    @endif
                    <span class="mt-auto flex items-center justify-between pt-2">
                        <span class="font-semibold">{{ $money($product['price']) }}</span>
                        <span class="grid size-9 place-items-center rounded-full text-white" style="background-color: var(--qr-primary);" aria-hidden="true">@svg('heroicon-m-plus', 'size-5')</span>
                    </span>
                </span>
            </button>
        @empty
            <p class="py-10 text-center text-ink-500">Nothing on the menu here right now.</p>
        @endforelse
    </section>

    @if ($design['footer'])
        <footer class="mt-8 px-4 text-center text-sm text-ink-500">{!! nl2br(e($design['footer'])) !!}</footer>
    @endif
    <p class="mt-4 px-4 text-center text-xs text-ink-500/70">Ordering by {{ config('app.name') }}</p>

    {{-- Basket bar --}}
    @if ($count > 0)
        <div class="fixed inset-x-0 bottom-0 z-40 mx-auto max-w-lg p-4">
            <button type="button" wire:click="$set('basketOpen', true)" class="{{ $button }} flex w-full items-center justify-between" style="background-color: var(--qr-primary);">
                <span class="flex items-center gap-2">@svg('heroicon-o-shopping-bag', 'size-5') {{ $count }} {{ Str::plural('item', $count) }}</span>
                <span>View basket · {{ $money($estimate['total']) }}</span>
            </button>
        </div>
    @endif

    {{-- Choosing options --}}
    @if ($picking)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-ink-900/60" wire:click.self="$set('pickingId', null)">
            <form wire:submit="confirmPick" class="max-h-[90dvh] w-full max-w-lg overflow-y-auto rounded-t-3xl bg-white p-5">
                @if ($design['photos'] && $picking['image'])
                    <img src="{{ $picking['image'] }}" alt="" class="-mx-5 -mt-5 mb-4 h-48 w-[calc(100%+2.5rem)] max-w-none object-cover">
                @endif
                <h2 class="font-display text-2xl font-bold">{{ $picking['name'] }}</h2>
                <p class="font-semibold">{{ $money($picking['price']) }}</p>
                @if ($picking['description'])
                    <p class="mt-1 text-sm text-ink-500">{{ $picking['description'] }}</p>
                @endif

                @foreach ([...$picking['setGroups'], ...$picking['modifierGroups']] as $group)
                    @php($isSet = $group['kind'] === 'set')
                    <fieldset class="mt-5">
                        <legend class="font-semibold">{{ $group['name'] }} <span class="text-sm font-normal text-ink-500">{{ $group['rule'] }}</span></legend>
                        <div class="mt-2 grid gap-2">
                            @foreach ($group['options'] as $option)
                                @php($chosen = in_array($option['id'], $isSet ? ($pickedParts[$group['id']] ?? []) : ($pickedModifiers[$group['id']] ?? []), true))
                                @php($extra = $isSet ? $option['extra'] : $option['delta'])
                                <button type="button" wire:key="o-{{ $group['id'] }}-{{ $option['id'] }}" wire:click="{{ $isSet ? 'togglePart' : 'toggleModifier' }}({{ $group['id'] }}, {{ $option['id'] }}, {{ $group['max'] }})" aria-pressed="{{ $chosen ? 'true' : 'false' }}" @class(['flex items-center justify-between rounded-xl border-2 px-4 py-3 text-left', 'border-transparent text-white' => $chosen, 'border-ink-900/10' => ! $chosen]) @if ($chosen) style="background-color: var(--qr-primary);" @endif>
                                    <span>{{ $option['name'] }}</span>
                                    @if ((float) $extra !== 0.0)
                                        <span class="text-sm">{{ (float) $extra > 0 ? '+' : '' }}{{ number_format((float) $extra, 2) }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <label class="mt-5 block text-sm font-semibold">Note for the kitchen <span class="font-normal text-ink-500">(optional)</span>
                    <input type="text" wire:model="pickNote" maxlength="200" placeholder="e.g. no peanuts" class="mt-1 block w-full rounded-xl border border-ink-900/15 px-4 py-3 font-normal">
                </label>

                <div class="mt-5 flex items-center gap-3">
                    <div class="flex items-center rounded-full border border-ink-900/15">
                        <button type="button" wire:click="$set('pickQuantity', {{ max(1, $pickQuantity - 1) }})" class="size-11 text-xl font-bold" aria-label="Fewer">−</button>
                        <span class="w-8 text-center font-semibold">{{ $pickQuantity }}</span>
                        <button type="button" wire:click="$set('pickQuantity', {{ min(\App\Sales\QrOrdering::MAX_QUANTITY, $pickQuantity + 1) }})" class="size-11 text-xl font-bold" aria-label="More">+</button>
                    </div>
                    <button type="submit" class="{{ $button }} flex-1" style="background-color: var(--qr-primary);">Add to basket</button>
                </div>
            </form>
        </div>
    @endif

    {{-- The basket --}}
    @if ($basketOpen)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-ink-900/60" wire:click.self="$set('basketOpen', false)">
            <form wire:submit="send" class="max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-3xl bg-white p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-2xl font-bold">Your order</h2>
                    <button type="button" wire:click="$set('basketOpen', false)" class="text-sm font-semibold text-ink-500">Keep browsing</button>
                </div>
                <p class="text-sm text-ink-500">Table {{ $tableName }}</p>

                <ul class="mt-4 divide-y divide-ink-900/10">
                    @foreach ($lines as $key => $line)
                        <li wire:key="b-{{ $key }}" class="flex items-start justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $line['name'] }}</p>
                                @if ($line['details'])<p class="text-sm text-ink-500">{{ $line['details'] }}</p>@endif
                                @if ($line['note'])<p class="text-sm italic text-ink-500">“{{ $line['note'] }}”</p>@endif
                                <div class="mt-2 inline-flex items-center rounded-full border border-ink-900/15">
                                    <button type="button" wire:click="changeQuantity('{{ $key }}', -1)" class="size-9 font-bold" aria-label="One fewer {{ $line['name'] }}">−</button>
                                    <span class="w-6 text-center text-sm font-semibold">{{ $line['quantity'] }}</span>
                                    <button type="button" wire:click="changeQuantity('{{ $key }}', 1)" class="size-9 font-bold" aria-label="One more {{ $line['name'] }}">+</button>
                                </div>
                            </div>
                            <p class="shrink-0 font-semibold">{{ $money($line['total']) }}</p>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-2 space-y-1 border-t border-ink-900/10 pt-3 text-sm">
                    <div class="flex justify-between"><dt>Items</dt><dd>{{ $money($estimate['subtotal']) }}</dd></div>
                    @if ($estimate['service_charge'] !== '0.00')
                        <div class="flex justify-between"><dt>Service charge {{ $estimate['service_rate'] }}%</dt><dd>{{ $money($estimate['service_charge']) }}</dd></div>
                    @endif
                    @if ($estimate['sst'] !== '0.00')
                        <div class="flex justify-between text-ink-500"><dt>SST {{ $estimate['sst_rate'] }}%{{ $estimate['sst_included'] ? ' (included)' : '' }}</dt><dd>{{ $money($estimate['sst']) }}</dd></div>
                    @endif
                    <div class="flex justify-between pt-1 text-lg font-bold"><dt>Total</dt><dd>{{ $money($estimate['total']) }}</dd></div>
                </dl>

                <details class="mt-4 rounded-2xl bg-cream-100 p-4">
                    <summary class="cursor-pointer text-sm font-semibold">Add your name and phone <span class="font-normal text-ink-500">(optional)</span></summary>
                    <div class="mt-3 grid gap-3">
                        <input type="text" wire:model="name" maxlength="100" placeholder="Your name" autocomplete="name" class="rounded-xl border border-ink-900/15 px-4 py-3">
                        <input type="tel" wire:model="phone" maxlength="20" placeholder="Phone number" autocomplete="tel" class="rounded-xl border border-ink-900/15 px-4 py-3">
                        <label class="flex items-start gap-2 text-sm text-ink-700">
                            <input type="checkbox" wire:model="consent" class="mt-0.5 size-4">
                            <span>I agree to receive offers and news from {{ $design['title'] }}.</span>
                        </label>
                    </div>
                </details>

                <p class="mt-4 text-center text-sm text-ink-500">You pay at the counter. Staff confirm your order before it goes to the kitchen.</p>
                <button type="submit" wire:loading.attr="disabled" class="{{ $button }} mt-3 w-full" style="background-color: var(--qr-primary);">Send order</button>
            </form>
        </div>
    @endif
</div>
