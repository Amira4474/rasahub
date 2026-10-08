<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'business_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Mirror the column default so a new model is active before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The branches this user is assigned to work in.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    /**
     * A platform user belongs to no business and is not limited to one.
     */
    public function isPlatformUser(): bool
    {
        return $this->business_id === null;
    }

    public function belongsToBusiness(Business|int|null $business): bool
    {
        $businessId = $business instanceof Business ? $business->getKey() : $business;

        return $businessId !== null && $this->business_id === $businessId;
    }

    /**
     * Whether the user may work in the given branch. Roles do not decide
     * this: it depends on the business, and then on the branch assignment.
     */
    public function canAccessBranch(Branch $branch): bool
    {
        if ($this->isPlatformUser()) {
            return true;
        }

        if (! $this->belongsToBusiness($branch->business_id)) {
            return false;
        }

        return $this->can(Permission::AccessAllBranches)
            || $this->branches()->whereKey($branch->getKey())->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->can(Permission::AccessAdminPanel);
    }

    /**
     * Limit the query to the users in the given user's business.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isPlatformUser()) {
            return;
        }

        $query->where($this->qualifyColumn('business_id'), $user->business_id);
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
