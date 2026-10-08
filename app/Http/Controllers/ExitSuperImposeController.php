<?php

namespace App\Http\Controllers;

use App\Tenancy\SuperImpose;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

class ExitSuperImposeController extends Controller
{
    public function __invoke(SuperImpose $superImpose): RedirectResponse
    {
        $superImpose->exit();

        session()->regenerate();

        return redirect()->to(Filament::getPanel('platform')->getUrl());
    }
}
