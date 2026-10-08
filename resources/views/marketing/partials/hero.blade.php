@php
    $currency = config('marketing.currency');

    $channels = ['Dine-in', 'Takeaway', 'QR ordering', 'Kitchen display', 'Table service', 'Split bills', 'Multi-branch', 'Modifiers', 'Receipts', 'Staff roles'];
@endphp

<section class="relative overflow-hidden">
    {{-- Soft background colour, purely decorative. --}}
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute -right-32 -top-40 size-[34rem] rounded-full bg-saffron-400/25 blur-3xl"></div>
        <div class="absolute -left-40 top-64 size-[30rem] rounded-full bg-chili-400/15 blur-3xl"></div>
    </div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-16 px-4 pb-20 pt-16 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:px-8 lg:pb-28 lg:pt-24">
        <div>
            <p class="inline-flex items-center gap-2 rounded-full border border-cream-300 bg-white/70 px-4 py-1.5 text-sm font-medium text-ink-700">
                <span class="size-2 rounded-full bg-pandan-500"></span>
                POS and restaurant management for F&amp;B
            </p>

            <h1 class="mt-6 font-display text-5xl font-extrabold leading-[1.02] tracking-tight text-balance text-ink-900 sm:text-6xl xl:text-7xl">
                Service moves fast.
                <span class="relative whitespace-nowrap text-chili-500">
                    So should
                    <svg viewBox="0 0 300 12" preserveAspectRatio="none" class="absolute -bottom-2 left-0 h-3 w-full text-saffron-400" aria-hidden="true">
                        <path d="M2 9C60 2 130 2 298 7" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                    </svg>
                </span>
                your POS.
            </h1>

            <p class="mt-7 max-w-xl text-lg text-pretty text-ink-700 sm:text-xl">
                RasaHub connects the counter, the floor and the kitchen in one system. Take orders, route them to the right station and keep every outlet in view.
            </p>

            <div class="mt-9 flex flex-wrap items-center gap-4">
                <a href="#pricing" class="inline-flex items-center gap-2 rounded-full bg-chili-500 px-7 py-3.5 text-base font-semibold text-white shadow-lg shadow-chili-500/30 transition hover:-translate-y-0.5 hover:bg-chili-600">
                    See plans and pricing
                    @svg('heroicon-m-arrow-right', 'size-5')
                </a>
                <a href="#features" class="inline-flex items-center gap-2 rounded-full border border-cream-300 bg-white px-7 py-3.5 text-base font-semibold text-ink-900 transition hover:border-ink-900">
                    Explore features
                </a>
            </div>

            <dl class="mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-cream-200 pt-8">
                <div>
                    <dt class="text-sm text-ink-500">Outlets per business</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-ink-900">1 to 100+</dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-500">Staff roles built in</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-ink-900">8</dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-500">Software to install</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-ink-900">None</dd>
                </div>
            </dl>
        </div>

        {{-- Illustration of the product, built in HTML so it stays sharp. --}}
        <div class="relative mx-auto w-full max-w-xl lg:max-w-none" aria-hidden="true">
            <div class="rotate-1 rounded-[2rem] bg-ink-900 p-3 shadow-2xl shadow-ink-900/30">
                <div class="overflow-hidden rounded-[1.4rem] bg-cream-100">
                    <div class="flex items-center justify-between border-b border-cream-200 bg-white px-5 py-3">
                        <div class="flex items-center gap-2 text-sm font-semibold text-ink-900">
                            <span class="grid size-6 place-items-center rounded-md bg-chili-500 text-[10px] font-bold text-white">R</span>
                            Bangsar · Counter 1
                        </div>
                        <span class="rounded-full bg-pandan-100 px-2.5 py-1 text-xs font-semibold text-pandan-600">Online</span>
                    </div>

                    <div class="grid grid-cols-[1.25fr_1fr]">
                        <div class="p-4">
                            <div class="flex gap-2 text-xs font-semibold">
                                <span class="rounded-full bg-ink-900 px-3 py-1.5 text-white">Mains</span>
                                <span class="rounded-full bg-white px-3 py-1.5 text-ink-700">Drinks</span>
                                <span class="rounded-full bg-white px-3 py-1.5 text-ink-700">Sides</span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3">
                                @foreach ([['Nasi Lemak', '12.90', 'bg-saffron-100'], ['Mee Goreng', '11.50', 'bg-chili-100'], ['Roti Canai', '4.50', 'bg-pandan-100'], ['Satay (6)', '14.00', 'bg-cream-200']] as [$name, $price, $tone])
                                    <div class="rounded-xl bg-white p-3 shadow-sm">
                                        <div class="{{ $tone }} h-12 rounded-lg"></div>
                                        <p class="mt-2 text-xs font-semibold text-ink-900">{{ $name }}</p>
                                        <p class="text-xs text-ink-500">{{ $currency }} {{ $price }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex flex-col border-l border-cream-200 bg-white p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-bold text-ink-900">Table 7</p>
                                <span class="rounded-full bg-saffron-100 px-2 py-0.5 text-[10px] font-semibold text-saffron-500">Dine-in</span>
                            </div>
                            <ul class="mt-3 space-y-2.5 text-xs">
                                <li class="flex justify-between text-ink-900"><span>2 × Nasi Lemak</span><span>25.80</span></li>
                                <li class="-mt-1.5 text-[11px] text-ink-500">+ extra sambal</li>
                                <li class="flex justify-between text-ink-900"><span>1 × Satay (6)</span><span>14.00</span></li>
                                <li class="flex justify-between text-ink-900"><span>2 × Teh Tarik</span><span>7.00</span></li>
                            </ul>
                            <div class="mt-auto border-t border-dashed border-cream-300 pt-3">
                                <div class="flex justify-between text-sm font-bold text-ink-900">
                                    <span>Total</span><span>{{ $currency }} 46.80</span>
                                </div>
                                <div class="mt-3 rounded-lg bg-chili-500 py-2 text-center text-xs font-semibold text-white">Send to kitchen</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="absolute -left-4 bottom-10 w-48 -rotate-6 animate-float rounded-2xl border border-cream-200 bg-white p-4 shadow-xl shadow-ink-900/10 sm:-left-10">
                <div class="flex items-center justify-between text-[11px] font-semibold">
                    <span class="text-ink-500">HOT LINE</span>
                    <span class="rounded-full bg-chili-100 px-2 py-0.5 text-chili-600">Preparing</span>
                </div>
                <p class="mt-2 text-sm font-bold text-ink-900">Table 7 · #0142</p>
                <p class="mt-1 text-xs text-ink-700">2 × Nasi Lemak</p>
                <p class="text-xs text-ink-700">1 × Satay (6)</p>
                <div class="mt-3 h-1.5 rounded-full bg-cream-200"><div class="h-full w-2/3 rounded-full bg-chili-500"></div></div>
            </div>

            <div class="absolute -right-2 -top-6 flex animate-float-slow items-center gap-3 rounded-2xl border border-cream-200 bg-white px-4 py-3 shadow-xl shadow-ink-900/10 sm:-right-6">
                <span class="grid size-9 place-items-center rounded-full bg-pandan-100 text-pandan-600">
                    @svg('heroicon-m-check', 'size-5')
                </span>
                <div>
                    <p class="text-xs text-ink-500">Table 4 paid</p>
                    <p class="text-sm font-bold text-ink-900">{{ $currency }} 86.40</p>
                </div>
            </div>
        </div>
    </div>

    {{-- The list is rendered twice so the scrolling strip loops seamlessly. --}}
    <div class="relative border-y border-ink-900 bg-ink-900 py-4 text-cream-100">
        <div class="flex w-max animate-marquee gap-10 pr-10 font-display text-lg font-semibold">
            @foreach ([false, true] as $duplicate)
                @foreach ($channels as $channel)
                    <span class="flex items-center gap-10" @if ($duplicate) aria-hidden="true" @endif>
                        {{ $channel }}
                        <span class="text-saffron-400">✦</span>
                    </span>
                @endforeach
            @endforeach
        </div>
    </div>
</section>
