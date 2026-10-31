<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex">
        <meta name="referrer" content="no-referrer">
        <meta name="theme-color" content="{{ $primary }}">
        <title>{{ $title }} · Table {{ $tableName }}</title>
        @if ($logo)
            <link rel="icon" href="{{ $logo }}">
        @endif
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- The restaurant's own colours, from Menu → QR ordering page in the admin. --}}
    <body class="min-h-dvh font-sans text-ink-900 antialiased" style="--qr-primary: {{ $primary }}; --qr-background: {{ $background }}; background-color: var(--qr-background);">
        <livewire:qr.menu :business="strtolower($business->acronym)" :token="$token" />
    </body>
</html>
