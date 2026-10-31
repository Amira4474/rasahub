<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\FloorArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FloorArea>
 */
class FloorAreaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->randomElement(['Indoor', 'Patio', 'Upstairs', 'Garden']),
            'is_active' => true,
        ];
    }
}
