<?php

namespace Tests\Feature\Reports;

use App\Mail\AccountingPack;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Billing\CreditNoteIssuer;
use App\Services\Billing\InvoiceIssuer;
use App\Services\Reports\Period;
use App\Services\Reports\Reports;
use App\Support\Csv;
use App\Support\Settings;
use Database\Seeders\CatalogDemoSeeder;
use Database\Seeders\OrderDemoSeeder;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Cahier des charges 6 : rapports et documents comptables. */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->manager = User::factory()->manager()->create(['email' => 'gerant@example.com']);
        Carbon::setTestNow('2026-11-15 12:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Facture : $taxable cents taxables sur $subtotal, émise le $date. */
    private function invoice(int $subtotal, int $taxable, string $date, string $channel = 'detail'): Invoice
    {
        Carbon::setTestNow($date);
        $tps = intdiv($taxable * 5000 + 50000, 100000);
        $tvq = intdiv($taxable * 9975 + 50000, 100000);
        $order = Order::factory()->create(['customer_id' => Customer::factory()->create()->id, 'subtotal_cents' => $subtotal, 'taxable_cents' => $taxable,
            'tax_tps_cents' => $tps, 'tax_tvq_cents' => $tvq, 'total_cents' => $subtotal + $tps + $tvq, 'channel' => $channel]);
        $p = Product::factory()->create(['is_taxable' => $taxable > 0]);
        $f = SaleFormat::factory()->create(['product_id' => $p->id]);
        if ($subtotal - $taxable > 0) {
            $order->items()->create(['product_id' => $p->id, 'sale_format_id' => $f->id, 'product_name' => 'Gari jaune', 'format_label' => 'Sac',
                'channel' => $channel, 'qty' => '1', 'stock_qty' => '1', 'unit_price_cents' => $subtotal - $taxable, 'line_total_cents' => $subtotal - $taxable, 'is_taxable' => false]);
        }
        if ($taxable > 0) {
            $order->items()->create(['product_id' => $p->id, 'sale_format_id' => $f->id, 'product_name' => 'Malta', 'format_label' => 'Bouteille',
                'channel' => $channel, 'qty' => '1', 'stock_qty' => '1', 'unit_price_cents' => $taxable, 'line_total_cents' => $taxable, 'is_taxable' => true]);
        }
        $invoice = app(InvoiceIssuer::class)->issue($order, null, false);
        Carbon::setTestNow('2026-11-15 12:00');

        return $invoice;
    }

    public function test_csv_is_made_for_french_excel(): void
    {
        $csv = Csv::content(['Produit', 'Montant'], [['Gari jaune; sac', Csv::money(128450)], ['Négatif', Csv::money(-2748)], ['Qté', Csv::qty('1.500')]]);

        $this->assertStringStartsWith("\u{FEFF}Produit;Montant\n", $csv);
        $this->assertStringContainsString("\"Gari jaune; sac\";1284,50\n", $csv);
        $this->assertStringContainsString("Négatif;-27,48\n", $csv);
        $this->assertStringContainsString("Qté;1,5\n", $csv);
    }

    public function test_periods(): void
    {
        $this->assertSame(['2026-10-01', '2026-10-31'], [Period::fromRequest('2026-10')->from->toDateString(), Period::fromRequest('2026-10')->to->toDateString()]);
        $this->assertSame('Octobre 2026', Period::fromRequest('2026-10')->label);
        $q = Period::fromRequest('2026-T3');
        $this->assertSame(['2026-07-01', '2026-09-30'], [$q->from->toDateString(), $q->to->toDateString()]);
        $this->assertSame('3e trimestre 2026', $q->label);
        $this->assertSame('Novembre 2026', Period::fromRequest('n’importe quoi')->label);
        $this->assertSame('libre', Period::fromRequest(null, '2026-11-01', '2026-11-10')->key);
    }

    public function test_tax_report_nets_credit_notes_of_the_period(): void
    {
        // Facture de la maquette : 247,42 $ dont 27,48 $ taxables → TPS 1,37 $, TVQ 2,74 $
        $a = $this->invoice(24742, 2748, '2026-10-05');
        $this->invoice(5000, 0, '2026-10-20', 'gros');
        $this->invoice(9999, 1000, '2026-09-30'); // hors période

        Carbon::setTestNow('2026-10-25');
        $malta = $a->lines()->where('is_taxable', true)->first();
        app(CreditNoteIssuer::class)->issue($a, [$malta->id => 458], false, 'Bouteilles cassées', $this->manager);
        Carbon::setTestNow('2026-11-15 12:00');

        $t = app(Reports::class)->taxes(Period::fromRequest('2026-10'));

        $this->assertSame(21994 + 5000, $t['zero_rated']);
        $this->assertSame(2748, $t['taxable']);
        $this->assertSame(458, $t['credits']);
        $this->assertSame(137 - 23, $t['tps']);
        $this->assertSame(274 - 46, $t['tvq']);
        $this->assertSame(25153 + 5000 - (458 + 23 + 46), $t['total']);
    }

    public function test_summary_channels_categories_and_top_products(): void
    {
        $a = $this->invoice(24742, 2748, '2026-10-05');
        $this->invoice(5000, 0, '2026-10-20', 'gros');
        Payment::create(['order_id' => $a->order_id, 'method' => 'interac', 'amount_cents' => 10000, 'paid_at' => '2026-10-06']);

        $r = app(Reports::class);
        $p = Period::fromRequest('2026-10');
        $s = $r->summary($p);

        $this->assertSame(29742, $s['sales']);
        $this->assertSame(2, $s['count']);
        $this->assertSame(14871, $s['average']);
        $this->assertSame(10000, $s['collected']);
        $this->assertSame(['detail' => 24742, 'gros' => 5000], $r->byChannel($p));
        $this->assertSame(['interac' => 10000], $r->paymentsByMethod($p));
        $this->assertSame('Gari jaune', $r->topProducts($p)[0]['name']);
        $this->assertSame(21994 + 5000, $r->topProducts($p)[0]['cents']);
    }

    public function test_receivables_are_aged_from_the_invoice_date(): void
    {
        $this->invoice(1000, 0, '2026-11-10');   // 5 jours
        $this->invoice(2000, 0, '2026-10-01');   // 45 jours
        $old = $this->invoice(3000, 0, '2026-08-01'); // 106 jours
        $paid = $this->invoice(4000, 0, '2026-11-01');
        Payment::create(['order_id' => $paid->order_id, 'method' => 'comptant', 'amount_cents' => 4000, 'paid_at' => now()]);

        $r = app(Reports::class)->receivables();

        $this->assertSame(['0-30' => 1000, '31-60' => 2000, '60+' => 3000], $r['buckets']);
        $this->assertSame(6000, $r['total']);
        $this->assertCount(3, $r['rows']);
        $this->assertTrue($r['rows'][0]['invoice']->is($old));
    }

    public function test_inventory_is_valued_at_cost_proportionally(): void
    {
        $p = Product::factory()->stockIn('unite')->create(['name' => 'Water Leaf']);
        Lot::factory()->create(['product_id' => $p->id, 'received_qty' => '10', 'converted_qty' => '500', 'remaining_qty' => '100', 'cost_cents' => 155000, 'best_before' => now()->addDays(10)]);
        Lot::factory()->qty('20')->create(['product_id' => $p->id, 'cost_cents' => null]);

        $inv = app(Reports::class)->inventory();

        $this->assertSame(31000, $inv['total']); // 100/500 × 1 550 $
        $this->assertSame(1, $inv['without_cost']);
        $this->assertCount(1, $inv['soon']);
    }

    public function test_stock_adjustment_updates_the_lot_and_is_journaled(): void
    {
        $p = Product::factory()->stockIn('kg')->create(['name' => 'Gari jaune']);
        $lot = Lot::factory()->create(['product_id' => $p->id, 'received_qty' => '25', 'converted_qty' => '25', 'remaining_qty' => '25', 'cost_cents' => 10000]);

        $this->actingAs($this->manager)->get("/gestion/lots/{$lot->id}/ajuster")->assertOk()->assertSee('Gari jaune');
        $this->actingAs($this->manager)->post("/gestion/lots/{$lot->id}/ajuster", ['qty' => '22,5', 'reason' => 'casse'])
            ->assertRedirect("/gestion/produits/{$p->id}/edit");

        $this->assertSame('22.500', $lot->fresh()->remaining_qty);
        $a = StockAdjustment::firstOrFail();
        $this->assertSame('-2.500', $a->delta);
        $this->assertSame(-1000, $a->value_cents); // 2,5/25 × 100 $
        $this->assertSame($this->manager->id, $a->user_id);

        $this->actingAs($this->manager)->post("/gestion/lots/{$lot->id}/ajuster", ['qty' => '22,5', 'reason' => 'casse'])->assertSessionHasErrors('qty');
        $this->actingAs($this->manager)->post("/gestion/lots/{$lot->id}/ajuster", ['qty' => '20', 'reason' => 'autre'])->assertSessionHasErrors('note');
    }

    public function test_adjustment_warns_when_orders_hold_more_than_the_stock(): void
    {
        $p = Product::factory()->stockIn('unite')->create();
        $lot = Lot::factory()->qty('5')->create(['product_id' => $p->id]);
        $order = Order::factory()->create();
        $f = SaleFormat::factory()->create(['product_id' => $p->id]);
        $order->items()->create(['product_id' => $p->id, 'sale_format_id' => $f->id, 'product_name' => 'X', 'format_label' => 'X', 'channel' => 'detail',
            'qty' => '4', 'stock_qty' => '4', 'unit_price_cents' => 100, 'line_total_cents' => 400]);

        $this->actingAs($this->manager)->post("/gestion/lots/{$lot->id}/ajuster", ['qty' => '1', 'reason' => 'perte'])->assertSessionHas('warnings');
    }

    public function test_report_screen_csv_and_pdf_with_demo_data(): void
    {
        Carbon::setTestNow();
        $this->seed([CatalogDemoSeeder::class, StockDemoSeeder::class, OrderDemoSeeder::class]);
        $month = now()->format('Y-m');

        $this->actingAs($this->manager)->get("/gestion/rapports?periode={$month}")->assertOk()
            ->assertSee('Rapport de taxes')->assertSee('Journal des ventes')->assertSee('F-'.now()->format('Y').'-0001')->assertSee('71,52');

        foreach (['ventes', 'taxes', 'inventaire', 'entrees', 'ajustements', 'comptes-clients'] as $type) {
            $response = $this->actingAs($this->manager)->get("/gestion/rapports/csv/{$type}?periode={$month}");
            $response->assertOk();
            $this->assertStringStartsWith("\u{FEFF}", $response->streamedContent(), $type);
        }
        $this->assertStringContainsString('71,52', $this->actingAs($this->manager)->get("/gestion/rapports/csv/ventes?periode={$month}")->streamedContent());

        foreach (['mensuel', 'taxes', 'comptes-clients', 'inventaire'] as $type) {
            $this->actingAs($this->manager)->get("/gestion/rapports/pdf/{$type}?periode={$month}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_reports_are_for_the_manager_only(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get('/gestion/rapports')->assertForbidden();
        $this->actingAs($employee)->get('/gestion/rapports/csv/ventes')->assertForbidden();
    }

    public function test_send_everything_to_the_accountant(): void
    {
        $this->actingAs($this->manager)->post('/gestion/rapports/envoyer?periode=2026-10')->assertSessionHasErrors('accountant');

        Settings::set(['accounting.email' => 'comptable@example.com']);
        $this->invoice(1000, 0, '2026-10-05');
        $this->actingAs($this->manager)->post('/gestion/rapports/envoyer?periode=2026-10')->assertSessionHas('status');

        Mail::assertQueued(AccountingPack::class, function (AccountingPack $m) {
            return $m->hasTo('comptable@example.com') && count($m->attachments()) === 7;
        });
    }

    public function test_unknown_report_type_is_not_found(): void
    {
        $this->actingAs($this->manager)->get('/gestion/rapports/csv/secret')->assertNotFound();
        $this->actingAs($this->manager)->get('/gestion/rapports/pdf/secret')->assertNotFound();
    }
}
