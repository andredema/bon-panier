<?php

namespace Tests\Feature\Stock;

use App\Models\ExpectedArrival;
use App\Models\Lot;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Format;
use Database\Seeders\CatalogDemoSeeder;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employee = User::factory()->create();
    }

    public function test_employee_receives_stock_from_the_screen(): void
    {
        $product = Product::factory()->stockIn('unite')->create(['name' => 'Water Leaf']);
        $carton = SaleFormat::factory()->create(['product_id' => $product->id, 'content_qty' => '50']);

        $this->actingAs($this->employee)->get('/gestion/entrees/nouvelle')->assertOk()->assertSee('Bon de réception');

        $response = $this->actingAs($this->employee)->post('/gestion/entrees', [
            'received_on' => today()->toDateString(),
            'notify_preorders' => '1',
            'lines' => [['product_id' => $product->id, 'source' => 'format:'.$carton->id, 'received_qty' => '2']],
        ]);

        $receipt = StockReceipt::first();
        $response->assertRedirect("/gestion/entrees/{$receipt->id}");
        $this->assertSame('100.000', Lot::first()->converted_qty);

        $this->actingAs($this->employee)->get("/gestion/entrees/{$receipt->id}")->assertOk()->assertSee('Water Leaf')->assertSee($receipt->number());
    }

    public function test_print_labels_option_opens_the_labels_page(): void
    {
        $product = Product::factory()->stockIn('kg')->create();

        $this->actingAs($this->employee)->post('/gestion/entrees', [
            'received_on' => today()->toDateString(),
            'print_labels' => '1',
            'lines' => [['product_id' => $product->id, 'source' => 'stock', 'received_qty' => '5', 'lot_number' => 'A-12', 'best_before' => '2027-01-31']],
        ])->assertRedirect('/gestion/entrees/'.StockReceipt::first()->id.'/etiquettes');

        $this->actingAs($this->employee)->get('/gestion/entrees/'.StockReceipt::first()->id.'/etiquettes')
            ->assertOk()->assertSee('Lot A-12')->assertSee('31 janvier 2027');
    }

    public function test_errors_bring_lines_back_with_their_product(): void
    {
        $product = Product::factory()->stockIn('kg')->create(['name' => 'Gari jaune']);

        $this->actingAs($this->employee)->from('/gestion/entrees/nouvelle')->post('/gestion/entrees', [
            'received_on' => today()->toDateString(),
            'lines' => [['product_id' => $product->id, 'source' => 'stock', 'received_qty' => '0']],
        ])->assertSessionHasErrors('lines.0.received_qty');

        $this->actingAs($this->employee)->get('/gestion/entrees/nouvelle')->assertSee('Gari jaune');
    }

    public function test_product_search_finds_barcode_first(): void
    {
        Product::factory()->create(['name' => 'Gari jaune 0613']);
        $scanned = Product::factory()->create(['name' => 'Malta', 'barcode' => '0613']);

        $json = $this->actingAs($this->employee)->getJson('/gestion/entrees/produits?q=0613')->assertOk()->json();

        $this->assertSame($scanned->id, $json[0]['id']);
        $this->assertSame(['stock', 'other'], array_column($json[0]['sources'], 'value'));
        $this->assertSame([], $this->actingAs($this->employee)->getJson('/gestion/entrees/produits?q=')->json());
    }

    public function test_journal_filters_by_date(): void
    {
        $old = StockReceipt::factory()->create(['received_on' => '2026-10-01']);
        $new = StockReceipt::factory()->create(['received_on' => '2026-11-01']);

        $this->actingAs($this->employee)->get('/gestion/entrees?du=2026-10-15')
            ->assertOk()->assertSee($new->number())->assertDontSee($old->number());
    }

    public function test_expected_arrivals_are_announced_and_cancelled(): void
    {
        $product = Product::factory()->stockIn('kg')->create(['name' => 'Plantain mûr']);

        $this->actingAs($this->employee)->post('/gestion/arrivages', [
            'product_id' => $product->id, 'expected_on' => today()->addDays(2)->toDateString(), 'qty' => '54',
        ])->assertSessionHasNoErrors();

        $arrival = ExpectedArrival::first();
        $this->assertSame('54.000', $arrival->qty);
        $this->actingAs($this->employee)->get('/gestion/arrivages')->assertSee('Plantain mûr');

        $this->actingAs($this->employee)->patch("/gestion/arrivages/{$arrival->id}/annuler")->assertRedirect();
        $this->assertSame(ExpectedArrival::CANCELLED, $arrival->fresh()->status);

        $this->actingAs($this->employee)->post('/gestion/arrivages', [
            'product_id' => $product->id, 'expected_on' => today()->subDay()->toDateString(),
        ])->assertSessionHasErrors('expected_on');
    }

    public function test_suppliers_are_managed(): void
    {
        $this->actingAs($this->employee)->post('/gestion/fournisseurs', ['name' => 'Grossiste Laval', 'usual_delay_days' => '5'])->assertSessionHasNoErrors();
        $supplier = Supplier::first();

        $this->actingAs($this->employee)->put("/gestion/fournisseurs/{$supplier->id}", ['name' => 'Grossiste Laval', 'email' => 'pas-un-courriel'])
            ->assertSessionHasErrors('email');
        $this->actingAs($this->employee)->put("/gestion/fournisseurs/{$supplier->id}", ['name' => 'Grossiste Laval'])->assertSessionHasNoErrors();
        $this->assertFalse($supplier->fresh()->is_active);
    }

    public function test_product_sheet_and_list_show_stock(): void
    {
        $manager = User::factory()->manager()->create(['email' => 'gerant@example.com']);
        $this->seed([CatalogDemoSeeder::class, StockDemoSeeder::class]);
        $gari = Product::firstWhere('name', 'Gari jaune');
        $couscous = Product::firstWhere('name', 'Couscous de manioc');

        $this->actingAs($manager)->get('/gestion/produits')->assertSee('64 kg')->assertSee(__('stock.product.at_zero'));
        $this->actingAs($manager)->get("/gestion/produits/{$gari->id}/edit")->assertOk()->assertSee('64 kg')->assertSee(__('stock.product.lots'));
        $this->actingAs($manager)->get("/gestion/produits/{$couscous->id}/edit")
            ->assertSee(__('stock.product.next_arrival', ['date' => Format::date(today()->addDays(4))]));
        $this->actingAs($manager)->get('/gestion/entrees/nouvelle')->assertOk()->assertSee('Plantain mûr');
    }

    public function test_guest_cannot_reach_stock_screens(): void
    {
        $this->get('/gestion/entrees/nouvelle')->assertRedirect('/gestion/connexion');
        $this->getJson('/gestion/entrees/produits?q=a')->assertUnauthorized();
    }
}
