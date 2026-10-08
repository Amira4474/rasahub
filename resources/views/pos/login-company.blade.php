<x-pos.layout title="Sign in">
    <x-pos.sign-in-card step="1" heading="Which company?">
        <p class="mt-3 text-ink-700">Enter your company acronym. Ask your manager if you are not sure what it is.</p>

        <form method="POST" action="{{ route('pos.login.company') }}" class="mt-8">
            @csrf

            <label for="acronym" class="block text-sm font-semibold text-ink-900">Company acronym</label>
            <input
                id="acronym"
                name="acronym"
                type="text"
                value="{{ old('acronym') }}"
                maxlength="10"
                required
                autofocus
                autocomplete="organization"
                autocapitalize="characters"
                spellcheck="false"
                @error('acronym') aria-invalid="true" aria-describedby="acronym-error" @enderror
                class="mt-2 block w-full rounded-2xl border border-cream-300 bg-white px-5 py-4 font-display text-2xl font-bold uppercase tracking-[0.2em] text-ink-900 placeholder:font-sans placeholder:text-base placeholder:font-normal placeholder:normal-case placeholder:tracking-normal placeholder:text-ink-500 focus:border-chili-500 focus:outline-none focus:ring-4 focus:ring-chili-500/20"
                placeholder="For example KKB"
            >
            @error('acronym')
                <p id="acronym-error" class="mt-2 text-sm font-medium text-chili-600">{{ $message }}</p>
            @enderror

            <button type="submit" class="mt-6 w-full rounded-full bg-chili-500 px-6 py-4 text-base font-semibold text-white transition hover:bg-chili-600 focus:outline-none focus:ring-4 focus:ring-chili-500/30">
                Continue
            </button>
        </form>
    </x-pos.sign-in-card>
</x-pos.layout>
