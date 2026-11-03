<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleFormat>
 */
class SaleFormatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'channel' => Channel::Retail,
            'unit_id' => fn () => Unit::where('code', 'unite')->value('id'),
            'label' => 'À la pièce',
            'is_by_weight' => false,
            'content_qty' => '1.000',
            'net_qty' => null,
            'net_unit_id' => null,
            'price_cents' => fake()->numberBetween(199, 4999),
            'min_qty' => '1.000',
            'step' => '1.000',
            'is_visible' => true,
            'sort_order' => 0,
        ];
    }

    public function wholesale(): static
    {
        return $this->state(fn () => ['channel' => Channel::Wholesale]);
    }
}
