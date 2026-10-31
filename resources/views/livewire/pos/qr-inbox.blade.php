@php($count = $this->waiting->count() + $this->requests->count())

<div
    wire:poll.8s
    x-data="{
        chime() {
            const audio = new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.15, 0.3].forEach((delay, i) => {
                const tone = audio.createOscillator();
                tone.frequency.value = [660, 880, 990][i];
                tone.connect(audio.destination);
                tone.start(audio.currentTime + delay);
                tone.stop(audio.currentTime + delay + 0.1);
            });
        },
    }"
    x-on:qr-arrived.window="chime()"
>
    <button type="button" wire:click="$set('open', true)" aria-label="QR orders and calls" title="QR orders and calls" @class(['relative grid size-10 place-items-center rounded-lg transition', 'bg-saffron-400 text-ink-900' => $count > 0, 'text-cream-100 hover:bg-white/10' => $count === 0])>
        @svg('heroicon-o-qr-code', 'size-6')
        @if ($count > 0)
            <span class="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full bg-chili-500 px-1 text-xs font-bold text-white">{{ $count }}</span>
        @endif
    </button>

    @if ($open)
        <x-pos.modal heading="QR orders and calls">
            @if ($message !== '')
                <p class="mb-3 rounded-xl bg-cream-200 p-3 text-sm">{{ $message }}</p>
            @endif

            @if ($this->requests->isNotEmpty())
                <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-500">Calls</h3>
                <ul class="mt-2 mb-5 space-y-2">
                    @foreach ($this->requests as $request)
                        <li wire:key="req-{{ $request->id }}" class="flex items-center justify-between gap-3 rounded-xl bg-saffron-100 p-3">
                            <span><span class="font-semibold">Table {{ $request->table?->name }}</span> {{ strtolower($request->label()) }} · {{ $request->created_at->diffForHumans() }}</span>
                            <button type="button" wire:click="resolve({{ $request->id }})" class="rounded-lg bg-ink-900 px-3 py-1.5 text-sm font-semibold text-white">Done</button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-500">Orders to confirm</h3>
            <div class="mt-2 space-y-3">
                @forelse ($this->waiting as $order)
                    <article wire:key="qr-{{ $order->id }}" class="rounded-xl border border-cream-300 p-3">
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="font-display text-lg font-bold">Table {{ $order->diningTable?->name }}</p>
                            <p class="text-sm text-ink-500">{{ $order->created_at->diffForHumans() }}</p>
                        </div>
                        @if ($order->customer || $order->note)
                            <p class="text-sm text-ink-500">{{ $order->customer ? $order->customer->displayName().' · '.$order->customer->phone : $order->note }}</p>
                        @endif
                        <ul class="mt-2 text-sm">
                            @foreach ($order->lines as $line)
                                <li>
                                    <span class="font-semibold">{{ $line->quantity }} × {{ $line->name }}</span>
                                    @if ($line->modifiers->isNotEmpty() || $line->parts->isNotEmpty())
                                        <span class="text-ink-500">· {{ collect([...$line->modifiers->pluck('name'), ...$line->parts->pluck('name')])->implode(', ') }}</span>
                                    @endif
                                    @if ($line->note)
                                        <span class="italic text-ink-500">“{{ $line->note }}”</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-1 text-sm font-semibold">{{ app(\App\Tenancy\TenantContext::class)->business()->currency }} {{ number_format((float) $order->total, 2) }}</p>

                        @if ($rejectingId === $order->id)
                            <form wire:submit="reject" class="mt-3 flex gap-2">
                                <input type="text" wire:model="rejectReason" required maxlength="200" placeholder="Reason for the customer, e.g. sold out" class="min-w-0 flex-1 rounded-lg border border-cream-300 px-3 py-2 text-sm">
                                <button type="submit" class="rounded-lg bg-chili-500 px-3 py-2 text-sm font-semibold text-white">Reject</button>
                            </form>
                        @else
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <button type="button" wire:click="$set('rejectingId', {{ $order->id }})" class="rounded-lg bg-cream-200 px-3 py-2 text-sm font-semibold">Reject</button>
                                <button type="button" wire:click="confirm({{ $order->id }})" class="rounded-lg bg-pandan-500 px-3 py-2 text-sm font-semibold text-white">Confirm</button>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-ink-500">No QR orders waiting.</p>
                @endforelse
            </div>

            <div class="mt-5 flex justify-end">
                <button type="button" wire:click="$set('open', false)" class="rounded-xl bg-cream-200 px-4 py-2.5 text-sm font-semibold">Close</button>
            </div>
        </x-pos.modal>
    @endif
</div>
