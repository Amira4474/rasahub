<?php

namespace App\Tenancy;

use App\Audit\Audit;
use App\Enums\AuditEvent;
use App\Enums\Plan;
use App\Enums\Role;
use App\Models\Business;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns an invitation into a tenant: the business with its own database,
 * its Owner, and a subscription on the chosen plan that has no dates until
 * a platform user activates it.
 */
class RegisterTenant
{
    public function __construct(
        private readonly TenantDatabase $databases,
        private readonly Audit $audit,
    ) {}

    /**
     * @param  array{business_name: string, plan: Plan, name: string, email: string, password: string}  $data
     *
     * @throws AuthorizationException When the invitation can no longer be used.
     */
    public function handle(TenantInvitation $invitation, array $data): User
    {
        return DB::transaction(function () use ($invitation, $data): User {
            // Locked so that the same token cannot be used twice at once.
            $invitation = TenantInvitation::lockForUpdate()->findOrFail($invitation->getKey());

            if (! $invitation->isUsable()) {
                throw new AuthorizationException('This invitation can no longer be used.');
            }

            $business = Business::create([
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                // Its company acronym is assigned from the name.
                'currency' => config('tenancy.registration.currency'),
                'timezone' => config('tenancy.registration.timezone'),
            ]);

            try {
                $business->subscription()->create(['plan' => $data['plan']]);

                $owner = new User([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'business_id' => $business->getKey(),
                ]);
                $owner->save();
                $owner->assignRole(Role::Owner);

                $invitation->update(['accepted_at' => now(), 'business_id' => $business->getKey()]);

                $this->audit->record(AuditEvent::TenantRegistered, $business, [
                    'email' => $owner->email,
                    'plan' => $data['plan']->value,
                    'invitation_id' => $invitation->getKey(),
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
