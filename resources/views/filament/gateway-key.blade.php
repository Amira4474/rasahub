<div x-data="{ copied: false }" style="display: grid; gap: 0.75rem;">
    <code style="display: block; padding: 0.75rem; border-radius: 0.5rem; background: rgb(107 114 128 / 0.12); font-size: 0.85rem; word-break: break-all;">{{ $key }}</code>
    <x-filament::button color="gray" icon="heroicon-m-clipboard" x-on:click="navigator.clipboard.writeText(@js($key)); copied = true">
        <span x-text="copied ? 'Copied' : 'Copy key'"></span>
    </x-filament::button>
    <p style="font-size: 0.8rem; color: #6b7280;">
        On the branch computer: <code>RASAHUB_URL={{ $url }} RASAHUB_GATEWAY_KEY=… npm start</code> in the <code>gateway</code> folder, or see gateway/README.md.
    </p>
</div>
