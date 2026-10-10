<?php

namespace App\Models;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A business's subscription to RasaHub. It records the plan and the dates;
 * no payment is taken here. Only an active subscription lets the business's
 * own staff sign in. A tenant that registered itself has no dates until a
 * platform user activates it.
 */
#[Fillable(['business_id', 'plan', 'starts_at', 'ends_at', 'suspended_at', 'suspension_reason'])]
class Subscription extends Model
{
    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }

    public function status(): SubscriptionStatus
    {
        return match (true) {
            $this->suspended_at !== null => SubscriptionStatus::Suspended,
            $this->starts_at === null || $this->ends_at === null => SubscriptionStatus::Pending,
            // The end date is the last valid day.
            $this->ends_at->lt(today()) => SubscriptionStatus::Expired,
            default => SubscriptionStatus::Active,
        };
    }

    public function allowsAccess(): bool
    {
        return $this->status() === SubscriptionStatus::Active;
    }

    public function suspend(?string $reason = null): void
    {
        $this->update(['suspended_at' => now(), 'suspension_reason' => $reason]);
    }

    public function resume(): void
    {
        $this->update(['suspended_at' => null, 'suspension_reason' => null]);
    }

    /**
     * Give the subscription its dates. This is what lets a tenant that
     * registered itself start signing in.
     */
    public function activate(Carbon $startsAt, Carbon $endsAt): void
    {
        $this->update(['starts_at' => $startsAt, 'ends_at' => $endsAt]);
    }

    /**
     * Add one month. A subscription that is still running is extended from
     * its end date; one that has already ended, or never started, gets a
     * month from today.
     */
    public function extendByOneMonth(): void
    {
        $from = $this->ends_at === null || $this->ends_at->lt(today()) ? today() : $this->ends_at;

        $this->update(['ends_at' => $from->copy()->addMonthNoOverflow()]);
    }

    /**
     * The dates a new subscription gets: one month from today.
     *
     * @return array{starts_at: Carbon, ends_at: Carbon}
     */
    public static function firstPeriod(): array
    {
        return ['starts_at' => today(), 'ends_at' => today()->addMonthNoOverflow()];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'suspended_at' => 'datetime',
        ];
    }
}
