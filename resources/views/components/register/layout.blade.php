@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <meta name="referrer" content="no-referrer">

        <title>{{ $title }} · {{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-cream-100 font-sans text-ink-800 antialiased">
        <div class="flex min-h-screen flex-col items-center px-4 py-12">
            <a href="{{ route('home') }}" aria-label="{{ config('app.name') }} home">
                <x-marketing.logo class="text-ink-900" />
            </a>

            <div class="mt-8 w-full max-w-xl rounded-3xl border border-cream-200 bg-white p-8 shadow-xl shadow-ink-900/5 sm:p-10">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
