<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The POS screen of the terminal chosen at sign-in.
 */
class PosHomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('pos.home', ['terminal' => $request->attributes->get('terminal')]);
    }
}
