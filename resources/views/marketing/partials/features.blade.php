@php
    $features = config('marketing.features');
    $tones = ['bg-chili-100 text-chili-600', 'bg-saffron-100 text-saffron-500', 'bg-pandan-100 text-pandan-600'];
@endphp

<section id="features" class="py-24 sm:py-32">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-marketing.section-heading eyebrow="Features" title="Everything between the order and the receipt">
            One system for the counter, the floor, the kitchen and the back office, so nothing gets retyped and nothing gets lost.
        </x-marketing.section-heading>

        <div class="mt-16 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($features as $feature)
                {{-- The first and last cards are wide, which gives the grid its shape. --}}
                <article
                    @class([
                        'group relative flex flex-col rounded-3xl border border-cream-200 bg-white p-7 transition duration-300 hover:-translate-y-1 hover:border-cream-300 hover:shadow-xl hover:shadow-ink-900/5',
                        'lg:col-span-2' => $loop->first || $loop->last,
                    ])
                    data-reveal
                >
                    <div class="flex items-start justify-between gap-4">
                        <span class="{{ $tones[$loop->index % count($tones)] }} grid size-12 place-items-center rounded-2xl transition group-hover:scale-110">
                            @svg($feature['icon'], 'size-6')
                        </span>
                        @if ($feature['live'])
                            <span class="rounded-full bg-pandan-100 px-3 py-1 text-xs font-semibold text-pandan-600">Live</span>
                        @else
                            <span class="rounded-full bg-cream-100 px-3 py-1 text-xs font-semibold text-ink-500">Coming soon</span>
                        @endif
                    </div>
                    <h3 class="mt-6 font-display text-xl font-bold text-ink-900">{{ $feature['title'] }}</h3>
                    <p class="mt-2 text-pretty text-ink-700">{{ $feature['body'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
