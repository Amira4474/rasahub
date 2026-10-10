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
use Illuminate\Support\Str;
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
        // The acronym is assigned from the name unless one was given.
        static::creating(function (self $business): void {
            if (blank($business->acronym)) {
                $business->acronym = self::acronymFor($business->name);
            }
        });

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
     * The company acronym for a new business: the first letter of each word
     * of its name, so "Demo Cafe" gives DC. A name of one word gives its
     * first three letters. If another business already has the acronym, a
     * number is added: DC2, DC3 and so on.
     */
    public static function acronymFor(string $name): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', Str::ascii($name), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        $base = match (count($words)) {
            0 => 'RH',
            1 => substr($words[0], 0, 3),
            default => implode('', array_map(fn (string $word): string => $word[0], $words)),
        };

        // Room is left for the number within the ten characters allowed.
        $base = strtoupper(substr($base, 0, 7));

        for ($number = 1; ; $number++) {
            $acronym = $number === 1 ? $base : $base.$number;

            if (strlen($acronym) >= 2 && self::acronymIsFree($acronym)) {
                return $acronym;
            }
        }
    }

    /**
     * How many of something the business has, counted against its plan's
     * limit: "branches", "terminals" or "staff".
     */
    public function usage(string $limit): int
    {
        return match ($limit) {
            'branches' => $this->run(fn (): int => Branch::count()),
            'terminals' => $this->run(fn (): int => Terminal::count()),
            'staff' => User::acrossBusinesses()->where('business_id', $this->getKey())->count(),
        };
    }

    /**
     * Whether the plan allows one more branch, POS terminal or staff
     * account. A business with no subscription cannot sign in at all, so
     * nothing more is refused here.
     */
    public function hasRoomFor(string $limit): bool
    {
        $allowed = $this->subscription?->plan->limit($limit);

        return $allowed === null || $this->usage($limit) < $allowed;
    }

    /**
     * What to tell someone who has reached the plan's limit.
     */
    public function limitReachedMessage(string $limit): string
    {
        $plan = $this->subscription->plan;
        $allowed = $plan->limit($limit);
        $what = config("plans.limits.{$limit}.".($allowed === 1 ? 'singular' : 'plural'));

        return "The {$plan->label()} plan allows {$allowed} {$what}, and this business has reached that. Contact RasaHub to change plan.";
    }

    private static function acronymIsFree(string $acronym): bool
    {
        return ! in_array($acronym, config('tenancy.reserved_acronyms'), true)
            && ! self::withTrashed()->where('acronym', $acronym)->exists();
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
            SubscriptionStatus::Pending => 'This company\'s registration is waiting for RasaHub to activate it. You will be able to sign in once it has been activated.',
            SubscriptionStatus::Suspended => 'This company\'s subscription is suspended. Please contact RasaHub support.',
            SubscriptionStatus::Expired => 'This company\'s subscription ended on '.$this->subscription->ends_at->format('j F Y').'. Please contact RasaHub support to renew it.',
            null => 'This company has no subscription. Please contact RasaHub support.',
        };
    }

    /**
     * The acronym is what staff type to find their company at the POS
     * sign-in, so it is always stored in capitals. The tenant's database is
     * named after it, so it never changes once the business exists.
     *
     * @return Attribute<string, string|null>
     */
    protected function acronym(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : strtoupper(trim($value)));
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
