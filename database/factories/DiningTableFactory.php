<?php

namespace Database\Factories;

use App\Models\DiningTable;
use App\Models\FloorArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'floor_area_id' => FloorArea::factory(),
            'name' => 'T'.fake()->unique()->numberBetween(1, 999),
            'seats' => 4,
            'is_active' => true,
        ];
    }
}
