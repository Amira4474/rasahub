<?php

namespace App\Tenancy;

use App\Models\Business;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Answers one question for the whole application: which business is the
 * current request acting in?
 *
 * - A tenant user always acts in their own business.
 * - A platform user acts in no business, unless Super Impose is active.
 * - Code with no signed-in user (console commands, queued jobs) is system
 *   code and is not limited to a business unless it asks to be, with for().
 *
 * Query scopes, policies and forms read the answer from here. Nothing else
 * should work it out from the user directly.
 */
class TenantContext
{
    private bool $forced = false;

    private ?int $forcedBusinessId = null;

    private ?Business $resolved = null;

    public function __construct(private readonly SuperImpose $superImpose) {}

    /**
     * Whether queries must be limited to a business. When this is true and
     * businessId() is null, tenant data is out of reach entirely.
     */
    public function isScoped(): bool
    {
        return $this->forced || $this->user() !== null;
    }

    /**
     * Whether code is deliberately acting in a business through for().
     */
    public function isForced(): bool
    {
        return $this->forced;
    }

    public function businessId(): ?int
    {
        if ($this->forced) {
            return $this->forcedBusinessId;
        }

        $user = $this->user();

        return $user === null ? null : $this->businessIdFor($user);
    }

    /**
     * The business the given user acts in: their own, or for a platform
     * user the one they have entered through Super Impose.
     */
    public function businessIdFor(User $user): ?int
    {
        return $user->business_id ?? $this->superImpose->businessIdFor($user);
    }

    /**
     * Whether the given user is acting in the given business. Policies ask
     * this, so that knowing a record's ID is never enough to reach it.
     */
    public function allows(User $user, Business|int|null $business): bool
    {
        $id = $business instanceof Business ? $business->getKey() : $business;

        return $id !== null && (int) $id === $this->businessIdFor($user);
    }

    public function business(): ?Business
    {
        $id = $this->businessId();

        if ($id === null) {
            return null;
        }

        if ($this->resolved?->getKey() !== $id) {
            $this->resolved = Business::find($id);
        }

        return $this->resolved;
    }

    /**
     * The signed-in user. Loading them is never limited to a business (see
     * SignInUserProvider), so asking here from inside a query scope is safe.
     */
    private function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Run the callback as if acting in the given business, whoever is signed
     * in. For deliberate system or platform work only.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function for(Business $business, Closure $callback): mixed
    {
        [$wasForced, $previousId] = [$this->forced, $this->forcedBusinessId];

        $this->forced = true;
        $this->forcedBusinessId = $business->getKey();

        try {
            return $callback();
        } finally {
            $this->forced = $wasForced;
            $this->forcedBusinessId = $previousId;

            // A query built inside the callback must not be able to run
            // against that business's database after leaving it.
            app(TenantDatabase::class)->release();
        }
    }
}
