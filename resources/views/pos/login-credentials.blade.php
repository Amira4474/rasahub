<x-pos.layout title="Sign in">
    <x-pos.sign-in-card step="2" heading="Sign in">
        <div class="mt-4 flex items-center justify-between gap-4 rounded-2xl bg-cream-100 px-5 py-3">
            <div class="min-w-0">
                <p class="truncate font-semibold text-ink-900">{{ $business->name }}</p>
                <p class="text-sm tracking-widest text-ink-500">{{ $business->acronym }}</p>
            </div>
            <a href="{{ route('pos.login') }}" class="shrink-0 text-sm font-semibold text-chili-500 underline underline-offset-4">Change</a>
        </div>

        <form method="POST" action="{{ route('pos.login.authenticate') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-semibold text-ink-900">Email address</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    class="mt-2 block w-full rounded-2xl border border-cream-300 bg-white px-5 py-3.5 text-ink-900 focus:border-chili-500 focus:outline-none focus:ring-4 focus:ring-chili-500/20"
                >
                @error('email')
                    <p id="email-error" class="mt-2 text-sm font-medium text-chili-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-ink-900">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-2 block w-full rounded-2xl border border-cream-300 bg-white px-5 py-3.5 text-ink-900 focus:border-chili-500 focus:outline-none focus:ring-4 focus:ring-chili-500/20"
                >
                @error('password')
                    <p class="mt-2 text-sm font-medium text-chili-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-full bg-chili-500 px-6 py-4 text-base font-semibold text-white transition hover:bg-chili-600 focus:outline-none focus:ring-4 focus:ring-chili-500/30">
                Sign in to the POS
            </button>
        </form>
    </x-pos.sign-in-card>
</x-pos.layout>
