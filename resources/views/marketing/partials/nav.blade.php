@php
    $links = [
        '#features' => 'Features',
        '#how-it-works' => 'How it works',
        '#pricing' => 'Pricing',
        '#testimonials' => 'Stories',
        '#about' => 'About',
    ];
@endphp

<header class="sticky top-0 z-40 border-b border-cream-200/70 bg-cream-50/85 backdrop-blur-md">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
        <x-marketing.logo class="text-ink-900" />

        <nav class="hidden items-center gap-8 text-sm font-medium text-ink-700 lg:flex" aria-label="Main">
            @foreach ($links as $href => $label)
                <a href="{{ $href }}" class="transition hover:text-chili-500">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-3 lg:flex">
            <a href="{{ config('marketing.sign_in_url') }}" class="rounded-full px-4 py-2 text-sm font-semibold text-ink-900 transition hover:bg-cream-200">
                Sign in
            </a>
            <a href="#pricing" class="rounded-full bg-ink-900 px-5 py-2 text-sm font-semibold text-white transition hover:bg-chili-500">
                See pricing
            </a>
        </div>

        <details class="group relative lg:hidden" data-mobile-menu>
            <summary class="grid size-10 cursor-pointer list-none place-items-center rounded-full text-ink-900 transition hover:bg-cream-200 [&::-webkit-details-marker]:hidden">
                <span class="sr-only">Menu</span>
                @svg('heroicon-o-bars-3', 'size-6 group-open:hidden')
                @svg('heroicon-o-x-mark', 'hidden size-6 group-open:block')
            </summary>
            <div class="absolute right-0 top-12 w-64 rounded-2xl border border-cream-200 bg-white p-3 shadow-xl shadow-ink-900/10">
                <nav class="flex flex-col text-sm font-medium text-ink-800" aria-label="Mobile">
                    @foreach ($links as $href => $label)
                        <a href="{{ $href }}" class="rounded-lg px-3 py-2.5 hover:bg-cream-100">{{ $label }}</a>
                    @endforeach
                    <a href="{{ config('marketing.sign_in_url') }}" class="mt-2 rounded-full bg-ink-900 px-4 py-2.5 text-center font-semibold text-white">
                        Sign in
                    </a>
                </nav>
            </div>
        </details>
    </div>
</header>
