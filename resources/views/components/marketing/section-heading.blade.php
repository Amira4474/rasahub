@props(['eyebrow', 'title', 'dark' => false])

<div {{ $attributes->class('mx-auto max-w-2xl text-center') }} data-reveal>
    <p @class(['text-sm font-semibold uppercase tracking-[0.18em]', 'text-saffron-400' => $dark, 'text-chili-500' => ! $dark])>
        {{ $eyebrow }}
    </p>
    <h2 @class(['mt-3 font-display text-3xl font-extrabold tracking-tight text-balance sm:text-5xl', 'text-white' => $dark, 'text-ink-900' => ! $dark])>
        {{ $title }}
    </h2>
    @if ($slot->isNotEmpty())
        <p @class(['mt-5 text-lg text-pretty', 'text-cream-200/80' => $dark, 'text-ink-700' => ! $dark])>
            {{ $slot }}
        </p>
    @endif
</div>
