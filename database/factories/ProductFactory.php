<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'brand' => null,
            'barcode' => null,
            'stock_unit_id' => fn () => Unit::where('code', 'unite')->value('id') ?? Unit::factory(),
            'is_frozen' => false,
            'is_taxable' => false,
            'is_visible' => true,
            'allow_preorder' => true,
            'alert_threshold' => '0.000',
            'popularity' => 0,
        ];
    }

    /** Stock compté dans l'unité donnée par son code (kg, g, l, sac, unite). */
    public function stockIn(string $code): static
    {
        return $this->state(fn () => ['stock_unit_id' => Unit::where('code', $code)->value('id')]);
    }
}
