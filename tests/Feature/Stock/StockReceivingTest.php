<?php

namespace Tests\Feature\Stock;

use App\Events\StockReceived;
use App\Models\ExpectedArrival;
use App\Models\Lot;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Stock\StockLevels;
use App\Services\Stock\StockReceiving;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Règle 6 : chaque entrée en stock crée un lot ; conversion en unité de stock. */
class StockReceivingTest extends TestCase
{
    use RefreshDatabase;

    private StockReceiving $receiving;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->receiving = app(StockReceiving::class);
        $this->user = User::factory()->create();
    }

    private function receive(array $lines, array $header = []): StockReceipt
    {
        return $this->receiving->receive($header + ['received_on' => today()->toDateString()], $lines, $this->user);
    }

    private function errorsOf(callable $fn): array
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('Une erreur de validation était attendue.');
    }

    public function test_carton_of_50_balls_enters_500_balls_for_10_cartons(): void
    {
        $waterLeaf = Product::factory()->stockIn('unite')->create(['name' => 'Water Leaf']);
        $carton = SaleFormat::factory()->wholesale()->create(['product_id' => $waterLeaf->id, 'label' => 'Carton de 50 boules', 'content_qty' => '50']);

        $receipt = $this->receive([[
            'product_id' => $waterLeaf->id, 'source' => 'format:'.$carton->id, 'received_qty' => '10',
            'lot_number' => '017', 'best_before' => '2027-05-01', 'unit_cost' => '155,00',
        ]], ['supplier_id' => Supplier::factory()->create()->id, 'delivery_note_no' => 'BL-4471']);

        $lot = $receipt->lots->first();
        $this->assertSame('500.000', $lot->converted_qty);
        $this->assertSame('500.000', $lot->remaining_qty);
        $this->assertSame('Carton de 50 boules', $lot->received_label);
        $this->assertSame($carton->id, $lot->sale_format_id);
        $this->assertSame('017', $lot->lot_number);
        $this->assertSame('2027-05-01', $lot->best_before->toDateString());
        $this->assertSame(15500, $lot->unit_cost_cents);
        $this->assertSame(155000, $lot->cost_cents);
        $this->assertSame($this->user->id, $receipt->user_id);
        $this->assertSame(500_000, app(StockLevels::class)->onHandMilli($waterLeaf));
    }

    public function test_weight_received_directly_in_stock_unit_allows_decimals(): void
    {
        $plantain = Product::factory()->stockIn('kg')->create();

        $receipt = $this->receive([['product_id' => $plantain->id, 'source' => 'stock', 'received_qty' => '12,5']]);

        $this->assertSame('12.500', $receipt->lots->first()->converted_qty);
    }

    public function test_other_packaging_converts_with_its_content(): void
    {
        $couscous = Product::factory()->stockIn('kg')->create();

        $receipt = $this->receive([[
            'product_id' => $couscous->id, 'source' => 'other', 'received_qty' => '3',
            'other_label' => 'Carton de 10 sacs de 1 kg', 'other_content' => '10',
        ]]);

        $lot = $receipt->lots->first();
        $this->assertSame('Carton de 10 sacs de 1 kg', $lot->received_label);
        $this->assertSame('30.000', $lot->converted_qty);
        $this->assertNull($lot->sale_format_id);
    }

    public function test_lot_number_is_generated_when_empty(): void
    {
        $product = Product::factory()->stockIn('kg')->create();
        $receipt = $this->receive([['product_id' => $product->id, 'source' => 'stock', 'received_qty' => '5']]);

        $this->assertSame('L'.today()->format('ymd').'-'.$receipt->id.'-1', $receipt->lots->first()->lot_number);
    }

    public function test_packages_are_counted_whole_and_conversion_must_be_exact(): void
    {
        $product = Product::factory()->stockIn('unite')->create();
        $format = SaleFormat::factory()->create(['product_id' => $product->id, 'content_qty' => '50']);

        $errors = $this->errorsOf(fn () => $this->receive([['product_id' => $product->id, 'source' => 'format:'.$format->id, 'received_qty' => '1,5']]));
        $this->assertSame([__('stock.rules.qty_whole')], $errors['lines.0.received_qty']);

        // Stock à l'unité : 2,5 unités n'existent pas
        $errors = $this->errorsOf(fn () => $this->receive([['product_id' => $product->id, 'source' => 'stock', 'received_qty' => '2,5']]));
        $this->assertArrayHasKey('lines.0.received_qty', $errors);

        $this->assertSame(0, Lot::count());
    }

    public function test_by_weight_formats_are_not_offered_as_received_format(): void
    {
        $product = Product::factory()->stockIn('kg')->create();
        $bulk = SaleFormat::factory()->create(['product_id' => $product->id, 'unit_id' => Unit::where('code', 'kg')->value('id'), 'is_by_weight' => true, 'label' => 'Vrac']);

        $values = array_column(StockReceiving::sourcesFor($product->load(['formats', 'stockUnit'])), 'value');
        $this->assertNotContains('format:'.$bulk->id, $values);
        $this->assertSame(['stock', 'other'], $values);

        $errors = $this->errorsOf(fn () => $this->receive([['product_id' => $product->id, 'source' => 'format:'.$bulk->id, 'received_qty' => '1']]));
        $this->assertArrayHasKey('lines.0.source', $errors);
    }

    public function test_header_is_validated(): void
    {
        $errors = $this->errorsOf(fn () => $this->receiving->receive(['received_on' => today()->addDay()->toDateString(), 'supplier_id' => 999], [], $this->user));

        $this->assertArrayHasKey('received_on', $errors);
        $this->assertArrayHasKey('supplier_id', $errors);
        $this->assertArrayHasKey('lines', $errors);
    }

    public function test_past_best_before_is_a_warning_not_an_error(): void
    {
        $product = Product::factory()->stockIn('kg')->create(['name' => 'Gari jaune']);

        $this->receive([['product_id' => $product->id, 'source' => 'stock', 'received_qty' => '5', 'best_before' => today()->subDay()->toDateString()]]);

        $this->assertSame(1, Lot::count());
        $this->assertSame([__('stock.rules.best_before_past', ['product' => 'Gari jaune'])], $this->receiving->warnings());
    }

    public function test_receiving_closes_the_oldest_expected_arrival_and_dispatches_the_event(): void
    {
        Event::fake([StockReceived::class]);
        $product = Product::factory()->stockIn('kg')->create();
        $first = ExpectedArrival::factory()->create(['product_id' => $product->id, 'expected_on' => today()]);
        $second = ExpectedArrival::factory()->create(['product_id' => $product->id, 'expected_on' => today()->addWeek()]);

        $receipt = $this->receive([
            ['product_id' => $product->id, 'source' => 'stock', 'received_qty' => '5'],
            ['product_id' => $product->id, 'source' => 'stock', 'received_qty' => '3'],
        ]);

        $this->assertSame(ExpectedArrival::RECEIVED, $first->fresh()->status);
        $this->assertSame($receipt->id, $first->fresh()->stock_receipt_id);
        $this->assertSame(ExpectedArrival::OPEN, $second->fresh()->status, 'Un seul arrivage clos par produit et par entrée');
        Event::assertDispatched(StockReceived::class, fn ($e) => $e->receipt->is($receipt));
    }

    public function test_next_arrival_ignores_past_and_closed_arrivals(): void
    {
        $product = Product::factory()->create();
        ExpectedArrival::factory()->create(['product_id' => $product->id, 'expected_on' => today()->subDay()]);
        ExpectedArrival::factory()->create(['product_id' => $product->id, 'expected_on' => today()->addDay(), 'status' => ExpectedArrival::CANCELLED]);
        $next = ExpectedArrival::factory()->create(['product_id' => $product->id, 'expected_on' => today()->addDays(4)]);

        $this->assertTrue($product->nextArrival()->is($next));
    }
}
