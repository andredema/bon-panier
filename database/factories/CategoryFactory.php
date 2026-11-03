<?php

namespace Database\Factories;

use App\Enums\CategoryPalette;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'icon' => fake()->randomElement(Category::ICONS),
            'palette' => fake()->randomElement(CategoryPalette::cases()),
            'sort_order' => 0,
        ];
    }
}
