<?php

namespace Database\Factories;

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
            'token' => strtoupper(Str::random(10)),
            'expires_at' => now()->addDays(TenantInvitation::DEFAULT_DAYS_VALID),
        ];
    }
}
