<?php

namespace Database\Factories;

use App\Models\ExpectedArrival;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpectedArrival>
 */
class ExpectedArrivalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => null,
            'product_id' => Product::factory(),
            'expected_on' => today()->addDays(3),
            'qty' => null,
            'status' => ExpectedArrival::OPEN,
        ];
    }
}
