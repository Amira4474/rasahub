<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Terminal>
 */
class TerminalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => 'Counter '.fake()->numberBetween(1, 99),
            'code' => fake()->unique()->bothify('POS-####'),
            'is_active' => true,
        ];
    }
}
