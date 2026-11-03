<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Catalogue de démonstration : les produits, prix et formats des maquettes
 * (maquettes/commande.html). Données d'exemple, jamais chargées en production.
 * Le stock (lots) arrive à l'étape 3.
 */
class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        $u = Unit::pluck('id', 'code');

        $cats = collect([
            ['Légumes-feuilles surgelés', 'feuille', 'vert'],
            ['Farines et féculents', 'sac', 'osier'],
            ['Fruits et légumes', 'plantain', 'osier'],
            ['Poissons séchés', 'poisson', 'bleu'],
            ['Huiles et condiments', 'bouteille', 'terre'],
            ['Épices', 'piment', 'terre'],
            ['Boissons', 'bouteille', 'violet'],
        ])->mapWithKeys(fn ($c, $i) => [$c[0] => Category::create([
            'name' => $c[0], 'icon' => $c[1], 'palette' => $c[2], 'sort_order' => $i + 1,
        ])->id]);

        // [canal, vendu en, nom, contenu (unité de stock), prix, options]
        // options : w = au poids (pas), min, net = [qté, unité]
        $products = [
            ['Water Leaf', 'DJM', 'Légumes-feuilles surgelés', 'unite', ['frozen' => true, 'pop' => 1, 'alert' => 20], [
                ['detail', 'unite', 'Boule de 500 g', '1', '3.99', ['net' => ['500', 'g']]],
                ['gros', 'unite', 'Carton de 50 boules (25 kg)', '50', '175.00', ['net' => ['25', 'kg']]],
            ]],
            ['Feuilles de ndolé', 'DJM', 'Légumes-feuilles surgelés', 'unite', ['frozen' => true, 'pop' => 2, 'alert' => 20], [
                ['detail', 'unite', 'Boule de 500 g', '1', '4.49', ['net' => ['500', 'g']]],
                ['gros', 'unite', 'Carton de 50 boules (25 kg)', '50', '199.00', ['net' => ['25', 'kg']]],
            ]],
            ['Gari jaune', null, 'Farines et féculents', 'kg', ['pop' => 3, 'alert' => 20], [
                ['detail', 'sac', 'Sac de 1 kg', '1', '6.99', []],
                ['detail', 'sac', 'Sac de 5 kg', '5', '29.99', []],
                ['detail', 'kg', 'Vrac, au poids', null, '6.49', ['w' => '0.5']],
                ['gros', 'sac', 'Sac de 25 kg', '25', '129.00', ['min' => '2']],
            ]],
            ['Couscous de manioc', null, 'Farines et féculents', 'kg', ['pop' => 6, 'alert' => 10], [
                ['detail', 'sac', 'Sac de 1 kg', '1', '5.99', []],
                ['gros', 'sac', 'Sac de 25 kg', '25', '119.00', ['min' => '2']],
            ]],
            ['Plantain mûr', null, 'Fruits et légumes', 'kg', ['pop' => 4, 'alert' => 15], [
                ['detail', 'kg', 'Au poids', null, '2.99', ['w' => '0.5']],
                ['gros', 'unite', 'Caisse de 18 kg', '18', '34.90', []],
            ]],
            ['Machoiron fumé', null, 'Poissons séchés', 'kg', ['pop' => 5, 'alert' => 3], [
                ['detail', 'unite', 'La pièce (≈ 350 g)', '0.35', '12.99', []],
                ['detail', 'kg', 'Au poids', null, '36.90', ['w' => '0.5']],
                ['gros', 'kg', 'Carton de 10 kg', '10', '349.00', []],
            ]],
            ['Crevettes séchées', null, 'Poissons séchés', 'g', ['pop' => 7, 'alert' => 1000], [
                ['detail', 'g', 'Sachet de 100 g', '100', '5.49', []],
                ['detail', 'g', 'Sachet de 250 g', '250', '12.49', []],
                ['gros', 'sac', 'Sac de 1 kg', '1000', '42.00', ['min' => '2']],
            ]],
            ['Huile de palme rouge', null, 'Huiles et condiments', 'l', ['pop' => 8, 'alert' => 10], [
                ['detail', 'l', 'Bouteille de 1 L', '1', '8.49', []],
                ['detail', 'l', 'Bidon de 5 L', '5', '36.99', []],
                ['gros', 'l', 'Bidon de 20 L', '20', '129.00', []],
            ]],
            ['Piment rouge moulu', null, 'Épices', 'g', ['pop' => 9, 'alert' => 1000], [
                ['detail', 'g', 'Pot de 100 g', '100', '3.99', []],
                ['gros', 'sac', 'Sac de 1 kg', '1000', '24.90', []],
            ]],
            ['Graines de njansang', null, 'Épices', 'g', ['pop' => 10, 'alert' => 500], [
                ['detail', 'g', 'Sachet de 100 g', '100', '4.99', []],
                ['gros', 'sac', 'Sac de 1 kg', '1000', '39.00', []],
            ]],
            ['Malta', null, 'Boissons', 'unite', ['taxable' => true, 'pop' => 11, 'alert' => 24], [
                ['detail', 'unite', 'Bouteille de 330 ml', '1', '2.29', ['net' => ['0.33', 'l']]],
                ['gros', 'unite', 'Caisse de 24 bouteilles', '24', '49.99', ['net' => ['7.92', 'l']]],
            ]],
        ];

        foreach ($products as [$name, $brand, $cat, $stockUnit, $o, $formats]) {
            $product = Product::create([
                'category_id' => $cats[$cat],
                'name' => $name,
                'brand' => $brand,
                'stock_unit_id' => $u[$stockUnit],
                'is_frozen' => $o['frozen'] ?? false,
                'is_taxable' => $o['taxable'] ?? false,
                'is_visible' => true,
                'allow_preorder' => true,
                'alert_threshold' => (string) $o['alert'],
                'popularity' => $o['pop'],
            ]);

            foreach ($formats as $i => [$channel, $unit, $label, $content, $price, $f]) {
                $byWeight = isset($f['w']);
                $product->formats()->create([
                    'channel' => Channel::from($channel),
                    'unit_id' => $u[$unit],
                    'label' => $label,
                    'is_by_weight' => $byWeight,
                    // Au poids : 1 unité de vente = 1 unité de stock (kg vendu, kg en stock)
                    'content_qty' => $content ?? '1',
                    'net_qty' => $f['net'][0] ?? null,
                    'net_unit_id' => isset($f['net']) ? $u[$f['net'][1]] : null,
                    'price_cents' => (int) round((float) $price * 100),
                    'min_qty' => $f['min'] ?? ($f['w'] ?? '1'),
                    'step' => $f['w'] ?? '1',
                    'is_visible' => true,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
