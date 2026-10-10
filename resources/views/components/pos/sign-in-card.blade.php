@props(['step', 'heading'])

<div class="flex flex-1 flex-col items-center justify-center px-4 py-12">
    <x-marketing.logo class="text-white" />
    <p class="mt-2 text-sm font-semibold uppercase tracking-[0.2em] text-saffron-400">Staff POS</p>

    <div class="mt-8 w-full max-w-md rounded-3xl bg-cream-50 p-8 shadow-2xl sm:p-10">
        <p class="text-sm font-semibold text-chili-500">Step {{ $step }} of 2</p>
        <h1 class="mt-1 font-display text-3xl font-extrabold tracking-tight text-ink-900">{{ $heading }}</h1>

        {{ $slot }}
    </div>

    <p class="mt-8 text-sm text-cream-200/70">
        Managing the business instead?
        <a href="{{ config('marketing.sign_in_url') }}" class="font-semibold text-white underline underline-offset-4">Go to admin sign-in</a>
    </p>
</div>
