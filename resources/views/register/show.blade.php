@php
    $input = 'mt-2 block w-full rounded-2xl border border-cream-300 bg-white px-5 py-3.5 text-ink-900 focus:border-chili-500 focus:outline-none focus:ring-4 focus:ring-chili-500/20';
    $label = 'block text-sm font-semibold text-ink-900';
    $error = 'mt-2 text-sm font-medium text-chili-600';
@endphp

<x-register.layout title="Set up your business">
    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-chili-500">You are invited</p>
    <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight text-ink-900">Set up your business</h1>
    <p class="mt-3 text-ink-700">
        This creates your business on {{ config('app.name') }} and your own Owner account.
    </p>

    <dl class="mt-6 grid gap-4 rounded-2xl bg-cream-100 px-5 py-4 sm:grid-cols-2">
        <div class="min-w-0">
            <dt class="text-sm text-ink-500">Your sign-in email</dt>
            <dd class="truncate font-semibold text-ink-900">{{ $invitation->email }}</dd>
        </div>
        <div>
            <dt class="text-sm text-ink-500">Plan</dt>
            <dd class="font-semibold text-ink-900">{{ $invitation->plan->label() }}</dd>
        </div>
    </dl>

    <form method="POST" action="{{ route('register.store', ['token' => $token]) }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <label for="business_name" class="{{ $label }}">Business name</label>
            <input id="business_name" name="business_name" type="text" value="{{ old('business_name') }}" required autofocus maxlength="255" autocomplete="organization"
                @error('business_name') aria-invalid="true" aria-describedby="business_name-error" @enderror class="{{ $input }}">
            @error('business_name')
                <p id="business_name-error" class="{{ $error }}">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="acronym" class="{{ $label }}">Company acronym</label>
            <input id="acronym" name="acronym" type="text" value="{{ old('acronym') }}" required minlength="2" maxlength="10" autocapitalize="characters" spellcheck="false" aria-describedby="acronym-help @error('acronym') acronym-error @enderror"
                @error('acronym') aria-invalid="true" @enderror class="{{ $input }} uppercase tracking-widest">
            <p id="acronym-help" class="mt-2 text-sm text-ink-500">2 to 10 letters or digits, for example KKB. Your staff type this to sign in to the POS, and it cannot be changed later.</p>
            @error('acronym')
                <p id="acronym-error" class="{{ $error }}">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="name" class="{{ $label }}">Your name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255" autocomplete="name"
                @error('name') aria-invalid="true" aria-describedby="name-error" @enderror class="{{ $input }}">
            @error('name')
                <p id="name-error" class="{{ $error }}">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="password" class="{{ $label }}">Password</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror class="{{ $input }}">
            </div>
            <div>
                <label for="password_confirmation" class="{{ $label }}">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="{{ $input }}">
            </div>
            @error('password')
                <p id="password-error" class="{{ $error }} sm:col-span-2 sm:-mt-3">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-full bg-chili-500 px-6 py-4 text-base font-semibold text-white transition hover:bg-chili-600 focus:outline-none focus:ring-4 focus:ring-chili-500/30">
            Create my business
        </button>
    </form>
</x-register.layout>
