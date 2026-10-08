<section id="faq" class="bg-cream-100 py-24 sm:py-32">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <x-marketing.section-heading eyebrow="Questions" title="Before you ask" />

        <div class="mt-12 space-y-3">
            @foreach (config('marketing.faqs') as $faq)
                <details class="group rounded-2xl border border-cream-200 bg-white px-6 open:shadow-lg open:shadow-ink-900/5" data-reveal>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-6 py-5 font-display text-lg font-bold text-ink-900 [&::-webkit-details-marker]:hidden">
                        {{ $faq['question'] }}
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-cream-100 text-ink-900 transition group-open:rotate-45 group-open:bg-chili-500 group-open:text-white">
                            @svg('heroicon-m-plus', 'size-5')
                        </span>
                    </summary>
                    <p class="pb-6 text-pretty text-ink-700">{{ $faq['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>
