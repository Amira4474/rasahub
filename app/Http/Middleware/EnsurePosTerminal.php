<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\KitchenScreen;
use App\Models\Terminal;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The POS works on one device at a time, chosen after signing in: a POS
 * terminal, or a kitchen screen. One that is gone, inactive, or at a branch
 * the user does not work in sends them back to choose again.
 */
class EnsurePosTerminal
{
    public const SESSION_KEY = 'pos.terminal_id';

    public const SCREEN_SESSION_KEY = 'pos.kitchen_screen_id';

    public function handle(Request $request, Closure $next): Response
    {
        if (($screen = self::currentScreen($request)) !== null) {
            $request->attributes->set('kitchenScreen', $screen);

            return $next($request);
        }

        $terminal = self::current($request);

        if ($terminal === null) {
            $request->session()->forget([self::SESSION_KEY, self::SCREEN_SESSION_KEY]);

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

    public static function currentScreen(Request $request): ?KitchenScreen
    {
        $id = $request->session()->get(self::SCREEN_SESSION_KEY);

        if ($id === null || ! self::screensAllowed($request)) {
            return null;
        }

        $screen = KitchenScreen::with('branch')->where('is_active', true)->find($id);

        return $screen !== null && $screen->branch?->is_active && $request->user()->canAccessBranch($screen->branch)
            ? $screen
            : null;
    }

    /**
     * Kitchen screens are for the kitchen display permission, on Growth and
     * Enterprise.
     */
    public static function screensAllowed(Request $request): bool
    {
        return $request->user()->can(Permission::UseKitchenDisplay)
            && (app(TenantContext::class)->business()?->subscription?->plan->includes('kitchen_display') ?? false);
    }
}
