@php
    $tones = ['bg-saffron-100', 'bg-chili-100', 'bg-pandan-100'];
@endphp

<section id="testimonials" class="bg-cream-100 py-24 sm:py-32">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-marketing.section-heading eyebrow="Stories" title="Made for the people on shift" />

        <div class="mt-16 grid gap-6 lg:grid-cols-3">
            @foreach (config('marketing.testimonials') as $testimonial)
                <figure @class(['flex flex-col rounded-3xl border border-cream-200 bg-white p-8', 'lg:translate-y-8' => $loop->iteration === 2]) data-reveal>
                    <div class="flex gap-1 text-saffron-400" aria-hidden="true">
                        @for ($star = 0; $star < 5; $star++)
                            @svg('heroicon-s-star', 'size-5')
                        @endfor
                    </div>
                    <blockquote class="mt-5 grow font-display text-xl font-semibold leading-snug text-pretty text-ink-900">
                        “{{ $testimonial['quote'] }}”
                    </blockquote>
                    <figcaption class="mt-8 flex items-center gap-4">
                        <span class="{{ $tones[$loop->index % count($tones)] }} grid size-12 place-items-center rounded-full font-display text-lg font-bold text-ink-900" aria-hidden="true">
                            {{ substr($testimonial['name'], 0, 1) }}
                        </span>
                        <span>
                            <span class="block font-semibold text-ink-900">{{ $testimonial['name'] }}</span>
                            <span class="block text-sm text-ink-500">{{ $testimonial['role'] }}</span>
                        </span>
                    </figcaption>
                </figure>
            @endforeach
        </div>

        @if (config('marketing.testimonials_are_samples'))
            <p class="mt-16 text-center text-sm text-ink-500">
                Illustrative quotes. Real customer stories will replace these at launch.
            </p>
        @endif
    </div>
</section>
