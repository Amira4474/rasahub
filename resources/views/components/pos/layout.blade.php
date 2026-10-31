@props(['title', 'fit' => false])

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
    {{-- "fit" keeps everything inside the window, as on the POS screen:
         the panels scroll on their own and the page never does. --}}
    <body @class(['flex flex-col bg-ink-900 font-sans text-ink-800 antialiased', 'h-dvh overflow-hidden' => $fit, 'min-h-screen' => ! $fit])>
        <x-pos.super-impose-banner />

        <div class="flex min-h-0 flex-1 flex-col">
            {{ $slot }}
        </div>

        <footer class="border-t border-white/10">
            <div @class(['mx-auto flex flex-wrap justify-between gap-x-6 gap-y-1 px-4 text-xs text-cream-200/60 sm:px-6', 'py-1.5' => $fit, 'max-w-5xl py-4' => ! $fit])>
                <span>&copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.</span>
                <span>Point of Sale &middot; {{ config('app.version') }}</span>
            </div>
        </footer>
    </body>
</html>
