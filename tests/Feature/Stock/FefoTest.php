<?php

namespace Tests\Feature\Stock;

use App\Models\Lot;
use App\Models\Product;
use App\Services\Stock\FefoAllocator;
use App\Services\Stock\InsufficientStock;
use App\Services\Stock\StockLevels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Règle 5.1 : sortie FEFO, puis FIFO ; stock disponible = Σ restes − réservé. */
class FefoTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = Product::factory()->stockIn('kg')->create();
    }

    private function lot(string $qty, ?string $bestBefore, string $number): Lot
    {
        return Lot::factory()->qty($qty)->create(['product_id' => $this->product->id, 'best_before' => $bestBefore, 'lot_number' => $number]);
    }

    public function test_earliest_best_before_leaves_first_then_oldest_then_undated(): void
    {
        $undated = $this->lot('10', null, 'SANS-DATE');
        $later = $this->lot('10', '2027-03-01', 'MARS');
        $soonOld = $this->lot('4', '2027-01-15', 'JANV-A');
        $soonNew = $this->lot('4', '2027-01-15', 'JANV-B');

        $plan = app(FefoAllocator::class)->plan($this->product, 20_000);

        $this->assertSame(['JANV-A', 'JANV-B', 'MARS', 'SANS-DATE'], array_map(fn ($p) => $p['lot']->lot_number, $plan));
        $this->assertSame([4000, 4000, 10_000, 2000], array_column($plan, 'milli'));
        $this->assertTrue($soonOld->id < $soonNew->id);
        $this->assertSame('10.000', $undated->fresh()->remaining_qty, 'plan() ne modifie rien');
    }

    public function test_take_decrements_lots_and_keeps_traceability(): void
    {
        $a = $this->lot('1.5', '2027-01-01', 'A');
        $b = $this->lot('5', '2027-02-01', 'B');

        $taken = app(FefoAllocator::class)->take($this->product, 2500);

        $this->assertSame([[$a->id, 1500], [$b->id, 1000]], array_map(fn ($p) => [$p['lot']->id, $p['milli']], $taken));
        $this->assertSame('0.000', $a->fresh()->remaining_qty);
        $this->assertSame('4.000', $b->fresh()->remaining_qty);
        $this->assertSame(4000, app(StockLevels::class)->onHandMilli($this->product));
    }

    public function test_insufficient_stock_changes_nothing(): void
    {
        $a = $this->lot('2', null, 'A');

        try {
            app(FefoAllocator::class)->take($this->product, 3000);
            $this->fail('InsufficientStock attendu');
        } catch (InsufficientStock $e) {
            $this->assertSame(3000, $e->requestedMilli);
            $this->assertSame(2000, $e->availableMilli);
        }

        $this->assertSame('2.000', $a->fresh()->remaining_qty);
    }

    public function test_stock_levels_sum_remaining_quantities_exactly(): void
    {
        $this->lot('0.1', null, 'A');
        $this->lot('0.2', null, 'B');
        $this->lot('12.345', null, 'C');
        Lot::factory()->qty('7')->create(); // autre produit

        $levels = app(StockLevels::class);
        $this->assertSame(12_645, $levels->onHandMilli($this->product));
        $this->assertSame(12_645, $levels->availableMilli($this->product));
        $this->assertSame(0, $levels->onHandMilli(Product::factory()->create()));
    }
}
