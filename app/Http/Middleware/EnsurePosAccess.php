<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the POS. Only tenant staff who may use the POS get in; anyone not
 * signed in is sent to the POS sign-in page.
 */
class EnsurePosAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->guest(route('pos.login'));
        }

        $user = Auth::user();

        // A tenant whose subscription was suspended or has ended while its
        // staff were signed in: they are signed out and told why.
        if ($user->holdsPosAccess() && ($problem = $user->businessAccessProblem()) !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('pos.login')->withErrors(['acronym' => $problem]);
        }

        abort_unless($user->canAccessPos(), 403);

        return $next($request);
    }
}
