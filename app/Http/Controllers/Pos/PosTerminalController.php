<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePosTerminal;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The last step of signing in to the POS: which device this is, a POS
 * terminal or a kitchen screen. Asked at every sign-in, and again from
 * "Switch terminal".
 */
class PosTerminalController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $screens = EnsurePosTerminal::screensAllowed($request);

        $branches = Branch::visibleTo($request->user())
            ->where('is_active', true)
            ->with([
                'terminals' => fn ($terminals) => $terminals->where('is_active', true)->orderBy('name'),
                'kitchenScreens' => fn ($q) => $q->where('is_active', true)->when(! $screens, fn ($q) => $q->whereRaw('0 = 1'))->orderBy('name'),
            ])
            ->orderBy('name')
            ->get()
            ->filter(fn (Branch $branch): bool => $branch->terminals->isNotEmpty() || $branch->kitchenScreens->isNotEmpty())
            ->values();

        $devices = $branches->flatMap(fn (Branch $branch) => [
            ...$branch->terminals->map(fn ($terminal) => "terminal:{$terminal->id}"),
            ...$branch->kitchenScreens->map(fn ($screen) => "screen:{$screen->id}"),
        ]);

        // Nothing to choose between.
        if ($devices->count() === 1 && ! $request->boolean('switch')) {
            return $this->choose($request, $devices->first());
        }

        $session = $request->session();

        return view('pos.terminal', [
            'branches' => $branches,
            'current' => $session->has(EnsurePosTerminal::SCREEN_SESSION_KEY)
                ? 'screen:'.$session->get(EnsurePosTerminal::SCREEN_SESSION_KEY)
                : 'terminal:'.$session->get(EnsurePosTerminal::SESSION_KEY),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'device' => ['required_without:terminal', 'nullable', 'string', 'regex:/^(terminal|screen):\d+$/'],
            'terminal' => ['nullable', 'integer'],
        ]);

        return $this->choose($request, $request->input('device') ?? 'terminal:'.$request->integer('terminal'));
    }

    private function choose(Request $request, string $device): RedirectResponse
    {
        [$kind, $id] = explode(':', $device, 2);
        $session = $request->session();

        $session->forget([EnsurePosTerminal::SESSION_KEY, EnsurePosTerminal::SCREEN_SESSION_KEY]);
        $session->put($kind === 'screen' ? EnsurePosTerminal::SCREEN_SESSION_KEY : EnsurePosTerminal::SESSION_KEY, (int) $id);

        $valid = $kind === 'screen' ? EnsurePosTerminal::currentScreen($request) : EnsurePosTerminal::current($request);

        if ($valid === null) {
            $session->forget([EnsurePosTerminal::SESSION_KEY, EnsurePosTerminal::SCREEN_SESSION_KEY]);

            return redirect()->route('pos.terminal', ['switch' => 1])->withErrors(['terminal' => 'Choose one of the devices listed.']);
        }

        return redirect()->route('pos.home');
    }
}
