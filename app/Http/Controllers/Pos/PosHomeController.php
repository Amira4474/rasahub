<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The POS shell. It shows who is signed in and where they may work.
 * Selling is not built yet.
 */
class PosHomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('pos.home', [
            'user' => $user,
            'business' => $user->business,
            'branches' => Branch::visibleTo($user)
                ->where('is_active', true)
                ->with(['terminals' => fn ($terminals) => $terminals->where('is_active', true)->orderBy('name')])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
