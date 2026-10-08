<x-pos.layout title="Home">
    <header class="border-b border-white/10">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <div class="flex items-center gap-4">
                <x-marketing.logo class="text-white" />
                <span class="rounded-full bg-white/10 px-3 py-1 text-sm font-semibold text-cream-100">{{ $business->name }}</span>
            </div>

            <div class="flex items-center gap-4 text-sm text-cream-100">
                <span>
                    {{ $user->name }}
                    <span class="text-cream-200/60">· {{ $user->getRoleNames()->implode(', ') }}</span>
                </span>
                <form method="POST" action="{{ route('pos.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full bg-white px-4 py-2 font-semibold text-ink-900 transition hover:bg-cream-200">Sign out</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-extrabold tracking-tight text-white">Welcome, {{ $user->name }}</h1>
        <p class="mt-2 text-cream-200/75">You are signed in to the POS. Selling is not available yet; this page shows where you can work.</p>

        <h2 class="mt-10 text-sm font-semibold uppercase tracking-[0.18em] text-saffron-400">Your branches</h2>

        @if ($branches->isEmpty())
            <p class="mt-4 rounded-2xl border border-white/10 bg-white/5 p-6 text-cream-100">
                You are not assigned to a branch yet. Ask your manager to assign you to one.
            </p>
        @else
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($branches as $branch)
                    <section class="rounded-2xl bg-cream-50 p-6">
                        <div class="flex items-baseline justify-between gap-4">
                            <h3 class="font-display text-xl font-bold text-ink-900">{{ $branch->name }}</h3>
                            <span class="text-sm tracking-widest text-ink-500">{{ $branch->code }}</span>
                        </div>

                        @if ($branch->terminals->isEmpty())
                            <p class="mt-3 text-sm text-ink-500">No POS terminals set up.</p>
                        @else
                            <ul class="mt-3 flex flex-wrap gap-2">
                                @foreach ($branch->terminals as $terminal)
                                    <li class="rounded-full bg-cream-200 px-3 py-1 text-sm font-medium text-ink-800">{{ $terminal->name }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </main>
</x-pos.layout>
