<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('450-555-####'),
            'email' => fake()->unique()->safeEmail(),
            'type' => 'particulier',
            'approved_wholesale' => false,
            'consent_at' => now(),
        ];
    }
}
