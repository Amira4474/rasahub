<x-filament-panels::page>
    @php($pending = $this->centralPending())

    <x-filament::section>
        <x-slot name="heading">Central database</x-slot>
        <x-slot name="description">
            {{ $this->centralDatabase() }} holds tenants, users, roles, subscriptions, invitations and the audit log.
        </x-slot>

        @if ($pending === [])
            <x-filament::badge color="success">Up to date</x-filament::badge>
        @else
            <x-filament::badge color="warning">{{ count($pending) }} pending</x-filament::badge>

            <ul style="margin-top: 0.75rem; font-family: ui-monospace, monospace; font-size: 0.875rem;">
                @foreach ($pending as $migration)
                    <li>{{ $migration }}</li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
