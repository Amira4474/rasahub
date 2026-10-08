<?php

namespace App\Tenancy;

use App\Audit\Audit;
use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns an invitation into a working tenant: the business with its own
 * database, its first subscription period, and its Owner.
 */
class RegisterTenant
{
    public function __construct(
        private readonly TenantDatabase $databases,
        private readonly Audit $audit,
    ) {}

    /**
     * @param  array{business_name: string, acronym: string, name: string, password: string}  $data
     *
     * @throws AuthorizationException When the invitation can no longer be used.
     */
    public function handle(TenantInvitation $invitation, array $data): User
    {
        return DB::transaction(function () use ($invitation, $data): User {
            // Locked so that the same link cannot be used twice at once.
            $invitation = TenantInvitation::lockForUpdate()->findOrFail($invitation->getKey());

            if (! $invitation->isUsable()) {
                throw new AuthorizationException('This invitation can no longer be used.');
            }

            $business = Business::create([
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'acronym' => $data['acronym'],
                'currency' => config('tenancy.registration.currency'),
                'timezone' => config('tenancy.registration.timezone'),
            ]);

            try {
                $business->subscription()->create(['plan' => $invitation->plan, ...Subscription::firstPeriod()]);

                $owner = new User([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'password' => $data['password'],
                    'business_id' => $business->getKey(),
                ]);
                // They reached the link sent to this address.
                $owner->email_verified_at = now();
                $owner->save();
                $owner->assignRole(Role::Owner);

                $invitation->update(['accepted_at' => now(), 'business_id' => $business->getKey()]);

                $this->audit->record(AuditEvent::TenantRegistered, $business, [
                    'email' => $owner->email,
                    'plan' => $invitation->plan->value,
                ], actor: $owner, superImpose: false);

                return $owner;
            } catch (Throwable $exception) {
                // The transaction undoes the central records. The tenant's
                // database is outside it, so it is removed here.
                $this->databases->drop($business);

                throw $exception;
            }
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;

        for ($suffix = 2; Business::withTrashed()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
