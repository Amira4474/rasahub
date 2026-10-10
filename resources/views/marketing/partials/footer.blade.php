<footer class="border-t border-cream-200 bg-cream-50">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.5fr_1fr_1fr] lg:px-8">
        <div>
            <x-marketing.logo class="text-ink-900" />
            <p class="mt-4 max-w-sm text-ink-700">
                Point of sale and restaurant management for cafés, restaurants and multi-outlet F&amp;B groups.
            </p>
        </div>

        <nav aria-label="Product">
            <h2 class="font-display text-sm font-bold uppercase tracking-wider text-ink-900">Product</h2>
            <ul class="mt-4 space-y-2.5 text-ink-700">
                <li><a href="#features" class="hover:text-chili-500">Features</a></li>
                <li><a href="#how-it-works" class="hover:text-chili-500">How it works</a></li>
                <li><a href="#pricing" class="hover:text-chili-500">Pricing</a></li>
                <li><a href="#faq" class="hover:text-chili-500">Questions</a></li>
            </ul>
        </nav>

        <nav aria-label="Company">
            <h2 class="font-display text-sm font-bold uppercase tracking-wider text-ink-900">Company</h2>
            <ul class="mt-4 space-y-2.5 text-ink-700">
                <li><a href="#about" class="hover:text-chili-500">About the system</a></li>
                <li><a href="#testimonials" class="hover:text-chili-500">Stories</a></li>
                <li><a href="{{ config('marketing.sign_in_url') }}" class="hover:text-chili-500">Sign in</a></li>
                <li><a href="{{ route('pos.login') }}" class="hover:text-chili-500">Staff POS sign-in</a></li>
            </ul>
        </nav>
    </div>

    <div class="border-t border-cream-200">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-6 sm:px-6 lg:px-8">
            <p class="text-sm text-ink-500">
                &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
            </p>

            <div class="flex shrink-0 items-center gap-3">
                <span class="text-sm text-ink-500">{{ config('app.version') }}</span>

                {{-- Platform staff only, so it is an icon rather than a named link. --}}
                <a href="{{ route('filament.platform.auth.login') }}" title="Platform sign-in" aria-label="Platform sign-in" class="grid size-9 place-items-center rounded-full text-ink-500 transition hover:bg-cream-200 hover:text-chili-500">
                    @svg('heroicon-o-shield-check', 'size-5')
                </a>
            </div>
        </div>
    </div>
</footer>
