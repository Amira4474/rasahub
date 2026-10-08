@php
    $business = app(\App\Tenancy\SuperImpose::class)->isActive()
        ? app(\App\Tenancy\TenantContext::class)->business()
        : null;
@endphp

@if ($business)
    {{-- Filament's own top bar and sidebar are moved down to make room, so
         this bar stays in view on every tenant page. --}}
    <style>
        :root {
            --super-impose-height: 2.75rem;
        }

        .fi-topbar-ctn {
            top: var(--super-impose-height);
        }

        @media (min-width: 1024px) {
            .fi-body-has-topbar .fi-sidebar {
                top: calc(var(--topbar-height) + var(--super-impose-height));
                height: calc(100dvh - var(--topbar-height) - var(--super-impose-height));
            }
        }
    </style>

    <div
        role="status"
        style="position: sticky; top: 0; z-index: 40; height: var(--super-impose-height); display: flex; align-items: center; justify-content: center; gap: 1rem; padding: 0 1rem; background: #b91c1c; color: #fff; font-size: 0.875rem; white-space: nowrap;"
    >
        <span style="overflow: hidden; text-overflow: ellipsis;">
            <strong style="letter-spacing: 0.08em;">SUPER IMPOSE MODE</strong>
            &nbsp;·&nbsp; You are inside <strong>{{ $business->name }}</strong> ({{ $business->acronym }})
            as {{ auth()->user()->name }}
        </span>

        <form method="POST" action="{{ route('super-impose.exit') }}" style="flex-shrink: 0;">
            @csrf
            <button type="submit" style="padding: 0.3rem 0.9rem; border-radius: 9999px; background: #fff; color: #b91c1c; font-weight: 600; cursor: pointer;">
                Exit Super Impose
            </button>
        </form>
    </div>
@endif
