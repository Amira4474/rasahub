@php
    use App\Enums\KitchenStatus;

    $screen = $this->screen;
    $branch = $screen->branch;
    $orders = $this->orders;
    $isReady = fn ($order): bool => $order->items->reject(fn ($line) => $line->kitchen_status === KitchenStatus::Voided)->every(fn ($line) => $line->kitchen_status === KitchenStatus::Ready);
    $cooking = $orders->reject($isReady)->values();
    $ready = $orders->filter($isReady)->values();
@endphp

<div
    wire:poll.5s
    class="flex min-h-0 flex-1 flex-col"
    x-data="{
        sound: false,
        beep() {
            if (! this.sound) return;
            const audio = new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.18].forEach((delay) => {
                const tone = audio.createOscillator();
                tone.frequency.value = 880;
                tone.connect(audio.destination);
                tone.start(audio.currentTime + delay);
                tone.stop(audio.currentTime + delay + 0.12);
            });
        },
        now: Date.now(),
    }"
    x-init="setInterval(() => now = Date.now(), 1000)"
    x-on:kitchen-new-order.window="beep()"
>
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
        <div class="flex items-center gap-3">
            <x-marketing.logo class="text-white" />
            <span class="rounded-full bg-white/10 px-3 py-1 text-sm font-semibold text-cream-100">{{ $branch->name }} · {{ $screen->name }}</span>
            <span class="text-sm text-cream-200/70">{{ $this->stationNames() }}</span>
        </div>
        <div class="flex items-center gap-2 text-sm text-cream-100">
            <button type="button" x-on:click="sound = ! sound; if (sound) beep()" class="rounded-xl bg-white/10 px-3 py-2 font-semibold hover:bg-white/20" x-text="sound ? 'Sound on' : 'Sound off'"></button>
            <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
            <a href="{{ route('pos.terminal', ['switch' => 1]) }}" class="rounded-xl bg-white/10 px-3 py-2 font-semibold hover:bg-white/20">Switch device</a>
            @unless (app(\App\Tenancy\SuperImpose::class)->isActive())
                <form method="POST" action="{{ route('pos.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-xl bg-white px-3 py-2 font-semibold text-ink-900">Sign out</button>
                </form>
            @endunless
        </div>
    </header>

    @if ($message !== '')
        <p role="alert" class="mx-4 mt-3 rounded-xl bg-chili-500 px-4 py-2 text-sm font-medium text-white">{{ $message }}</p>
    @endif

    <div class="grid min-h-0 flex-1 gap-3 p-3 lg:grid-cols-[minmax(0,3fr)_minmax(0,1fr)]">
        {{-- Orders being made --}}
        <section class="flex min-h-0 flex-col rounded-2xl bg-white/5">
            <h2 class="flex items-center justify-between border-b border-white/10 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-cream-50">
                To make <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs">{{ $cooking->count() }}</span>
            </h2>
            <div class="grid min-h-0 flex-1 auto-rows-min grid-cols-[repeat(auto-fill,minmax(15rem,1fr))] gap-3 overflow-y-auto p-3">
                @forelse ($cooking as $order)
                    @php($sentAt = $order->items->min('sent_at'))
                    <article
                        wire:key="kds-{{ $order->id }}"
                        x-data="{ sent: {{ $sentAt?->getTimestampMs() ?? 'Date.now()' }} }"
                        :class="{
                            'border-pandan-500': (now - sent) / 60000 < {{ $branch->kitchen_amber_minutes }},
                            'border-saffron-400': (now - sent) / 60000 >= {{ $branch->kitchen_amber_minutes }} && (now - sent) / 60000 < {{ $branch->kitchen_red_minutes }},
                            'border-chili-500 ring-4 ring-chili-500/40': (now - sent) / 60000 >= {{ $branch->kitchen_red_minutes }},
                        }"
                        class="flex flex-col rounded-xl border-t-8 bg-cream-50 p-3 text-ink-900"
                    >
                        <header class="flex items-baseline justify-between gap-2">
                            <p class="font-display text-xl font-bold">{{ $order->number_label }}</p>
                            <p class="font-mono text-lg font-bold tabular-nums" x-text="(() => { const s = Math.max(0, Math.floor((now - sent) / 1000)); return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); })()"></p>
                        </header>
                        <p class="text-sm text-ink-500">{{ $order->placeLabel() }}@if ($order->guests) · {{ $order->guests }} guests @endif</p>
                        <ul class="mt-2 flex-1 space-y-1">
                            @foreach ($order->items as $line)
                                @php($status = $line->kitchen_status)
                                <li>
                                    @if ($status === KitchenStatus::Voided)
                                        <p class="rounded-lg px-2 py-1 text-chili-600"><span class="font-semibold line-through">{{ $line->quantity }} × {{ $line->name }}</span> <span class="text-xs font-bold uppercase">Void, do not make</span></p>
                                    @else
                                        <button type="button" wire:click="toggleLine({{ $line->id }})" @class([
                                            'flex w-full items-start gap-2 rounded-lg px-2 py-1 text-left transition',
                                            'bg-pandan-100 text-pandan-600 line-through' => $status === KitchenStatus::Ready,
                                            'hover:bg-cream-200' => $status !== KitchenStatus::Ready,
                                        ]) aria-pressed="{{ $status === KitchenStatus::Ready ? 'true' : 'false' }}">
                                            <span class="mt-0.5">@svg($status === KitchenStatus::Ready ? 'heroicon-s-check-circle' : 'heroicon-o-stop', 'size-5')</span>
                                            <span>
                                                <span class="font-semibold">{{ $line->quantity }} × {{ $line->name }}</span>
                                                @if ($line->modifiers->isNotEmpty())
                                                    <span class="block text-sm text-ink-500 no-underline">{{ $line->modifiers->pluck('name')->implode(', ') }}</span>
                                                @endif
                                                @if ($line->note)
                                                    <span class="block text-sm font-semibold italic text-chili-600">“{{ $line->note }}”</span>
                                                @endif
                                                @if ($screen->stationIds() === [] && $line->station_name)
                                                    <span class="block text-xs uppercase tracking-wider text-ink-500">{{ $line->station_name }}</span>
                                                @endif
                                            </span>
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            @if ($order->items->contains('kitchen_status', KitchenStatus::Sent))
                                <button type="button" wire:click="start({{ $order->id }})" class="rounded-lg bg-cream-200 px-3 py-2 text-sm font-semibold">Start</button>
                            @endif
                            <button type="button" wire:click="allReady({{ $order->id }})" class="col-span-1 rounded-lg bg-ink-900 px-3 py-2 text-sm font-semibold text-white last:col-span-2">All ready</button>
                        </div>
                    </article>
                @empty
                    <p class="col-span-full py-10 text-center text-cream-200/60">Nothing to make. New orders appear here.</p>
                @endforelse
            </div>
        </section>

        {{-- Ready to serve, and recall --}}
        <section class="flex min-h-0 flex-col gap-3">
            <div class="flex min-h-0 flex-1 flex-col rounded-2xl bg-white/5">
                <h2 class="flex items-center justify-between border-b border-white/10 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-cream-50">
                    Ready <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs">{{ $ready->count() }}</span>
                </h2>
                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
                    @forelse ($ready as $order)
                        <article wire:key="ready-{{ $order->id }}" class="rounded-xl border-l-8 border-pandan-500 bg-cream-50 p-3 text-ink-900">
                            <div class="flex items-baseline justify-between">
                                <p class="font-display text-lg font-bold">{{ $order->number_label }}</p>
                                <p class="text-sm text-ink-500">{{ $order->placeLabel() }}</p>
                            </div>
                            <p class="text-sm text-ink-500">{{ $order->items->where('kitchen_status', KitchenStatus::Ready)->map(fn ($l) => $l->quantity.' × '.$l->name)->implode(', ') }}</p>
                            <button type="button" wire:click="served({{ $order->id }})" class="mt-2 w-full rounded-lg bg-pandan-500 px-3 py-2 text-sm font-semibold text-white">Served</button>
                        </article>
                    @empty
                        <p class="py-6 text-center text-sm text-cream-200/60">Nothing waiting to be served.</p>
                    @endforelse
                </div>
            </div>

            @if ($this->recent->isNotEmpty())
                <div class="rounded-2xl bg-white/5 p-3">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-cream-200/70">Recently served</h2>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($this->recent as $order)
                            <button type="button" wire:key="recall-{{ $order->id }}" wire:click="recall({{ $order->id }})" class="rounded-lg bg-white/10 px-3 py-1.5 text-sm text-cream-50 hover:bg-white/20" title="Bring it back">{{ $order->number_label }} ↺</button>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>
