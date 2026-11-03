<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\ExpectedArrival;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\Unit;
use App\Services\Stock\StockLevels;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $unit, string $stock, array $attrs = []): Product
    {
        $p = Product::factory()->stockIn($unit)->create($attrs);
        if ($stock !== '0') {
            Lot::factory()->qty($stock)->create(['product_id' => $p->id]);
        }

        return $p;
    }

    private function add(SaleFormat $f, string $qty)
    {
        return $this->postJson('/panier/lignes', ['format_id' => $f->id, 'qty' => $qty]);
    }

    public function test_adding_returns_lines_and_french_totals(): void
    {
        $malta = $this->product('unite', '96', ['name' => 'Malta', 'is_taxable' => true]);
        $bottle = SaleFormat::factory()->create(['product_id' => $malta->id, 'price_cents' => 229]);

        $json = $this->add($bottle, '6')->assertOk()->json('cart');

        $this->assertSame(1, $json['count']);
        $this->assertSame(1374, $json['totals']['subtotal']);
        $this->assertSame(69, $json['totals']['tps']);   // 0,687 → 0,69
        $this->assertSame(137, $json['totals']['tvq']);  // 1,3706 → 1,37
        $this->assertSame("15,80\u{00A0}$", $json['labels']['total']);

        // Le panier survit au rechargement (session)
        $this->getJson('/panier')->assertJsonPath('cart.count', 1);
    }

    public function test_same_format_adds_up_and_zero_removes(): void
    {
        $p = $this->product('unite', '50');
        $f = SaleFormat::factory()->create(['product_id' => $p->id]);

        $this->add($f, '2');
        $this->add($f, '3')->assertJsonPath('cart.lines.0.qty', '5.000');
        $this->patchJson("/panier/lignes/{$f->id}", ['qty' => '0'])->assertJsonPath('cart.count', 0);
    }

    public function test_wholesale_minimum_and_step_are_enforced(): void
    {
        $gari = $this->product('kg', '100');
        $bag = SaleFormat::factory()->wholesale()->create(['product_id' => $gari->id, 'label' => 'Sac de 25 kg', 'unit_id' => Unit::where('code', 'sac')->value('id'), 'content_qty' => '25', 'min_qty' => '2']);
        $bulk = SaleFormat::factory()->create(['product_id' => $gari->id, 'unit_id' => Unit::where('code', 'kg')->value('id'), 'is_by_weight' => true, 'step' => '0.5', 'min_qty' => '0.5']);

        $this->add($bag, '1')->assertStatus(422)->assertJsonPath('error', __('shop.cart.errors.wholesale_min', ['min' => '2', 'label' => 'Sac de 25 kg']));
        $this->add($bag, '2')->assertOk();
        $this->add($bulk, '0.3')->assertStatus(422)->assertJsonPath('error', __('shop.cart.errors.step', ['step' => '0,5']));
        $this->add($bulk, '1.5')->assertOk()->assertJsonPath('cart.lines.1.qty_label', '1,5 kg');
    }

    public function test_wholesale_closed_when_reserved_to_approved_accounts(): void
    {
        Settings::set(['wholesale.prices_visible_to' => 'approved']);
        $f = SaleFormat::factory()->wholesale()->create(['product_id' => $this->product('unite', '10')->id]);

        $this->add($f, '1')->assertStatus(422)->assertJsonPath('error', __('shop.cart.errors.wholesale_closed'));
    }

    public function test_out_of_stock_without_arrival_is_refused(): void
    {
        $p = $this->product('unite', '3');
        $f = SaleFormat::factory()->create(['product_id' => $p->id]);

        $this->add($f, '4')->assertStatus(422)->assertJsonPath('cart.count', 0);
        $this->assertStringContainsString('il reste 3', $this->add($f, '5')->json('error'));
    }

    public function test_product_at_zero_with_arrival_becomes_a_whole_line_preorder(): void
    {
        $couscous = $this->product('kg', '0', ['name' => 'Couscous de manioc']);
        $bag = SaleFormat::factory()->create(['product_id' => $couscous->id, 'content_qty' => '1']);
        ExpectedArrival::factory()->create(['product_id' => $couscous->id, 'expected_on' => today()->addDays(4)]);

        $line = $this->add($bag, '2')->assertOk()->json('cart.lines.0');

        $this->assertTrue($line['preorder']);
        $this->assertSame(today()->addDays(4)->toDateString(), $line['expected_on']);
    }

    public function test_partial_stock_follows_the_preorder_scope_setting(): void
    {
        $p = $this->product('unite', '3');
        $f = SaleFormat::factory()->create(['product_id' => $p->id]);
        ExpectedArrival::factory()->create(['product_id' => $p->id]);

        // Par défaut : toute la ligne en précommande
        $line = $this->add($f, '5')->json('cart.lines.0');
        $this->assertTrue($line['preorder']);
        $this->assertCount(1, $line['parts']);

        // Seulement la partie manquante : 3 en stock + 2 en précommande
        Settings::set(['preorder.scope' => 'missing_part']);
        $line = $this->getJson('/panier')->json('cart.lines.0');
        $this->assertTrue($line['partial_preorder']);
        $this->assertSame([[3000, false], [2000, true]], array_map(fn ($p) => [$p['qty'], $p['preorder']], $line['parts']));
    }

    public function test_preorder_not_allowed_when_product_refuses_it(): void
    {
        $p = $this->product('unite', '0', ['allow_preorder' => false]);
        $f = SaleFormat::factory()->create(['product_id' => $p->id]);
        ExpectedArrival::factory()->create(['product_id' => $p->id]);

        $this->add($f, '1')->assertStatus(422);
    }

    public function test_active_orders_reserve_stock_but_unserved_preorders_do_not(): void
    {
        $p = $this->product('unite', '10');
        $order = Order::factory()->create();
        $order->items()->create($this->item($p, '4', false));
        $order->items()->create($this->item($p, '2', true));
        Order::factory()->create(['status' => OrderStatus::Cancelled])->items()->create($this->item($p, '5', false));

        $this->assertSame(4000, app(StockLevels::class)->reservedMilli($p));
        $this->assertSame(6000, app(StockLevels::class)->availableMilli($p));
    }

    private function item(Product $p, string $qty, bool $preorder): array
    {
        $f = SaleFormat::factory()->create(['product_id' => $p->id]);

        return [
            'product_id' => $p->id, 'sale_format_id' => $f->id, 'product_name' => $p->name, 'format_label' => $f->label,
            'channel' => 'detail', 'qty' => $qty, 'stock_qty' => $qty, 'unit_price_cents' => 100, 'line_total_cents' => 100,
            'is_preorder' => $preorder,
        ];
    }
}
