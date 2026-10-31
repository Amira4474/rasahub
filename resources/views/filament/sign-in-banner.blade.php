{{-- Shown across the top of the sign-in pages, so the business admin and the
     platform console cannot be mistaken for each other. Styled inline, as
     Filament does not load the website's styles. --}}
<div style="position: fixed; inset: 0 0 auto 0; z-index: 10; display: flex; justify-content: center; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: {{ $background }}; color: #fff; font-size: 0.875rem; font-weight: 600; letter-spacing: 0.04em; text-align: center;">
    <span style="text-transform: uppercase;">{{ $area }}</span>
    <span style="font-weight: 400; opacity: 0.85;">&middot; {{ $note }}</span>
</div>

<style>
    /* Keep the form clear of the banner on short screens. */
    .fi-simple-main-ctn { padding-top: 3rem; }
    @if ($tintPage ?? false)
        .fi-simple-layout { background: #1e1b4b; }
    @endif
</style>
