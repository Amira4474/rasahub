<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Models\TenantInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantInvitation>
 */
class TenantInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'plan' => Plan::Growth,
            'token_hash' => hash('sha256', Str::random(48)),
            'expires_at' => now()->addDays(TenantInvitation::DEFAULT_DAYS_VALID),
        ];
    }
}
