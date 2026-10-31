{{-- Transactions: the branch's orders on a business day. Tapping one shows
     it in the order panel, with its receipt. --}}
<section class="flex min-h-0 flex-col rounded-2xl bg-white/5">
    <div class="flex flex-wrap items-end gap-3 border-b border-white/10 p-3">
        <div>
            <label for="transactions-date" class="block text-xs font-semibold uppercase tracking-wider text-cream-200/70">Business day</label>
            <input id="transactions-date" type="date" wire:model.live="transactionsDate" max="{{ $this->branch->businessDateFor(now())->toDateString() }}" class="mt-1 rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-cream-50 [color-scheme:dark]">
        </div>
        <div class="min-w-48 flex-1">
            <label for="transactions-search" class="block text-xs font-semibold uppercase tracking-wider text-cream-200/70">Search</label>
            <input id="transactions-search" type="search" wire:model.live.debounce.300ms="transactionsSearch" placeholder="Order number, customer name or phone" class="mt-1 w-full rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-cream-50 placeholder:text-cream-200/50">
        </div>
        <p class="text-sm text-cream-200/70">
            {{ $this->transactions->count() }} {{ Str::plural('order', $this->transactions->count()) }} ·
            {{ $this->money((string) $this->transactions->where('status', \App\Enums\OrderStatus::Completed)->sum('total')) }} paid
        </p>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto">
        <table class="w-full text-left text-sm text-cream-100">
            <thead class="sticky top-0 bg-ink-900 text-xs uppercase tracking-wider text-cream-200/70">
                <tr>
                    <th scope="col" class="px-4 py-2">Order</th>
                    <th scope="col" class="px-4 py-2">Time</th>
                    <th scope="col" class="px-4 py-2">Where</th>
                    <th scope="col" class="px-4 py-2">Status</th>
                    <th scope="col" class="px-4 py-2">Paid by</th>
                    <th scope="col" class="px-4 py-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse ($this->transactions as $transaction)
                    <tr wire:key="transaction-{{ $transaction->id }}" wire:click="showTransaction({{ $transaction->id }})" @class(['cursor-pointer hover:bg-white/10', 'bg-white/10' => $orderId === $transaction->id])>
                        <td class="px-4 py-2.5 font-semibold text-cream-50">
                            <button type="button" class="text-left">{{ $transaction->number_label }}</button>
                        </td>
                        <td class="px-4 py-2.5 tabular-nums">{{ $transaction->created_at->timezone($this->branch->effectiveTimezone())->format('H:i') }}</td>
                        <td class="px-4 py-2.5">{{ $transaction->placeLabel() }}@if ($transaction->customer) · {{ $transaction->customer->displayName() }}@endif</td>
                        <td class="px-4 py-2.5">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-pandan-500/20 text-pandan-100' => $transaction->status === \App\Enums\OrderStatus::Completed,
                                'bg-chili-500/20 text-chili-100' => $transaction->status === \App\Enums\OrderStatus::Cancelled,
                                'bg-saffron-400/20 text-saffron-400' => $transaction->isOpen(),
                            ])>{{ $transaction->isOpen() ? 'Open · '.$transaction->payment_status->label() : $transaction->status->label() }}</span>
                        </td>
                        <td class="px-4 py-2.5">{{ $transaction->payments->pluck('method_name')->unique()->implode(', ') ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ $this->money($transaction->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-cream-200/70">No orders on this day.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
