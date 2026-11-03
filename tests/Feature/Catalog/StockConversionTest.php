<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Règle 1 : un seul stock par produit ; chaque format indique ce qu'il retire. */
class StockConversionTest extends TestCase
{
    use RefreshDatabase;

    private function unit(string $code): Unit
    {
        return Unit::where('code', $code)->firstOrFail();
    }

    public function test_base_units_are_installed_by_migration(): void
    {
        $this->assertEqualsCanonicalizing(['sac', 'unite', 'l', 'kg', 'g'], Unit::pluck('code')->all());
        $this->assertTrue(Unit::where('is_system', true)->count() === 5);
    }

    public function test_mass_units_convert_exactly(): void
    {
        $kg = $this->unit('kg');
        $g = $this->unit('g');

        $this->assertSame('1000.000', $kg->convert('1', $g));
        $this->assertSame('0.250', $g->convert('250', $kg));
        $this->assertSame('0.001', $g->convert('1', $kg));
        $this->assertNull($g->convert('0,5', $kg), '0,5 g = 0,0005 kg ne tombe pas au millième');
    }

    public function test_units_of_different_kinds_do_not_convert(): void
    {
        $this->assertNull($this->unit('kg')->convert('1', $this->unit('l')));
        $this->assertNull($this->unit('sac')->convert('1', $this->unit('kg')));
        $this->assertSame('3.000', $this->unit('sac')->convert('3', $this->unit('sac')));
    }

    public function test_carton_of_50_balls_removes_50_from_stock(): void
    {
        $product = Product::factory()->stockIn('unite')->create();
        $carton = SaleFormat::factory()->wholesale()->create(['product_id' => $product->id, 'content_qty' => '50', 'price_cents' => 17500]);

        $this->assertSame(100_000, $carton->stockMilliFor('2'));  // 2 cartons = 100 boules
        $this->assertSame(350, $carton->pricePerStockUnitCents()); // 3,50 $ la boule
    }

    public function test_bulk_sale_by_weight_removes_the_weighed_quantity(): void
    {
        $product = Product::factory()->stockIn('kg')->create();
        $bulk = SaleFormat::factory()->create([
            'product_id' => $product->id, 'unit_id' => $this->unit('kg')->id,
            'is_by_weight' => true, 'content_qty' => '1', 'step' => '0.5', 'min_qty' => '0.5',
        ]);

        $this->assertSame(1500, $bulk->stockMilliFor('1,5'));
    }

    public function test_comparison_price_per_kg_or_litre(): void
    {
        $kg = $this->unit('kg');
        $g = $this->unit('g');
        $l = $this->unit('l');

        // Stock compté à la pièce : le poids net donne le prix au kg (boule de 500 g à 3,99 $)
        $waterLeaf = Product::factory()->stockIn('unite')->create();
        $ball = SaleFormat::factory()->create(['product_id' => $waterLeaf->id, 'content_qty' => '1', 'price_cents' => 399, 'net_qty' => '500', 'net_unit_id' => $g->id]);
        $this->assertSame(['cents' => 798, 'unit' => 'kg'], $ball->load('netUnit')->comparisonPrice($waterLeaf->stockUnit));

        // Stock en grammes : sachet de 100 g à 5,49 $ → 54,90 $/kg
        $shrimp = Product::factory()->stockIn('g')->create();
        $bag = SaleFormat::factory()->create(['product_id' => $shrimp->id, 'content_qty' => '100', 'price_cents' => 549]);
        $this->assertSame(['cents' => 5490, 'unit' => 'kg'], $bag->comparisonPrice($shrimp->stockUnit));

        // Bouteille de 330 ml à 2,29 $ → 6,94 $/L
        $malta = Product::factory()->stockIn('unite')->create();
        $bottle = SaleFormat::factory()->create(['product_id' => $malta->id, 'content_qty' => '1', 'price_cents' => 229, 'net_qty' => '0.33', 'net_unit_id' => $l->id]);
        $this->assertSame(['cents' => 694, 'unit' => 'L'], $bottle->load('netUnit')->comparisonPrice($malta->stockUnit));

        // Vente au poids : le prix est déjà au kg, pas de prix comparatif
        $bulk = SaleFormat::factory()->create(['product_id' => Product::factory()->stockIn('kg')->create()->id, 'unit_id' => $kg->id, 'is_by_weight' => true]);
        $this->assertNull($bulk->comparisonPrice($kg));
    }
}
