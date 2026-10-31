@php($p = $this->preview())

{{-- Filament does not load the website's styles, so the preview is styled
     inline, to look like the customer's page. --}}
<x-filament-panels::page>
    <style>
        .rh-qr { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
        @media (min-width: 1100px) { .rh-qr { grid-template-columns: minmax(0, 1fr) 24rem; } }
        .rh-qr-preview { position: sticky; top: 5rem; }
        .rh-phone { width: 100%; max-width: 24rem; margin: 0 auto; border: 10px solid #1d1714; border-radius: 2.25rem; overflow: hidden; box-shadow: 0 20px 40px rgb(0 0 0 / 0.25); }
        .rh-screen { height: 40rem; overflow-y: auto; font-family: ui-sans-serif, system-ui, sans-serif; color: #1d1714; }
        .rh-chip { flex-shrink: 0; padding: 0.4rem 0.9rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; background: #fff; }
        .rh-card { display: flex; gap: 0.75rem; padding: 0.75rem; border-radius: 1rem; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / 0.06); }
    </style>

    <div class="rh-qr">
        <div>
            {{ $this->form }}
        </div>

        <div class="rh-qr-preview" aria-label="Preview of the QR ordering page">
            <p style="margin-bottom: 0.5rem; text-align: center; font-size: 0.8rem; opacity: 0.7;">Preview · follows your changes before you save</p>
            <div class="rh-phone">
                <div class="rh-screen" style="background-color: {{ $p['background'] }};">
                    @if ($p['banner'])
                        <img src="{{ $p['banner'] }}" alt="" style="display: block; width: 100%; height: 9rem; object-fit: cover;">
                    @else
                        <div style="height: 5rem; background-color: {{ $p['primary'] }};"></div>
                    @endif

                    <div style="padding: 0 1rem; position: relative;">
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                            @if ($p['logo'])
                                <img src="{{ $p['logo'] }}" alt="" style="width: 4.5rem; height: 4.5rem; margin-top: -2.25rem; flex-shrink: 0; border: 4px solid #fff; border-radius: 1rem; background: #fff; object-fit: contain; box-shadow: 0 2px 6px rgb(0 0 0 / 0.15);">
                            @endif
                            <div style="min-width: 0; padding-top: 0.75rem;">
                                <p style="font-size: 1.35rem; font-weight: 800; line-height: 1.2;">{{ $p['title'] }}</p>
                                <p style="font-size: 0.8rem; opacity: 0.75;">Table <strong>T1</strong></p>
                            </div>
                        </div>
                        @if (filled($p['welcome']))
                            <p style="margin-top: 0.6rem; font-size: 0.85rem; opacity: 0.85;">{{ $p['welcome'] }}</p>
                        @endif
                        <div style="margin-top: 0.6rem; display: flex; gap: 0.4rem;">
                            <span class="rh-chip" style="border: 1px solid rgb(0 0 0 / 0.1);">Call staff</span>
                            <span class="rh-chip" style="border: 1px solid rgb(0 0 0 / 0.1);">Ask for the bill</span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.4rem; overflow-x: auto; padding: 0.9rem 1rem;">
                        @foreach ($p['categories'] as $index => $category)
                            <span class="rh-chip" style="{{ $index === 0 ? 'background-color: '.$p['primary'].'; color: #fff;' : '' }}">{{ $category }}</span>
                        @endforeach
                    </div>

                    <div style="display: grid; gap: 0.6rem; padding: 0 1rem;">
                        @foreach ($p['products'] as $product)
                            <div class="rh-card">
                                @if ($p['photos'])
                                    @if ($product['image'])
                                        <img src="{{ $product['image'] }}" alt="" style="width: 4.5rem; height: 4.5rem; flex-shrink: 0; border-radius: 0.75rem; object-fit: cover;">
                                    @else
                                        <div style="width: 4.5rem; height: 4.5rem; flex-shrink: 0; border-radius: 0.75rem; background: rgb(0 0 0 / 0.06);"></div>
                                    @endif
                                @endif
                                <div style="display: flex; min-width: 0; flex: 1; flex-direction: column;">
                                    <span style="font-weight: 600; font-size: 0.9rem;">{{ $product['name'] }}</span>
                                    @if ($p['descriptions'] && $product['description'])
                                        <span style="font-size: 0.75rem; opacity: 0.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ $product['description'] }}</span>
                                    @endif
                                    <span style="margin-top: auto; display: flex; align-items: center; justify-content: space-between; padding-top: 0.4rem;">
                                        <span style="font-weight: 600; font-size: 0.85rem;">{{ $p['currency'] }} {{ number_format((float) $product['price'], 2) }}</span>
                                        <span style="display: grid; place-items: center; width: 1.8rem; height: 1.8rem; border-radius: 9999px; color: #fff; font-weight: 700; background-color: {{ $p['primary'] }};">+</span>
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if (filled($p['footer']))
                        <p style="margin-top: 1.5rem; padding: 0 1rem; text-align: center; font-size: 0.75rem; opacity: 0.6;">{!! nl2br(e($p['footer'])) !!}</p>
                    @endif

                    <div style="position: sticky; bottom: 0; padding: 1rem; margin-top: 1rem;">
                        <div style="display: flex; justify-content: space-between; border-radius: 9999px; padding: 0.8rem 1.1rem; color: #fff; font-weight: 600; font-size: 0.85rem; background-color: {{ $p['primary'] }};">
                            <span>2 items</span><span>View basket</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
