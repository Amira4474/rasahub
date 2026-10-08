<x-register.layout title="Invitation not valid">
    <h1 class="font-display text-3xl font-extrabold tracking-tight text-ink-900">This invitation is no longer valid</h1>

    <p class="mt-4 text-ink-700">
        The link may have expired, been used already, or been withdrawn. Invitation links work once.
    </p>
    <p class="mt-3 text-ink-700">
        Please ask the person who invited you for a new link. If you have already registered, you can sign in.
    </p>

    <div class="mt-8 flex flex-wrap gap-3">
        <a href="{{ config('marketing.sign_in_url') }}" class="rounded-full bg-chili-500 px-6 py-3 font-semibold text-white transition hover:bg-chili-600">Sign in</a>
        <a href="{{ route('home') }}" class="rounded-full border border-cream-300 px-6 py-3 font-semibold text-ink-900 transition hover:border-ink-900">Back to the website</a>
    </div>
</x-register.layout>
