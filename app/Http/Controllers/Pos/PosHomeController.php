<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The screen of the device chosen at sign-in: a POS terminal's, or a
 * kitchen screen's.
 */
class PosHomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        if (($screen = $request->attributes->get('kitchenScreen')) !== null) {
            return view('pos.kitchen-screen', ['screen' => $screen]);
        }

        return view('pos.home', ['terminal' => $request->attributes->get('terminal')]);
    }
}
