@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">

        <title>{{ $title }} · {{ config('app.name') }} POS</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-ink-900 font-sans text-ink-800 antialiased">
        <div class="flex flex-1 flex-col">
            {{ $slot }}
        </div>

        <footer class="border-t border-white/10">
            <div class="mx-auto flex max-w-5xl flex-wrap justify-between gap-x-6 gap-y-1 px-4 py-4 text-xs text-cream-200/60 sm:px-6">
                <span>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</span>
                <span>Point of Sale &middot; {{ config('app.version') }}</span>
            </div>
        </footer>
    </body>
</html>
