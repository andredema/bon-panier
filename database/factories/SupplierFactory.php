<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'contact' => fake()->name(),
            'phone' => fake()->numerify('514-555-####'),
            'email' => fake()->unique()->safeEmail(),
            'usual_delay_days' => fake()->numberBetween(2, 14),
            'is_active' => true,
        ];
    }
}
