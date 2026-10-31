<x-pos.layout title="Choose terminal">
    <x-pos.sign-in-card step="3" heading="Which terminal is this?">
        <p class="mt-3 text-ink-700">Signed in as {{ auth()->user()->name }}.</p>

        @if ($branches->isEmpty())
            <p class="mt-6 rounded-2xl bg-cream-100 p-5 text-ink-900">
                There is no POS terminal at your branches yet. Ask your manager to add one, or to assign you to a branch.
            </p>
            <form method="POST" action="{{ route('pos.logout') }}" class="mt-6">
                @csrf
                <button type="submit" class="w-full rounded-full border border-cream-300 px-6 py-3 font-semibold text-ink-900">Sign out</button>
            </form>
        @else
            <form method="POST" action="{{ route('pos.terminal.store') }}" class="mt-6 space-y-5">
                @csrf

                @foreach ($branches as $branch)
                    <fieldset>
                        <legend class="text-sm font-semibold uppercase tracking-[0.15em] text-ink-500">{{ $branch->name }}</legend>
                        <div class="mt-2 grid gap-2">
                            @foreach ($branch->terminals as $terminal)
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-cream-300 px-5 py-3.5 has-[:checked]:border-chili-500 has-[:checked]:bg-chili-100">
                                    <input type="radio" name="terminal" value="{{ $terminal->id }}" @checked($current === $terminal->id) required class="size-5 accent-chili-500">
                                    <span class="font-semibold text-ink-900">{{ $terminal->name }}</span>
                                    <span class="ml-auto text-sm tracking-widest text-ink-500">{{ $terminal->code }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                @error('terminal')
                    <p class="text-sm font-medium text-chili-600">{{ $message }}</p>
                @enderror

                <button type="submit" class="w-full rounded-full bg-chili-500 px-6 py-4 text-base font-semibold text-white transition hover:bg-chili-600 focus:outline-none focus:ring-4 focus:ring-chili-500/30">
                    Start
                </button>
            </form>
        @endif
    </x-pos.sign-in-card>
</x-pos.layout>
