<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Rice', 'Noodles', 'Drinks', 'Desserts', 'Snacks', 'Breakfast', 'Soups', 'Sides']).' '.fake()->unique()->numberBetween(1, 9999),
            'is_active' => true,
        ];
    }
}
