<?php

namespace Database\Factories;

use App\Models\Lot;
use App\Models\Product;
use App\Models\StockReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lot>
 */
class LotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'stock_receipt_id' => StockReceipt::factory(),
            'sale_format_id' => null,
            'received_label' => 'Unité de stock',
            'received_qty' => '10.000',
            'content_qty' => '1.000',
            'converted_qty' => '10.000',
            'remaining_qty' => '10.000',
            'lot_number' => fake()->unique()->numerify('L-####'),
            'best_before' => null,
            'unit_cost_cents' => null,
            'cost_cents' => null,
        ];
    }

    /** Lot de $qty unités de stock, encore entier. */
    public function qty(string $qty): static
    {
        return $this->state(fn () => ['received_qty' => $qty, 'converted_qty' => $qty, 'remaining_qty' => $qty]);
    }
}
