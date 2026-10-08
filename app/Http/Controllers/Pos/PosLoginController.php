<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Staff sign in to the POS in two steps: first the company acronym, then
 * their email and password. The second step only accepts people who belong
 * to that company and may use the POS.
 */
class PosLoginController extends Controller
{
    private const SESSION_KEY = 'pos_login.business_id';

    public function showCompany(): View|RedirectResponse
    {
        if (Auth::user()?->canAccessPos()) {
            return redirect()->route('pos.home');
        }

        return view('pos.login-company');
    }

    public function storeCompany(Request $request): RedirectResponse
    {
        $request->validate(['acronym' => ['required', 'string', 'max:10']]);

        $business = Business::query()
            ->where('acronym', strtoupper(trim($request->string('acronym'))))
            ->where('is_active', true)
            ->first();

        if ($business === null) {
            throw ValidationException::withMessages([
                'acronym' => 'We could not find a company with that acronym.',
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $business->getKey());

        return redirect()->route('pos.login.credentials');
    }

    public function showCredentials(Request $request): View|RedirectResponse
    {
        $business = $this->chosenBusiness($request);

        if ($business === null) {
            return redirect()->route('pos.login');
        }

        return view('pos.login-credentials', ['business' => $business]);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $business = $this->chosenBusiness($request);

        if ($business === null) {
            return redirect()->route('pos.login');
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Whoever was signed in on this browser is signed out first, so the
        // lookup below is never limited to their business.
        if (Auth::check()) {
            Auth::logout();
        }

        // A correct password is not enough: the account must belong to the
        // chosen company and be allowed in the POS. Every failure gets the
        // same message, so the form reveals nothing about other accounts.
        // The one exception is someone who would get in if their company's
        // subscription allowed it: they have proved who they are, so they
        // are told why.
        $problem = null;

        $signedIn = Auth::attemptWhen($credentials, function (User $user) use ($business, &$problem): bool {
            if ($user->business_id !== $business->getKey()) {
                return false;
            }

            if ($user->holdsPosAccess()) {
                $problem = $user->businessAccessProblem();
            }

            return $user->canAccessPos();
        });

        if (! $signedIn) {
            throw ValidationException::withMessages(['email' => $problem ?? __('auth.failed')]);
        }

        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->intended(route('pos.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('pos.login');
    }

    private function chosenBusiness(Request $request): ?Business
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return $id === null ? null : Business::whereKey($id)->where('is_active', true)->first();
    }
}
