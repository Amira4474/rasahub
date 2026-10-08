<?php

namespace App\Http\Middleware;

use App\Tenancy\SuperImpose;
use App\Tenancy\TenantContext;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the tenant admin panel. Everyone inside it must be acting in a
 * business that still exists and is allowed in. A platform user who is not
 * in Super Impose is sent back to the platform panel rather than shown an
 * error.
 */
class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        if (app(TenantContext::class)->business() === null) {
            app(SuperImpose::class)->exit();

            if (Auth::user()->isPlatformUser()) {
                return redirect()->to(Filament::getPanel('platform')->getUrl());
            }
        }

        // A tenant whose subscription was suspended or has ended while its
        // staff were signed in: they are signed out and told why.
        $user = Auth::user();

        if ($user->holdsAdminAccess() && ($problem = $user->businessAccessProblem()) !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Notification::make()->danger()->title($problem)->persistent()->send();

            return redirect()->to(Filament::getPanel('admin')->getLoginUrl());
        }

        return $next($request);
    }
}
