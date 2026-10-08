<?php

namespace App\Http\Middleware;

use App\Tenancy\SuperImpose;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the platform panel. While Super Impose is active the platform user
 * is inside a tenant, so they are kept in the tenant panel until they exit.
 * This keeps the two contexts from ever being mixed on one screen.
 */
class LeavePlatformWhileSuperImposing
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(SuperImpose::class)->isActive()) {
            return redirect()->to(Filament::getPanel('admin')->getUrl());
        }

        return $next($request);
    }
}
