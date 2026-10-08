<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\RoleScopeMismatchException;
use App\Tenancy\BelongsToBusiness;
use App\Tenancy\SuperImpose;
use App\Tenancy\TenantContext;
use BackedEnum;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'business_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use BelongsToBusiness, HasFactory, Notifiable;

    use HasRoles {
        assignRole as protected assignRoleUnchecked;
    }

    /**
     * Mirror the column default so a new model is active before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Branch assignments are kept in the tenant's database, which has no
     * link back to this table, so they are removed here.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $user): void {
            $user->business?->run(fn () => $user->branches()->detach());
        });
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The branches this user is assigned to work in. Branches and the
     * assignment table are both in the tenant's database.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    /**
     * A platform user belongs to the platform itself, not to any business.
     */
    public function isPlatformUser(): bool
    {
        return $this->business_id === null;
    }

    /**
     * Give the user roles, refusing a platform role for a tenant user and a
     * business role for a platform user. syncRoles() goes through here too.
     *
     * @param  string|int|array|RoleContract|Collection|BackedEnum  ...$roles
     */
    public function assignRole(...$roles): static
    {
        foreach (collect($roles)->flatten() as $role) {
            $name = match (true) {
                $role instanceof BackedEnum => $role->value,
                $role instanceof RoleContract => $role->name,
                default => $role,
            };

            $known = is_string($name) ? Role::tryFrom($name) : null;

            if ($known !== null && $known->isPlatform() !== $this->isPlatformUser()) {
                throw RoleScopeMismatchException::for($known->value, $this->isPlatformUser());
            }
        }

        return $this->assignRoleUnchecked(...$roles);
    }

    /**
     * Whether the user may work in the given branch. The branch must come
     * from the business the user is acting in, and then the user needs
     * either the access-all permission or an assignment to that branch.
     */
    public function canAccessBranch(Branch $branch): bool
    {
        $context = app(TenantContext::class);
        $tenant = $branch->tenantBusinessId();

        // The assignments are read from the database being acted in, so
        // that must be the branch's own as well as the user's.
        if (! $context->allows($this, $tenant) || $context->businessId() !== $tenant) {
            return false;
        }

        return $this->can(Permission::AccessAllBranches)
            || $this->branches()->whereKey($branch->getKey())->exists();
    }

    /**
     * Each panel is a separate door with its own sign-in page.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'platform' => $this->is_active && $this->isPlatformUser() && $this->can(Permission::AccessPlatform),
            'admin' => $this->isPlatformUser()
                // A platform user only ever enters a tenant through Super Impose.
                ? $this->is_active && app(SuperImpose::class)->businessIdFor($this) !== null
                : $this->holdsAdminAccess() && $this->businessAccessProblem() === null,
            default => false,
        };
    }

    /**
     * The POS is for tenant staff only. Platform users never enter it.
     */
    public function canAccessPos(): bool
    {
        return $this->holdsPosAccess() && $this->businessAccessProblem() === null;
    }

    /**
     * Whether this is a tenant user who may use the admin pages, leaving
     * aside whether their business is currently allowed in.
     */
    public function holdsAdminAccess(): bool
    {
        return $this->is_active && ! $this->isPlatformUser() && $this->can(Permission::AccessAdminPanel);
    }

    /**
     * The same, for the POS.
     */
    public function holdsPosAccess(): bool
    {
        return $this->is_active && ! $this->isPlatformUser() && $this->can(Permission::AccessPos);
    }

    /**
     * Why this user's business cannot be used right now (it is inactive, or
     * its subscription is suspended or has ended), or null when it can.
     */
    public function businessAccessProblem(): ?string
    {
        return $this->business === null
            ? 'This account does not belong to a company.'
            : $this->business->accessProblem();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
