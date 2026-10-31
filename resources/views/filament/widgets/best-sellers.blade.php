<x-filament-widgets::widget>
    <x-filament::section heading="Best sellers today">
        <x-slot name="afterHeader">
            <x-filament::link :href="$url" size="sm">All products</x-filament::link>
        </x-slot>

        @if ($rows === [])
            <p style="font-size: 0.875rem; opacity: 0.7;">Nothing sold yet today.</p>
        @else
            <ol style="display: grid; gap: 0.5rem; font-size: 0.875rem;">
                @foreach ($rows as $index => $row)
                    <li style="display: flex; justify-content: space-between; gap: 1rem;">
                        <span><span style="opacity: 0.5;">{{ $index + 1 }}.</span> {{ $row['product'] }} <span style="opacity: 0.6;">× {{ $row['sold'] }}</span></span>
                        <span style="font-variant-numeric: tabular-nums; font-weight: 600;">{{ $currency }} {{ number_format((float) $row['sales'], 2) }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
