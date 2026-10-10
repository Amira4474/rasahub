@php
    $input = 'mt-2 block w-full rounded-2xl border border-cream-300 bg-white px-5 py-3.5 text-ink-900 focus:border-chili-500 focus:outline-none focus:ring-4 focus:ring-chili-500/20';
@endphp

<x-register.layout title="Register your business">
    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-chili-500">Step 1 of 2</p>
    <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight text-ink-900">Register your business</h1>
    <p class="mt-3 text-ink-700">
        New businesses join {{ config('app.name') }} by invitation. Enter the token you were given to continue.
    </p>

    <form method="POST" action="{{ route('register.token') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <label for="token" class="block text-sm font-semibold text-ink-900">Invitation token</label>
            <input id="token" name="token" type="text" value="{{ old('token') }}" required autofocus maxlength="{{ \App\Models\TenantInvitation::TOKEN_MAX_LENGTH }}" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="token-help @error('token') token-error @enderror"
                @error('token') aria-invalid="true" @enderror class="{{ $input }} font-mono uppercase tracking-widest">
            <p id="token-help" class="mt-2 text-sm text-ink-500">A token works once. Capitals or not makes no difference.</p>
            @error('token')
                <p id="token-error" class="mt-2 text-sm font-medium text-chili-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-full bg-chili-500 px-6 py-4 text-base font-semibold text-white transition hover:bg-chili-600 focus:outline-none focus:ring-4 focus:ring-chili-500/30">
            Continue
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-500">
        Already registered? <a href="{{ config('marketing.sign_in_url') }}" class="font-semibold text-chili-600 underline-offset-4 hover:underline">Sign in</a>
    </p>
</x-register.layout>
