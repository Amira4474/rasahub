@props(['heading'])

{{-- A dialog over the POS screen. --}}
<div class="fixed inset-0 z-50 flex items-end justify-center bg-ink-900/70 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="modal-heading">
    <div {{ $attributes->except('wire:click.self')->merge(['class' => 'max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-cream-50 p-6 text-ink-900 shadow-2xl']) }}>
        <h2 id="modal-heading" class="mb-4 font-display text-2xl font-bold">{{ $heading }}</h2>
        {{ $slot }}
    </div>
</div>
