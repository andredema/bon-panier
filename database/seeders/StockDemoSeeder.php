<?php

namespace Database\Seeders;

use App\Models\ExpectedArrival;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Stock\StockReceiving;
use Illuminate\Database\Seeder;

/**
 * Stock de démonstration : fournisseurs fictifs, une entrée en stock il y a une semaine
 * (niveaux des maquettes) et les arrivages attendus. Jamais chargé en production.
 */
class StockDemoSeeder extends Seeder
{
    public function run(StockReceiving $receiving): void
    {
        $djm = Supplier::create(['name' => 'DJM (démonstration)', 'usual_delay_days' => 7, 'is_active' => true]);
        $flours = Supplier::create(['name' => 'Grossiste farines (démonstration)', 'usual_delay_days' => 5, 'is_active' => true]);
        $oils = Supplier::create(['name' => 'Huiles et épices (démonstration)', 'usual_delay_days' => 10, 'is_active' => true]);

        $p = Product::with('formats')->get()->keyBy('name');
        $user = User::where('email', 'gerant@example.com')->first();
        $receivedOn = today()->subWeek();
        $bb = fn (int $months) => today()->addMonths($months)->toDateString();

        // [produit, source, quantité, n° de lot, date limite, coût par format]
        $receipts = [
            [$djm, [
                ['Water Leaf', 'stock', '38', '017', $bb(6), '3,10'],
                ['Feuilles de ndolé', 'stock', '120', '018', $bb(6), '3,45'],
            ]],
            [$flours, [
                ['Gari jaune', 'stock', '64', null, $bb(10), '4,20'],
                ['Machoiron fumé', 'stock', '22', null, $bb(3), '24,00'],
                ['Crevettes séchées', 'stock', '900', null, $bb(8), null],
            ]],
            [$oils, [
                ['Huile de palme rouge', 'stock', '6', null, $bb(12), '5,50'],
                ['Piment rouge moulu', 'stock', '4000', null, $bb(12), null],
                ['Graines de njansang', 'stock', '2500', null, $bb(12), null],
                ['Malta', 'format:'.$p['Malta']->formats->firstWhere('label', 'Caisse de 24 bouteilles')->id, '4', null, $bb(9), '36,00'],
            ]],
        ];

        foreach ($receipts as [$supplier, $lines]) {
            $receiving->receive(
                ['supplier_id' => $supplier->id, 'received_on' => $receivedOn->toDateString(), 'delivery_note_no' => 'DEMO-'.$supplier->id, 'notify_preorders' => true],
                array_map(fn ($l) => [
                    'product_id' => $p[$l[0]]->id, 'source' => $l[1], 'received_qty' => $l[2],
                    'lot_number' => $l[3], 'best_before' => $l[4], 'unit_cost' => $l[5],
                ], $lines),
                $user,
            );
        }

        // Arrivages attendus (dates des maquettes : plantain dans 2 jours, couscous dans 4)
        foreach ([
            ['Plantain mûr', $flours, 2, '54'],
            ['Couscous de manioc', $flours, 4, '50'],
            ['Feuilles de ndolé', $djm, 0, '100'],
            ['Gari jaune', $flours, 0, '50'],
        ] as [$name, $supplier, $days, $qty]) {
            ExpectedArrival::create([
                'product_id' => $p[$name]->id,
                'supplier_id' => $supplier->id,
                'expected_on' => today()->addDays($days),
                'qty' => $qty,
                'status' => ExpectedArrival::OPEN,
            ]);
        }
    }
}
