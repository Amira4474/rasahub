<?php

namespace App\Models;

use App\Enums\Permission;
use App\Tenancy\TenantContext;
use App\Tenancy\UsesTenantDatabase;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'timezone', 'address', 'phone', 'service_charge_rate', 'service_charge_dine_in_only', 'business_day_starts_at', 'is_active'])]
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
        'service_charge_rate' => '0.00',
        'service_charge_dine_in_only' => true,
        'business_day_starts_at' => '04:00:00',
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
     * @return HasMany<FloorArea, $this>
     */
    public function floorAreas(): HasMany
    {
        return $this->hasMany(FloorArea::class)->orderBy('sort_order');
    }

    /**
     * The branch's own timezone, or the business's.
     */
    public function effectiveTimezone(): string
    {
        return $this->timezone ?? app(TenantContext::class)->business()?->timezone ?? config('app.timezone');
    }

    /**
     * The business day a moment belongs to. The day starts at the branch's
     * cut-off time, so with the default of 04:00 a sale at 01:30 on Saturday
     * belongs to Friday.
     */
    public function businessDateFor(CarbonInterface $moment): CarbonImmutable
    {
        [$hours, $minutes] = array_map('intval', explode(':', (string) $this->business_day_starts_at));

        return CarbonImmutable::instance($moment)
            ->setTimezone($this->effectiveTimezone())
            ->subHours($hours)
            ->subMinutes($minutes)
            ->startOfDay();
    }

    public function chargesServiceCharge(): bool
    {
        return (float) $this->service_charge_rate > 0;
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
            'service_charge_rate' => 'decimal:2',
            'service_charge_dine_in_only' => 'boolean',
        ];
    }
}
