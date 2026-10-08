@php
    $currency = config('marketing.currency');
@endphp

<section id="pricing" class="py-24 sm:py-32">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-marketing.section-heading eyebrow="Pricing" title="One price per branch. No surprises.">
            No setup fee and no charge per order. Add or remove branches whenever you need to.
        </x-marketing.section-heading>

        <div class="mt-10 flex justify-center" data-reveal>
            <div class="inline-flex items-center gap-1 rounded-full border border-cream-200 bg-white p-1 text-sm font-semibold" role="group" aria-label="Billing period">
                <button type="button" data-billing="monthly" aria-pressed="true" class="rounded-full px-5 py-2 text-ink-700 transition aria-pressed:bg-ink-900 aria-pressed:text-white">
                    Monthly
                </button>
                <button type="button" data-billing="yearly" aria-pressed="false" class="flex items-center gap-2 rounded-full px-5 py-2 text-ink-700 transition aria-pressed:bg-ink-900 aria-pressed:text-white">
                    Yearly
                    <span class="rounded-full bg-saffron-400 px-2 py-0.5 text-xs text-ink-900">{{ config('marketing.yearly_saving') }}</span>
                </button>
            </div>
        </div>

        <div class="mt-12 grid items-start gap-6 lg:grid-cols-3">
            @foreach (config('marketing.plans') as $plan)
                <article
                    @class([
                        'relative flex flex-col rounded-3xl p-8',
                        'bg-ink-900 text-cream-100 shadow-2xl shadow-ink-900/25 lg:-mt-4 lg:pb-12 lg:pt-12' => $plan['featured'],
                        'border border-cream-200 bg-white' => ! $plan['featured'],
                    ])
                    data-reveal
                >
                    @if ($plan['featured'])
                        <span class="absolute -top-3.5 left-8 rounded-full bg-chili-500 px-4 py-1 text-xs font-bold uppercase tracking-wider text-white">Most popular</span>
                    @endif

                    <h3 @class(['font-display text-2xl font-extrabold', 'text-white' => $plan['featured'], 'text-ink-900' => ! $plan['featured']])>
                        {{ $plan['name'] }}
                    </h3>
                    <p @class(['mt-1.5 text-sm', 'text-cream-200/75' => $plan['featured'], 'text-ink-500' => ! $plan['featured']])>
                        {{ $plan['tagline'] }}
                    </p>

                    <div class="mt-7 min-h-20">
                        @if ($plan['monthly'] === null)
                            <p @class(['font-display text-5xl font-extrabold tracking-tight', 'text-white' => $plan['featured'], 'text-ink-900' => ! $plan['featured']])>
                                Custom
                            </p>
                            <p @class(['mt-1 text-sm', 'text-cream-200/75' => $plan['featured'], 'text-ink-500' => ! $plan['featured']])>
                                Priced for your group
                            </p>
                        @else
                            <p @class(['flex items-baseline gap-1.5 font-display font-extrabold tracking-tight', 'text-white' => $plan['featured'], 'text-ink-900' => ! $plan['featured']])>
                                <span class="text-xl">{{ $currency }}</span>
                                <span class="text-5xl" data-price data-monthly="{{ $plan['monthly'] }}" data-yearly="{{ $plan['yearly'] }}">{{ $plan['monthly'] }}</span>
                            </p>
                            <p @class(['mt-1 text-sm', 'text-cream-200/75' => $plan['featured'], 'text-ink-500' => ! $plan['featured']])>
                                <span data-billing-note="monthly">per branch, per month</span>
                                <span data-billing-note="yearly" hidden>per branch, per month, billed yearly</span>
                            </p>
                        @endif
                    </div>

                    <a
                        href="#get-started"
                        @class([
                            'mt-6 rounded-full px-6 py-3 text-center text-sm font-semibold transition',
                            'bg-chili-500 text-white hover:bg-chili-400' => $plan['featured'],
                            'bg-cream-100 text-ink-900 hover:bg-ink-900 hover:text-white' => ! $plan['featured'],
                        ])
                    >
                        {{ $plan['cta'] }}
                    </a>

                    <ul class="mt-8 space-y-3.5 text-sm">
                        @foreach ($plan['includes'] as $item)
                            <li class="flex gap-3">
                                @svg('heroicon-m-check-circle', $plan['featured'] ? 'size-5 shrink-0 text-saffron-400' : 'size-5 shrink-0 text-pandan-500')
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </div>
</section>
