<?php

namespace Database\Factories;

use App\Models\StockReceipt;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockReceipt>
 */
class StockReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'received_on' => today(),
            'delivery_note_no' => fake()->numerify('BL-####'),
            'user_id' => null,
            'notify_preorders' => true,
        ];
    }
}
