<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'type' => ProductType::Single,
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->randomFloat(2, 3, 40),
            'is_active' => true,
        ];
    }

    public function setMeal(): static
    {
        return $this->state(['type' => ProductType::Set]);
    }
}
