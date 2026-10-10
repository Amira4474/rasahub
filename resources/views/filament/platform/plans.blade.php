@php
    // Filament does not load the website's styles, so the table is styled inline.
    $cell = 'padding: 0.75rem 1rem; border-top: 1px solid rgb(107 114 128 / 0.2); text-align: center;';
    $rowHeading = 'padding: 0.75rem 1rem; border-top: 1px solid rgb(107 114 128 / 0.2); text-align: left; font-weight: 500;';
    $groupHeading = 'padding: 1.25rem 1rem 0.5rem; text-align: left; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280;';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">What each plan allows</x-slot>
        <x-slot name="description">
            The limits apply as soon as a tenant is on the plan: once one is reached, the tenant cannot add another branch, POS terminal or staff account. Nothing a tenant already has is removed. A tenant's plan is changed under Subscriptions.
        </x-slot>

        <div style="overflow-x: auto;">
            <table style="width: 100%; min-width: 40rem; border-collapse: collapse; font-size: 0.875rem;">
                <thead>
                    <tr>
                        <th scope="col" style="padding: 0.75rem 1rem; text-align: left;"><span class="sr-only">Limit or feature</span></th>
                        @foreach ($plans as $plan)
                            @php($price = $prices[$plan->value]['monthly'] ?? null)
                            <th scope="col" style="padding: 0.75rem 1rem; text-align: center; vertical-align: top;">
                                <div style="font-size: 1.125rem; font-weight: 700;">{{ $plan->label() }}</div>
                                <div style="margin-top: 0.125rem; font-weight: 400; color: #6b7280;">
                                    {{ $price === null ? 'Custom price' : config('tenancy.registration.currency').' '.$price.' per branch, per month' }}
                                </div>
                                <div style="margin-top: 0.5rem;">
                                    <x-filament::badge color="gray">{{ $tenants[$plan->value] ?? 0 }} {{ ($tenants[$plan->value] ?? 0) === 1 ? 'tenant' : 'tenants' }}</x-filament::badge>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <th scope="colgroup" colspan="{{ count($plans) + 1 }}" style="{{ $groupHeading }}">Limits</th>
                    </tr>
                    @foreach ($limits as $key => $limit)
                        <tr>
                            <th scope="row" style="{{ $rowHeading }}">{{ $limit['label'] }}</th>
                            @foreach ($plans as $plan)
                                <td style="{{ $cell }}">{{ $plan->limit($key) ?? 'Unlimited' }}</td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr>
                        <th scope="colgroup" colspan="{{ count($plans) + 1 }}" style="{{ $groupHeading }}">Features</th>
                    </tr>
                    @foreach ($features as $key => $feature)
                        <tr>
                            <th scope="row" style="{{ $rowHeading }}">
                                {{ $feature['label'] }}
                                @unless ($feature['live'])
                                    <span style="margin-left: 0.5rem; font-size: 0.75rem; font-weight: 400; color: #6b7280;">Not built yet</span>
                                @endunless
                            </th>
                            @foreach ($plans as $plan)
                                <td style="{{ $cell }}">
                                    @if ($plan->includes($key))
                                        <x-filament::badge color="success" style="display: inline-flex;">Included</x-filament::badge>
                                    @else
                                        <span style="color: #9ca3af;" aria-label="Not included">&mdash;</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr>
                        <th scope="colgroup" colspan="{{ count($plans) + 1 }}" style="{{ $groupHeading }}">Support</th>
                    </tr>
                    <tr>
                        <th scope="row" style="{{ $rowHeading }}">Support</th>
                        @foreach ($plans as $plan)
                            <td style="{{ $cell }}">{{ $plan->support() }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
