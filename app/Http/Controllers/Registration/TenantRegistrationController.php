<?php

namespace App\Http\Controllers\Registration;

use App\Http\Controllers\Controller;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Tenancy\RegisterTenant;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Registration of a new tenant. There is no open sign-up: the only way in is
 * a link from an invitation made in the platform panel.
 */
class TenantRegistrationController extends Controller
{
    public function show(string $token): View|Response
    {
        $invitation = $this->usableInvitation($token);

        if ($invitation === null) {
            return response()->view('register.invalid', status: 410);
        }

        return view('register.show', ['invitation' => $invitation, 'token' => $token]);
    }

    public function store(Request $request, string $token, RegisterTenant $register): RedirectResponse|Response
    {
        $invitation = $this->usableInvitation($token);

        if ($invitation === null) {
            return response()->view('register.invalid', status: 410);
        }

        $request->merge(['acronym' => strtoupper(trim((string) $request->input('acronym')))]);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'acronym' => ['required', 'string', 'alpha_num:ascii', 'min:2', 'max:10', Rule::unique('businesses', 'acronym')],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::default()],
        ], [
            'acronym.unique' => 'Another company already uses this acronym. Please choose a different one.',
        ]);

        // Whoever was signed in on this browser is signed out first, so the
        // new records are not mistaken for theirs.
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // The invitation was checked against existing accounts when it was
        // made, but one could have been created since.
        if (User::acrossBusinesses()->where('email', $invitation->email)->exists()) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors([
                'business_name' => 'Someone with the email address '.$invitation->email.' already has an account. Please contact RasaHub support.',
            ]);
        }

        $owner = $register->handle($invitation, $data);

        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->to(Filament::getPanel('admin')->getUrl());
    }

    private function usableInvitation(string $token): ?TenantInvitation
    {
        $invitation = TenantInvitation::findByToken($token);

        return $invitation?->isUsable() ? $invitation : null;
    }
}
