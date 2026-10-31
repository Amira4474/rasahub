@php
    $business = app(\App\Tenancy\SuperImpose::class)->isActive()
        ? app(\App\Tenancy\TenantContext::class)->business()
        : null;
@endphp

@if ($business)
    <div role="status" class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 bg-red-700 px-4 py-2 text-sm text-white">
        <span>
            <strong class="tracking-[0.08em]">SUPER IMPOSE MODE</strong>
            · You are in the POS of <strong>{{ $business->name }}</strong> ({{ $business->acronym }}) as {{ auth()->user()->name }}
        </span>
        <span class="flex items-center gap-2">
            <a href="{{ \Filament\Facades\Filament::getPanel('admin')->getUrl() }}" class="rounded-full border border-white/60 px-3 py-1 font-semibold hover:bg-white/10">Open tenant admin</a>
            <form method="POST" action="{{ route('super-impose.exit') }}">
                @csrf
                <button type="submit" class="rounded-full bg-white px-3 py-1 font-semibold text-red-700">Exit Super Impose</button>
            </form>
        </span>
    </div>
@endif
