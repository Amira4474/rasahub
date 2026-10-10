<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'acronym' => strtoupper(fake()->unique()->lexify('??????')),
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ];
    }

    /**
     * A business comes with an active subscription unless a state below
     * says otherwise.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Business $business): void {
            $business->subscription()->create(['plan' => Plan::Starter, ...Subscription::firstPeriod()]);
        });
    }

    public function onPlan(Plan $plan): static
    {
        return $this->afterCreating(function (Business $business) use ($plan): void {
            $business->subscription->update(['plan' => $plan]);
        });
    }

    public function expired(): static
    {
        return $this->afterCreating(function (Business $business): void {
            $business->subscription->update(['starts_at' => today()->subMonths(2), 'ends_at' => today()->subDay()]);
        });
    }

    /**
     * Registered, but not yet activated by a platform user.
     */
    public function pendingActivation(): static
    {
        return $this->afterCreating(function (Business $business): void {
            $business->subscription->update(['starts_at' => null, 'ends_at' => null]);
        });
    }

    public function suspended(): static
    {
        return $this->afterCreating(function (Business $business): void {
            $business->subscription->suspend('Suspended for testing.');
        });
    }

    public function withoutSubscription(): static
    {
        return $this->afterCreating(function (Business $business): void {
            $business->subscription->delete();
            $business->unsetRelation('subscription');
        });
    }
}
