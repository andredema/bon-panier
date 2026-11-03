<?php

namespace Database\Factories;

use App\Enums\UnitKind;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        $word = fake()->unique()->randomElement(['carton', 'botte', 'douzaine', 'caisse', 'paquet', 'boîte', 'régime', 'bidon']);

        return [
            'code' => $word.'-'.fake()->unique()->numberBetween(1, 9999),
            'label' => ucfirst($word),
            'short_label' => $word,
            'kind' => UnitKind::Count,
            'base_factor' => null,
            'allows_decimals' => false,
            'default_step' => '1.000',
            'is_active' => true,
            'is_system' => false,
            'sort_order' => 10,
        ];
    }
}
