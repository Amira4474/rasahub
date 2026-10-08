<section id="how-it-works" class="bg-ink-900 py-24 text-cream-100 sm:py-32">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-marketing.section-heading eyebrow="How it works" title="Follow one order through the house" dark>
            Every order takes the same path, whoever keys it in. That is what keeps the kitchen, the cashier and your reports in agreement.
        </x-marketing.section-heading>

        <ol class="mt-16 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            @foreach (config('marketing.flow') as $step)
                <li class="relative rounded-2xl border border-white/10 bg-white/5 p-5" data-reveal>
                    <span class="font-display text-sm font-bold text-saffron-400">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="mt-3 font-display text-lg font-bold text-white">{{ $step['title'] }}</h3>
                    <p class="mt-1.5 text-sm text-cream-200/75">{{ $step['body'] }}</p>
                    @unless ($loop->last)
                        <span class="absolute -right-3.5 top-1/2 z-10 hidden size-6 -translate-y-1/2 place-items-center rounded-full bg-chili-500 text-white lg:grid" aria-hidden="true">
                            @svg('heroicon-m-chevron-right', 'size-4')
                        </span>
                    @endunless
                </li>
            @endforeach
        </ol>

        <div class="mt-20 grid gap-x-12 gap-y-10 md:grid-cols-2">
            @foreach (config('marketing.details') as $detail)
                <div class="flex gap-5" data-reveal>
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white/10 text-saffron-400">
                        @svg($detail['icon'], 'size-6')
                    </span>
                    <div>
                        <h3 class="font-display text-xl font-bold text-white">{{ $detail['title'] }}</h3>
                        <p class="mt-2 text-pretty text-cream-200/75">{{ $detail['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
