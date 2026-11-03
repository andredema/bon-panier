<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'customer_id' => Customer::factory(),
            'channel' => Channel::Retail,
            'status' => OrderStatus::Received,
            'fulfilment' => Order::PICKUP,
            'slot_start' => $start,
            'slot_end' => $start->copy()->addHours(2),
            'payment_method' => Order::PAY_INTERAC,
            'subtotal_cents' => 0,
            'taxable_cents' => 0,
            'tax_tps_cents' => 0,
            'tax_tvq_cents' => 0,
            'total_cents' => 0,
            'consent_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Order $o) => $o->number ?? $o->update(['number' => 'C-'.now()->format('Y').'-'.str_pad((string) $o->id, 4, '0', STR_PAD_LEFT)]));
    }
}
