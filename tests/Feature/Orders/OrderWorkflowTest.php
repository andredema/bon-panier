<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Events\OrderHandedOver;
use App\Mail\OrderCancelled;
use App\Mail\OrderReady;
use App\Models\Lot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\User;
use App\Services\Orders\OrderWorkflow;
use App\Services\Orders\WorkflowException;
use App\Services\Stock\StockLevels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Règle 7 et cahier 5.5 : statuts, remise FEFO avec lots, annulation. */
class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private SaleFormat $format;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->user = User::factory()->create();
        $this->product = Product::factory()->stockIn('kg')->create(['name' => 'Gari jaune']);
        $this->format = SaleFormat::factory()->create(['product_id' => $this->product->id, 'label' => 'Sac de 1 kg', 'content_qty' => '1']);
    }

    private function order(string $qty = '3', bool $preorder = false, array $attrs = []): Order
    {
        $order = Order::factory()->create($attrs);
        $order->items()->create([
            'product_id' => $this->product->id, 'sale_format_id' => $this->format->id, 'product_name' => 'Gari jaune',
            'format_label' => 'Sac de 1 kg', 'channel' => 'detail', 'qty' => $qty, 'stock_qty' => $qty,
            'unit_price_cents' => 699, 'line_total_cents' => 699, 'is_preorder' => $preorder,
        ]);
        $order->history()->create(['to' => OrderStatus::Received]);

        return $order;
    }

    private function workflow(): OrderWorkflow
    {
        return app(OrderWorkflow::class);
    }

    public function test_normal_path_is_logged_with_user_and_ready_mail(): void
    {
        Lot::factory()->qty('10')->create(['product_id' => $this->product->id]);
        $order = $this->order();

        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready] as $expected) {
            $this->workflow()->advance($order, $this->user);
            $this->assertSame($expected, $order->fresh()->status);
        }

        $history = $order->history()->get();
        $this->assertSame(['recue', 'confirmee', 'en_preparation', 'prete'], $history->map(fn ($h) => $h->to->value)->all());
        $this->assertSame($this->user->id, $history->last()->user_id);
        Mail::assertQueued(OrderReady::class, fn ($m) => $m->order->is($order));
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $order = $this->order();

        $this->expectException(WorkflowException::class);
        $this->workflow()->transition($order, OrderStatus::Ready, $this->user);
    }

    public function test_pending_preorder_keeps_the_order_at_confirmed(): void
    {
        $order = $this->order('2', preorder: true);

        $this->workflow()->advance($order, $this->user);
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);

        try {
            $this->workflow()->advance($order, $this->user);
            $this->fail('La précommande non servie doit bloquer');
        } catch (WorkflowException $e) {
            $this->assertSame(__('orders.errors.pending_preorder'), $e->getMessage());
        }

        // Une fois servie, la commande avance
        OrderItem::query()->update(['preorder_served_at' => now()]);
        Lot::factory()->qty('2')->create(['product_id' => $this->product->id]);
        $this->workflow()->advance($order->fresh(), $this->user);
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }

    public function test_handover_takes_lots_fefo_and_keeps_traceability(): void
    {
        Event::fake([OrderHandedOver::class]);
        $late = Lot::factory()->qty('5')->create(['product_id' => $this->product->id, 'best_before' => '2027-06-01', 'lot_number' => 'TARD']);
        $soon = Lot::factory()->qty('2')->create(['product_id' => $this->product->id, 'best_before' => '2027-01-01', 'lot_number' => 'TOT']);
        $order = $this->order('3', attrs: ['status' => OrderStatus::Ready]);

        $this->assertSame(4000, app(StockLevels::class)->availableMilli($this->product)); // 7 − 3 retenus

        $this->workflow()->advance($order, $this->user);

        $order->refresh();
        $this->assertSame(OrderStatus::HandedOver, $order->status);
        $this->assertNotNull($order->handed_over_at);
        $this->assertSame([['TOT', '2.000'], ['TARD', '1.000']],
            $order->items()->first()->lots()->with('lot')->get()->map(fn ($l) => [$l->lot->lot_number, $l->qty])->all());
        $this->assertSame('0.000', $soon->fresh()->remaining_qty);
        $this->assertSame('4.000', $late->fresh()->remaining_qty);
        // Remise : plus rien de retenu, le stock en main a baissé d'autant
        $this->assertSame(4000, app(StockLevels::class)->availableMilli($this->product));
        Event::assertDispatched(OrderHandedOver::class);
    }

    public function test_handover_without_enough_stock_changes_nothing(): void
    {
        Lot::factory()->qty('1')->create(['product_id' => $this->product->id]);
        $order = $this->order('3', attrs: ['status' => OrderStatus::Ready]);

        try {
            $this->workflow()->advance($order, $this->user);
            $this->fail('Stock insuffisant attendu');
        } catch (WorkflowException) {
        }

        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
        $this->assertSame('1.000', Lot::first()->remaining_qty);
    }

    public function test_cancel_needs_a_reason_releases_stock_and_serves_waiting_preorders(): void
    {
        Lot::factory()->qty('3')->create(['product_id' => $this->product->id]);
        $order = $this->order('3');
        $waiting = $this->order('2', preorder: true)->items()->first();

        try {
            $this->workflow()->transition($order, OrderStatus::Cancelled, $this->user, '  ');
            $this->fail('Motif obligatoire');
        } catch (WorkflowException $e) {
            $this->assertSame(__('orders.errors.reason'), $e->getMessage());
        }

        $this->workflow()->transition($order, OrderStatus::Cancelled, $this->user, 'Client absent');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame('Client absent', $order->fresh()->cancel_reason);
        $this->assertNotNull($waiting->fresh()->preorder_served_at, 'Le stock libéré sert la précommande en attente');
        Mail::assertQueued(OrderCancelled::class, fn ($m) => $m->order->is($order));
    }

    public function test_handed_over_order_cannot_be_cancelled(): void
    {
        $order = $this->order(attrs: ['status' => OrderStatus::HandedOver]);

        $this->expectException(WorkflowException::class);
        $this->workflow()->transition($order, OrderStatus::Cancelled, $this->user, 'Erreur');
    }

    public function test_status_emails_render(): void
    {
        $order = $this->order(attrs: ['cancel_reason' => 'Produit abîmé']);

        $this->assertStringContainsString($order->number, (new OrderReady($order))->render());
        $this->assertStringContainsString('Produit abîmé', (new OrderCancelled($order))->render());
    }
}
