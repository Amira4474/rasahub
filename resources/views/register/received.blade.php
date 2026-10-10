<x-register.layout title="Registration received">
    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-pandan-500">Registration received</p>
    <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight text-ink-900">{{ $registration['business'] }} is registered</h1>

    <p class="mt-4 text-ink-700">
        Your business and your Owner account have been created. You cannot sign in yet: {{ config('app.name') }} first activates your subscription.
    </p>
    <p class="mt-3 text-ink-700">
        Once it is active, sign in with <span class="font-semibold text-ink-900">{{ $registration['email'] }}</span> and the password you just chose.
    </p>

    <div class="mt-8 flex flex-wrap gap-3">
        <a href="{{ config('marketing.sign_in_url') }}" class="rounded-full bg-chili-500 px-6 py-3 font-semibold text-white transition hover:bg-chili-600">Go to sign in</a>
        <a href="{{ route('home') }}" class="rounded-full border border-cream-300 px-6 py-3 font-semibold text-ink-900 transition hover:border-ink-900">Back to the website</a>
    </div>
</x-register.layout>
