<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
        <x-slot name="heading">Add your SST registration number</x-slot>
        <x-slot name="description">
            SST is charged, but there is no registration number to print on receipts yet.
            <x-filament::link :href="$url">Open Restaurant settings</x-filament::link>
        </x-slot>
    </x-filament::section>
</x-filament-widgets::widget>
