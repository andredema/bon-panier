<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\ExpectedArrival;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\User;
use Database\Seeders\CatalogDemoSeeder;
use Database\Seeders\OrderDemoSeeder;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->employee = User::factory()->create();
    }

    private function stocked(string $qty = '20'): SaleFormat
    {
        $p = Product::factory()->stockIn('unite')->create(['name' => 'Malta', 'is_taxable' => true]);
        Lot::factory()->qty($qty)->create(['product_id' => $p->id]);

        return SaleFormat::factory()->create(['product_id' => $p->id, 'label' => 'Bouteille', 'price_cents' => 229]);
    }

    private function counter(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Client Comptoir', 'phone' => '450 555-0123', 'consent' => '1',
            'fulfilment' => 'ramassage', 'slot' => now()->format('Y-m-d\TH:i'), 'payment_method' => 'remise',
            'lines' => [['format_id' => $this->stocked()->id, 'qty' => '2']],
        ], $overrides);
    }

    public function test_dashboard_and_orders_screens_with_demo_data(): void
    {
        User::factory()->manager()->create(['email' => 'gerant@example.com']);
        $this->seed([CatalogDemoSeeder::class, StockDemoSeeder::class, OrderDemoSeeder::class]);

        $this->actingAs($this->employee)->get('/gestion')->assertOk()
            ->assertSee('Commandes à traiter')->assertSee('Client Démo A')->assertSee('Précommandes à servir')->assertSee('Ventes des 7 derniers jours');

        $this->actingAs($this->employee)->get('/gestion/commandes')->assertOk()->assertSee('Client Démo C');
        $this->actingAs($this->employee)->get('/gestion/commandes?preco=1&statut=toutes')->assertOk()->assertSee('Client Démo B');
        $this->actingAs($this->employee)->get('/gestion/commandes?q=Démo B')->assertSee('Client Démo B')->assertDontSee('Client Démo A');

        $order = Order::first();
        $this->actingAs($this->employee)->get("/gestion/commandes/{$order->number}")->assertOk()->assertSee(__('orders.show.blocked'));
        $this->actingAs($this->employee)->get("/gestion/commandes/{$order->number}/preparation")->assertOk()->assertSee(__('orders.picking.waiting'));
    }

    public function test_next_step_button_and_french_error_on_block(): void
    {
        $order = Order::factory()->create();
        $p = Product::factory()->create();
        $f = SaleFormat::factory()->create(['product_id' => $p->id]);
        $order->items()->create(['product_id' => $p->id, 'sale_format_id' => $f->id, 'product_name' => 'X', 'format_label' => 'X', 'channel' => 'detail',
            'qty' => '1', 'stock_qty' => '1', 'unit_price_cents' => 100, 'line_total_cents' => 100, 'is_preorder' => true]);

        $this->actingAs($this->employee)->post("/gestion/commandes/{$order->number}/suivant")
            ->assertSessionHas('status', __('orders.done.confirmee', ['number' => $order->number]));
        $this->actingAs($this->employee)->post("/gestion/commandes/{$order->number}/suivant")
            ->assertSessionHasErrors(['order' => __('orders.errors.pending_preorder')]);
    }

    public function test_cancel_from_the_order_page(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->employee)->post("/gestion/commandes/{$order->number}/annuler", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->employee)->post("/gestion/commandes/{$order->number}/annuler", ['reason' => 'Doublon'])->assertRedirect();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_counter_order_with_immediate_handover(): void
    {
        $this->actingAs($this->employee)->post('/gestion/commandes', $this->counter(['hand_over' => '1']))->assertRedirect();

        $order = Order::with('items.lots')->firstOrFail();
        $this->assertSame(Order::SOURCE_COUNTER, $order->source);
        $this->assertSame($this->employee->id, $order->created_by);
        $this->assertSame(OrderStatus::HandedOver, $order->status);
        $this->assertCount(1, $order->items->first()->lots);
        $this->assertSame(458 + 23 + 46, $order->total_cents); // 2 × 2,29 $ + TPS + TVQ
        $this->assertNull($order->customer->email);
        Mail::assertNothingQueued();
    }

    public function test_counter_order_reuses_customer_and_sends_confirmation(): void
    {
        $existing = Customer::factory()->create(['email' => 'fatou@example.com']);

        $this->actingAs($this->employee)->post('/gestion/commandes', $this->counter(['email' => 'Fatou@example.com', 'send_mail' => '1']))->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertTrue($order->customer->is($existing));
        $this->assertSame(OrderStatus::Received, $order->status);
        Mail::assertQueued(OrderConfirmation::class);
    }

    public function test_counter_order_follows_the_same_rules(): void
    {
        $format = $this->stocked('1');
        $this->actingAs($this->employee)->post('/gestion/commandes', $this->counter(['lines' => [['format_id' => $format->id, 'qty' => '5']]]))
            ->assertSessionHasErrors('lines');

        ExpectedArrival::factory()->create(['product_id' => $format->product_id]);
        $this->actingAs($this->employee)->post('/gestion/commandes', $this->counter(['lines' => [['format_id' => $format->id, 'qty' => '5']], 'hand_over' => '1']))
            ->assertSessionHasErrors(['hand_over' => __('orders.errors.hand_over_preorder')]);

        $this->actingAs($this->employee)->post('/gestion/commandes', $this->counter(['consent' => null, 'lines' => []]))
            ->assertSessionHasErrors(['consent', 'lines']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_counter_preview_and_product_search(): void
    {
        $format = $this->stocked();

        $this->actingAs($this->employee)->postJson('/gestion/commandes/apercu', ['lines' => [['format_id' => $format->id, 'qty' => '2']]])
            ->assertOk()->assertJsonPath('cart.totals.subtotal', 458);
        $this->actingAs($this->employee)->getJson('/gestion/commandes/produits?q=mal')
            ->assertOk()->assertJsonPath('0.name', 'Malta');
    }
}
