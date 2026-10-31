<?php

namespace App\Http\Middleware;

use App\Models\Terminal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The POS works at one terminal at a time, chosen after signing in. A
 * terminal that is gone, inactive, or at a branch the user does not work
 * in sends them back to choose again.
 */
class EnsurePosTerminal
{
    public const SESSION_KEY = 'pos.terminal_id';

    public function handle(Request $request, Closure $next): Response
    {
        $terminal = self::current($request);

        if ($terminal === null) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('pos.terminal');
        }

        $request->attributes->set('terminal', $terminal);

        return $next($request);
    }

    public static function current(Request $request): ?Terminal
    {
        $id = $request->session()->get(self::SESSION_KEY);

        if ($id === null) {
            return null;
        }

        $terminal = Terminal::with('branch')->where('is_active', true)->find($id);

        return $terminal !== null && $terminal->branch?->is_active && $request->user()->canAccessBranch($terminal->branch)
            ? $terminal
            : null;
    }
}
