<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantDatabase;
use Closure;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

#[Fillable(['name', 'slug', 'acronym', 'currency', 'timezone', 'is_active'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, HasUlids, SoftDeletes;

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
     * Every business gets a database of its own as soon as it exists.
     */
    protected static function booted(): void
    {
        static::created(function (self $business): void {
            app(TenantDatabase::class)->create($business);
        });

        // Removing the business for good would leave its database behind
        // with nothing pointing at it. Soft deletion is the normal way to
        // retire a tenant; its database has to be removed deliberately.
        static::forceDeleting(function (self $business): void {
            if (app(TenantDatabase::class)->exists($business)) {
                throw new LogicException('A business cannot be permanently deleted while its database still exists.');
            }
        });
    }

    /**
     * Run the callback inside this business, reaching its own database.
     * For deliberate platform or system work only.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Closure $callback): mixed
    {
        return app(TenantContext::class)->for($this, $callback);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Why this business's own staff cannot sign in right now, in words that
     * can be shown to them, or null when they can.
     */
    public function accessProblem(): ?string
    {
        if (! $this->is_active) {
            return 'This company\'s account is inactive. Please contact RasaHub support.';
        }

        return match ($this->subscription?->status()) {
            SubscriptionStatus::Active => null,
            SubscriptionStatus::Suspended => 'This company\'s subscription is suspended. Please contact RasaHub support.',
            SubscriptionStatus::Expired => 'This company\'s subscription ended on '.$this->subscription->ends_at->format('j F Y').'. Please contact RasaHub support to renew it.',
            null => 'This company has no subscription. Please contact RasaHub support.',
        };
    }

    /**
     * The acronym is what staff type to find their company at the POS
     * sign-in, so it is always stored in capitals.
     *
     * @return Attribute<string, string>
     */
    protected function acronym(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strtoupper(trim($value)));
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
