{{-- Kitchen display: every order with lines in the kitchen, by stage. It
     refreshes every few seconds. --}}
@php
    use App\Enums\KitchenStatus;

    $stages = [
        KitchenStatus::Sent->value => ['label' => 'New', 'action' => 'Start preparing', 'tone' => 'border-chili-400'],
        KitchenStatus::Preparing->value => ['label' => 'Preparing', 'action' => 'Ready', 'tone' => 'border-saffron-400'],
        KitchenStatus::Ready->value => ['label' => 'Ready', 'action' => 'Served', 'tone' => 'border-pandan-500'],
    ];
@endphp

<section class="flex min-h-0 flex-col rounded-2xl bg-white/5" @if ($this->kitchenIncluded()) wire:poll.5s @endif>
    @if (! $this->kitchenIncluded())
        <div class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center text-cream-100">
            <p class="font-display text-2xl font-bold text-cream-50">Kitchen display</p>
            <p class="max-w-md text-cream-200/80">The kitchen display is part of the Growth and Enterprise plans. Ask the owner to contact RasaHub to change plan.</p>
        </div>
    @else
        <div class="grid min-h-0 flex-1 gap-3 p-3 md:grid-cols-3">
            @foreach ($stages as $stage => $info)
                @php($orders = $this->kitchenOrders[$stage])
                <div class="flex min-h-0 flex-col rounded-xl bg-white/5">
                    <h2 class="flex items-center justify-between border-b border-white/10 px-4 py-2 text-sm font-semibold uppercase tracking-wider text-cream-50">
                        {{ $info['label'] }}
                        <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs">{{ $orders->count() }}</span>
                    </h2>
                    <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                        @forelse ($orders as $kitchenOrder)
                            <article wire:key="kitchen-{{ $stage }}-{{ $kitchenOrder->id }}" class="rounded-xl border-l-8 {{ $info['tone'] }} bg-cream-50 p-3 text-ink-900">
                                <header class="flex items-baseline justify-between gap-2">
                                    <p class="font-display text-lg font-bold">{{ $kitchenOrder->number_label }}</p>
                                    <p class="text-sm text-ink-500">{{ $kitchenOrder->placeLabel() }}</p>
                                </header>
                                <p class="text-xs text-ink-500">Sent {{ $kitchenOrder->items->max('sent_at')?->diffForHumans() ?? $kitchenOrder->lines->max('sent_at')?->diffForHumans() }}</p>
                                <ul class="mt-2 space-y-1 text-sm">
                                    @foreach ($kitchenOrder->lines as $line)
                                        @php($lineStatus = $line->kitchen_status)
                                        @continue(! in_array($lineStatus, [KitchenStatus::Sent, KitchenStatus::Preparing, KitchenStatus::Ready, KitchenStatus::Voided], true))
                                        <li @class(['opacity-50' => $lineStatus->value !== $stage && $lineStatus !== KitchenStatus::Voided, 'text-chili-600' => $lineStatus === KitchenStatus::Voided])>
                                            <span @class(['font-semibold', 'line-through' => $lineStatus === KitchenStatus::Voided])>{{ $line->quantity }} × {{ $line->name }}</span>
                                            @if ($lineStatus === KitchenStatus::Voided)
                                                <span class="text-xs font-bold uppercase">Void, do not make</span>
                                            @endif
                                            @if ($line->modifiers->isNotEmpty())
                                                <span class="text-ink-500">· {{ $line->modifiers->pluck('name')->implode(', ') }}</span>
                                            @endif
                                            @foreach ($line->parts as $part)
                                                <span class="block pl-3 text-ink-500">– {{ $part->name }}</span>
                                            @endforeach
                                            @if ($line->note)
                                                <span class="block pl-3 italic text-chili-600">“{{ $line->note }}”</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                <button type="button" wire:click="advanceKitchen({{ $kitchenOrder->id }}, '{{ $stage }}')" class="mt-3 w-full rounded-lg bg-ink-900 px-3 py-2 text-sm font-semibold text-white hover:bg-ink-800">
                                    {{ $info['action'] }}
                                </button>
                            </article>
                        @empty
                            <p class="py-6 text-center text-sm text-cream-200/60">Nothing here.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
