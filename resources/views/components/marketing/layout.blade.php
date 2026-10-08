@props(['title' => null, 'description' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-cream-50 font-sans text-ink-800 antialiased selection:bg-chili-500 selection:text-white">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-ink-900 focus:px-4 focus:py-2 focus:text-white">
            Skip to content
        </a>

        @include('marketing.partials.nav')

        <main id="main">
            {{ $slot }}
        </main>

        @include('marketing.partials.footer')
    </body>
</html>
