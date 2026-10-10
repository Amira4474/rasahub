<?php

namespace App\Http\Controllers\Registration;

use App\Enums\Plan;
use App\Http\Controllers\Controller;
use App\Models\TenantInvitation;
use App\Tenancy\RegisterTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Registration of a new tenant, in two steps: the token from an invitation
 * made in the platform panel, then the business details. The new tenant
 * cannot sign in until a platform user activates its subscription.
 */
class TenantRegistrationController extends Controller
{
    private const INVITATION_SESSION_KEY = 'registration_invitation';

    private const RECEIVED_SESSION_KEY = 'registration_received';

    /**
     * The same answer whether the token never existed, was used, expired or
     * was revoked, so that the page tells a guesser nothing.
     */
    private const INVALID_TOKEN = 'This token is not valid. It may have expired, been used already, or been withdrawn.';

    public function showToken(): View
    {
        return view('register.token');
    }

    public function storeToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:'.TenantInvitation::TOKEN_MAX_LENGTH],
        ]);

        $invitation = TenantInvitation::findByToken($data['token']);

        if (! $invitation?->isUsable()) {
            return back()->withInput()->withErrors(['token' => self::INVALID_TOKEN]);
        }

        $request->session()->put(self::INVITATION_SESSION_KEY, $invitation->getKey());

        return redirect()->route('register.details');
    }

    public function showDetails(Request $request): View|RedirectResponse
    {
        $invitation = $this->invitation($request);

        if ($invitation === null) {
            return $this->backToToken($request);
        }

        return view('register.details', ['invitation' => $invitation, 'plans' => Plan::cases()]);
    }

    public function store(Request $request, RegisterTenant $register): RedirectResponse
    {
        $invitation = $this->invitation($request);

        if ($invitation === null) {
            return $this->backToToken($request);
        }

        $request->merge([
            'acronym' => strtoupper(trim((string) $request->input('acronym'))),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'acronym' => ['required', 'string', 'alpha_num:ascii', 'min:2', 'max:10', Rule::unique('businesses', 'acronym')],
            'plan' => ['required', Rule::enum(Plan::class)],
            'name' => ['required', 'string', 'max:255'],
            // One email address signs in to one account, so the owner of
            // another tenant, or anyone else with an account, is refused.
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::default()],
        ], [
            'acronym.unique' => 'Another company already uses this acronym. Please choose a different one.',
            'email.unique' => 'This email address already has an account. Please use a different one.',
        ]);

        // Whoever was signed in on this browser is signed out first, so the
        // new records are not mistaken for theirs.
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        try {
            $owner = $register->handle($invitation, [...$data, 'plan' => Plan::from($data['plan'])]);
        } catch (AuthorizationException) {
            // Someone else used the token a moment earlier.
            return $this->backToToken($request);
        }

        $request->session()->forget(self::INVITATION_SESSION_KEY);

        return redirect()->route('register.received')->with(self::RECEIVED_SESSION_KEY, [
            'business' => $owner->business->name,
            'email' => $owner->email,
        ]);
    }

    public function received(Request $request): View|RedirectResponse
    {
        $registration = $request->session()->get(self::RECEIVED_SESSION_KEY);

        if ($registration === null) {
            return redirect()->route('register.show');
        }

        return view('register.received', ['registration' => $registration]);
    }

    /**
     * The invitation whose token was accepted in step one, if it can still
     * be used.
     */
    private function invitation(Request $request): ?TenantInvitation
    {
        $id = $request->session()->get(self::INVITATION_SESSION_KEY);

        $invitation = $id === null ? null : TenantInvitation::find($id);

        return $invitation?->isUsable() ? $invitation : null;
    }

    private function backToToken(Request $request): RedirectResponse
    {
        $hadToken = $request->session()->pull(self::INVITATION_SESSION_KEY) !== null;

        return redirect()->route('register.show')->withErrors($hadToken ? ['token' => self::INVALID_TOKEN] : []);
    }
}
