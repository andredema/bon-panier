<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Mail\PreorderArrived;
use App\Models\ExpectedArrival;
use App\Models\Lot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\User;
use App\Services\Stock\StockLevels;
use App\Services\Stock\StockReceiving;
use Database\Seeders\CatalogDemoSeeder;
use Database\Seeders\OrderDemoSeeder;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Règle 3 et cahier 5.2 : précommandes servies dans l'ordre d'arrivée des commandes. */
class PreorderServingTest extends TestCase
{
    use RefreshDatabase;

    private Product $plantain;

    private SaleFormat $format;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->plantain = Product::factory()->stockIn('kg')->create(['name' => 'Plantain mûr']);
        $this->format = SaleFormat::factory()->create(['product_id' => $this->plantain->id, 'content_qty' => '1', 'label' => 'Au poids']);
    }

    private function preorder(string $qty, OrderStatus $status = OrderStatus::Received, ?string $createdAt = null): OrderItem
    {
        $order = Order::factory()->create(['status' => $status, 'created_at' => $createdAt ?? now(), 'has_preorder' => true]);

        return $order->items()->create([
            'product_id' => $this->plantain->id, 'sale_format_id' => $this->format->id, 'product_name' => 'Plantain mûr',
            'format_label' => 'Au poids', 'channel' => 'detail', 'qty' => $qty, 'stock_qty' => $qty,
            'unit_price_cents' => 299, 'line_total_cents' => 100, 'is_preorder' => true, 'expected_on' => today(),
        ]);
    }

    private function receive(string $qty, bool $notify = true)
    {
        return app(StockReceiving::class)->receive(
            ['received_on' => today()->toDateString(), 'notify_preorders' => $notify],
            [['product_id' => $this->plantain->id, 'source' => 'stock', 'received_qty' => $qty]],
            User::factory()->create(),
        );
    }

    public function test_stock_entry_serves_oldest_orders_first_with_whole_lines(): void
    {
        $first = $this->preorder('3', createdAt: '2026-11-01 09:00');
        $second = $this->preorder('5', createdAt: '2026-11-01 10:00');
        $third = $this->preorder('1', createdAt: '2026-11-01 11:00');

        $receipt = $this->receive('7');

        // 3 kg servis ; 5 kg ne tiennent pas dans les 4 restants → arrêt (le 1 kg plus récent ne passe pas devant)
        $this->assertNotNull($first->fresh()->preorder_served_at);
        $this->assertSame($receipt->id, $first->fresh()->served_receipt_id);
        $this->assertNull($second->fresh()->preorder_served_at);
        $this->assertNull($third->fresh()->preorder_served_at);

        // La ligne servie retient son stock : 7 − 3 = 4 kg disponibles à la vente
        $this->assertSame(4000, app(StockLevels::class)->availableMilli($this->plantain));
    }

    public function test_next_entry_serves_the_following_lines(): void
    {
        $this->preorder('3', createdAt: '2026-11-01 09:00');
        $second = $this->preorder('5', createdAt: '2026-11-01 10:00');
        $third = $this->preorder('1', createdAt: '2026-11-01 11:00');

        $this->receive('7');
        $this->receive('2');   // 4 + 2 = 6 disponibles : 5 puis 1

        $this->assertNotNull($second->fresh()->preorder_served_at);
        $this->assertNotNull($third->fresh()->preorder_served_at);
        $this->assertSame(0, app(StockLevels::class)->availableMilli($this->plantain));
    }

    public function test_customers_are_notified_once_per_order_only_when_asked(): void
    {
        $item = $this->preorder('2');

        $this->receive('1', notify: true);   // ne suffit pas
        Mail::assertNothingQueued();

        $this->receive('5', notify: false);
        Mail::assertNothingQueued();
        $this->assertNull($item->fresh()->preorder_notified_at);

        $other = $this->preorder('3');
        $this->receive('3', notify: true);
        Mail::assertQueued(PreorderArrived::class, 1);
        Mail::assertQueued(PreorderArrived::class, fn ($m) => $m->order->is($other->order) && $m->itemIds === [$other->id]);
        $this->assertNotNull($other->fresh()->preorder_notified_at);
    }

    public function test_cancelled_and_handed_over_orders_are_skipped(): void
    {
        $cancelled = $this->preorder('3', OrderStatus::Cancelled, '2026-11-01 08:00');
        $active = $this->preorder('3', createdAt: '2026-11-01 09:00');

        $this->receive('3');

        $this->assertNull($cancelled->fresh()->preorder_served_at);
        $this->assertNotNull($active->fresh()->preorder_served_at);
    }

    public function test_waiting_lines_take_the_next_announced_arrival_date(): void
    {
        $item = $this->preorder('10');
        $next = ExpectedArrival::factory()->create(['product_id' => $this->plantain->id, 'expected_on' => today()->addDays(9)]);

        // L'arrivage le plus ancien est clos par l'entrée : la ligne prend la date du suivant
        ExpectedArrival::factory()->create(['product_id' => $this->plantain->id, 'expected_on' => today()]);
        $this->receive('2');

        $this->assertNull($item->fresh()->preorder_served_at);
        $this->assertSame($next->expected_on->toDateString(), $item->fresh()->expected_on->toDateString());
    }

    public function test_order_with_pending_preorder_is_flagged(): void
    {
        $item = $this->preorder('2');
        $this->assertTrue($item->order->hasPendingPreorder());

        $this->receive('2');
        $this->assertFalse($item->order->fresh()->hasPendingPreorder());
    }

    public function test_preorder_email_renders(): void
    {
        $item = $this->preorder('2');
        $order = $item->order;

        $html = (new PreorderArrived($order, [$item->id]))->render();

        $this->assertStringContainsString('Plantain mûr', $html);
        $this->assertStringContainsString($order->number, $html);
        $this->assertStringContainsString('/suivi/', $html);
    }

    public function test_staff_can_serve_by_hand_when_stock_was_freed(): void
    {
        Lot::factory()->qty('5')->create(['product_id' => $this->plantain->id]);
        $item = $this->preorder('4');

        $this->actingAs(User::factory()->create())
            ->post("/gestion/precommandes/{$this->plantain->id}/servir", ['notify' => '1'])
            ->assertSessionHas('status', trans_choice('preorders.served_now', 1));

        $item->refresh();
        $this->assertNotNull($item->preorder_served_at);
        $this->assertNull($item->served_receipt_id);
        Mail::assertQueued(PreorderArrived::class);

        $this->actingAs(User::factory()->create())
            ->post("/gestion/precommandes/{$this->plantain->id}/servir")
            ->assertSessionHas('status', __('preorders.served_none'));
    }

    public function test_tracking_shows_the_arrived_preorder(): void
    {
        $item = $this->preorder('2');
        $this->receive('2');

        $this->get(URL::signedRoute('shop.track', ['order' => $item->order->number]))->assertSee(__('preorders.tracking.arrived'));
    }

    public function test_preorders_screen_and_receipt_preview_with_demo_data(): void
    {
        $this->seed([CatalogDemoSeeder::class, StockDemoSeeder::class, OrderDemoSeeder::class]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/gestion/precommandes')->assertOk()
            ->assertSee('Plantain mûr')->assertSee('Couscous de manioc')->assertSee('Client Démo A');

        // Le produit de démonstration (setUp crée déjà un « Plantain mûr » de test)
        $plantain = Product::where('name', 'Plantain mûr')->latest('id')->first();
        $this->actingAs($user)->get("/gestion/entrees/nouvelle?produit={$plantain->id}")->assertOk()
            ->assertViewHas('lines', fn ($lines) => $lines[0]['product']['preorders']->all() === [3000, 4500]);
    }
}
