<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePosTerminal;
use App\Models\Branch;
use App\Models\Terminal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The last step of signing in to the POS: which terminal this is. Asked at
 * every sign-in, and again from "Switch terminal".
 */
class PosTerminalController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $branches = Branch::visibleTo($request->user())
            ->where('is_active', true)
            ->with(['terminals' => fn ($terminals) => $terminals->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Branch $branch): bool => $branch->terminals->isNotEmpty())
            ->values();

        $terminals = $branches->flatMap->terminals;

        // Nothing to choose between.
        if ($terminals->count() === 1 && ! $request->boolean('switch')) {
            $request->session()->put(EnsurePosTerminal::SESSION_KEY, $terminals->first()->id);

            return redirect()->route('pos.home');
        }

        return view('pos.terminal', [
            'branches' => $branches,
            'current' => $request->session()->get(EnsurePosTerminal::SESSION_KEY),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['terminal' => ['required', 'integer']]);

        $request->session()->put(EnsurePosTerminal::SESSION_KEY, (int) $request->input('terminal'));

        if (EnsurePosTerminal::current($request) === null) {
            $request->session()->forget(EnsurePosTerminal::SESSION_KEY);

            return back()->withErrors(['terminal' => 'Choose one of the terminals listed.']);
        }

        return redirect()->route('pos.home');
    }
}
