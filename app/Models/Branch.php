<?php

namespace App\Models;

use App\Enums\Permission;
use App\Tenancy\TenantContext;
use App\Tenancy\UsesTenantDatabase;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'timezone', 'is_active'])]
/**
 * A branch is tenant data: it lives in its business's own database, so it
 * carries no business_id.
 */
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, HasUlids, SoftDeletes, UsesTenantDatabase;

    /**
     * Mirror the column default so a new model is active before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The ULID is a secondary public identifier; the primary key stays an integer.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return HasMany<Terminal, $this>
     */
    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }

    /**
     * Limit the query to the branches the given user may work in. The user
     * must be acting in the business whose database is being read; within
     * it, someone with the access-all permission reaches every branch, and
     * anyone else only the branches they are assigned to.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $context = app(TenantContext::class);

        if (! $context->allows($user, $context->businessId())) {
            $query->whereRaw('0 = 1');

            return;
        }

        if (! $user->can(Permission::AccessAllBranches)) {
            // Users are in the central database, so the assignment table is
            // read directly rather than through a relationship to them.
            $query->whereIn($this->qualifyColumn('id'), fn ($assigned) => $assigned
                ->select('branch_id')
                ->from('branch_user')
                ->where('user_id', $user->getKey()));
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
