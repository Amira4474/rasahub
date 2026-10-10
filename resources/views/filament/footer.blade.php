{{-- Filament does not load the website's styles, so this is styled inline. --}}
<footer style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.25rem 1.5rem; padding: 1rem 1.5rem; font-size: 0.75rem; line-height: 1.25rem; color: #6b7280; border-top: 1px solid rgb(107 114 128 / 0.2);">
    <span>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</span>
    <span>{{ $area }} &middot; {{ config('app.version') }}</span>
</footer>
