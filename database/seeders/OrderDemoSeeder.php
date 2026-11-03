<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\CounterOrder;
use App\Services\Orders\TaxCalculator;
use App\Support\Quantity;
use Illuminate\Database\Seeder;

/**
 * Commandes de démonstration avec précommandes (plantain et couscous à 0),
 * clients fictifs. Jamais chargé en production.
 */
class OrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::with(['formats', 'expectedArrivals'])->get()->keyBy('name');

        // [client, [produit, format, quantité]], du plus ancien au plus récent
        $orders = [
            ['Client Démo A', 'demo.a@example.com', [['Plantain mûr', 'Au poids', '3'], ['Gari jaune', 'Sac de 1 kg', '2']]],
            ['Client Démo B', 'demo.b@example.com', [['Couscous de manioc', 'Sac de 1 kg', '2']]],
            ['Client Démo C', 'demo.c@example.com', [['Plantain mûr', 'Au poids', '4.5'], ['Couscous de manioc', 'Sac de 1 kg', '1']]],
        ];

        foreach ($orders as $n => [$name, $email, $lines]) {
            $customer = Customer::create(['name' => $name, 'phone' => '450-555-01'.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'email' => $email, 'consent_at' => now()]);
            $slot = today()->addDays(5)->setTime(10, 0);

            $order = Order::create([
                'customer_id' => $customer->id, 'channel' => Channel::Retail, 'status' => OrderStatus::Received,
                'fulfilment' => Order::PICKUP, 'slot_start' => $slot, 'slot_end' => $slot->copy()->addHours(2),
                'payment_method' => Order::PAY_INTERAC, 'subtotal_cents' => 0, 'taxable_cents' => 0,
                'tax_tps_cents' => 0, 'tax_tvq_cents' => 0, 'total_cents' => 0, 'consent_at' => now(),
            ]);
            $order->update(['number' => 'C-'.now()->format('Y').'-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT)]);

            $subtotal = 0;
            $preorder = false;
            foreach ($lines as [$productName, $label, $qty]) {
                $p = $products[$productName];
                $f = $p->formats->firstWhere('label', $label);
                $arrival = $p->expectedArrivals->where('status', 'open')->sortBy('expected_on')->first();
                $isPreorder = $arrival !== null && in_array($productName, ['Plantain mûr', 'Couscous de manioc'], true);
                $total = TaxCalculator::lineTotal($f->price_cents, $qty);
                $subtotal += $total;
                $preorder = $preorder || $isPreorder;

                $order->items()->create([
                    'product_id' => $p->id, 'sale_format_id' => $f->id, 'product_name' => $p->name,
                    'format_label' => $f->label, 'channel' => $f->channel->value, 'is_by_weight' => $f->is_by_weight,
                    'qty_unit' => $f->is_by_weight ? 'kg' : null, 'qty' => $qty,
                    'stock_qty' => Quantity::fromMilli($f->stockMilliFor($qty)),
                    'unit_price_cents' => $f->price_cents, 'line_total_cents' => $total,
                    'is_taxable' => $p->is_taxable, 'is_preorder' => $isPreorder,
                    'expected_on' => $isPreorder ? $arrival->expected_on : null,
                ]);
            }

            $t = app(TaxCalculator::class)->totals($subtotal, 0);
            $order->update(['subtotal_cents' => $subtotal, 'total_cents' => $t['total'], 'has_preorder' => $preorder]);
            $order->history()->create(['to' => OrderStatus::Received]);
        }

        // Vente au comptoir remise et payée : une facture et un paiement de démonstration
        $manager = User::where('email', 'gerant@example.com')->first();
        if ($manager) {
            app(CounterOrder::class)->place([
                'name' => 'Client Démo D', 'phone' => '450-555-0199', 'email' => 'demo.d@example.com', 'consent' => '1',
                'fulfilment' => Order::PICKUP, 'slot' => now()->format('Y-m-d\TH:i'), 'payment_method' => Order::PAY_ON_HANDOVER,
                'hand_over' => true, 'collected' => 'debit', 'send_mail' => false,
                'lines' => [
                    ['format_id' => $products['Gari jaune']->formats->firstWhere('label', 'Sac de 1 kg')->id, 'qty' => '4'],
                    ['format_id' => $products['Malta']->formats->firstWhere('label', 'Bouteille de 330 ml')->id, 'qty' => '12'],
                    ['format_id' => $products['Water Leaf']->formats->firstWhere('label', 'Boule de 500 g')->id, 'qty' => '3'],
                ],
            ], $manager);
        }
    }
}
