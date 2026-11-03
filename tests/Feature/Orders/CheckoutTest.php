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
use App\Services\Orders\PickupSlots;
use App\Support\Settings;
use Database\Seeders\CatalogDemoSeeder;
use Database\Seeders\StockDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private SaleFormat $malta;

    private SaleFormat $couscous;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // Tous les jours, sans délai : un créneau existe toujours dans les tests
        Settings::set(['pickup.days' => '1,2,3,4,5,6,7', 'pickup.lead_hours' => 0, 'pickup.times' => '00:00-23:59', 'interac.email' => 'paiement@example.com']);

        $p = Product::factory()->stockIn('unite')->create(['name' => 'Malta', 'is_taxable' => true]);
        Lot::factory()->qty('10')->create(['product_id' => $p->id]);
        $this->malta = SaleFormat::factory()->create(['product_id' => $p->id, 'label' => 'Bouteille de 330 ml', 'price_cents' => 229]);

        $c = Product::factory()->stockIn('kg')->create(['name' => 'Couscous de manioc']);
        ExpectedArrival::factory()->create(['product_id' => $c->id, 'expected_on' => today()->addDays(2)]);
        $this->couscous = SaleFormat::factory()->create(['product_id' => $c->id, 'label' => 'Sac de 1 kg', 'price_cents' => 599]);
    }

    private function cart(): array
    {
        return $this->getJson('/panier')->json();
    }

    private function form(array $overrides = []): array
    {
        $cart = $this->cart();

        return array_replace([
            'name' => 'Client Démo',
            'phone' => '450 555-0199',
            'email' => 'Client.Demo@example.com',
            'fulfilment' => 'ramassage',
            'slot' => $cart['slots'][0]['value'] ?? '',
            'payment_method' => 'interac',
            'consent' => '1',
            'cart_signature' => $cart['cart']['signature'],
        ], $overrides);
    }

    public function test_customer_places_an_order_with_a_preorder(): void
    {
        $this->postJson('/panier/lignes', ['format_id' => $this->malta->id, 'qty' => '6']);
        $this->postJson('/panier/lignes', ['format_id' => $this->couscous->id, 'qty' => '1']);

        $response = $this->post('/commande', $this->form());

        $order = Order::with('items', 'history')->firstOrFail();
        $response->assertRedirect("/commande/{$order->number}/merci");

        $this->assertMatchesRegularExpression('/^C-\d{4}-\d{4}$/', $order->number);
        $this->assertSame(OrderStatus::Received, $order->status);
        $this->assertSame(1374 + 599, $order->subtotal_cents);
        $this->assertSame(1374, $order->taxable_cents);
        $this->assertSame(69, $order->tax_tps_cents);
        $this->assertSame(137, $order->tax_tvq_cents);
        $this->assertSame(1973 + 69 + 137, $order->total_cents);
        $this->assertTrue($order->has_preorder);
        $this->assertSame([false, true], $order->items->pluck('is_preorder')->all());
        $this->assertSame(today()->addDays(2)->toDateString(), $order->items[1]->expected_on->toDateString());
        $this->assertTrue($order->slot_start->gt(today()->addDays(2)), 'Créneau après l’arrivage');
        $this->assertSame([null, OrderStatus::Received], [$order->history[0]->from, $order->history[0]->to]);

        $customer = Customer::firstOrFail();
        $this->assertSame('client.demo@example.com', $customer->email);
        $this->assertNotNull($customer->consent_at);

        Mail::assertQueued(OrderConfirmation::class, fn ($m) => $m->hasTo('client.demo@example.com') && $m->order->is($order));
        $this->getJson('/panier')->assertJsonPath('cart.count', 0);

        $this->get("/commande/{$order->number}/merci")->assertOk()
            ->assertSee($order->number)->assertSee('paiement@example.com')
            ->assertSee(app(PickupSlots::class)->label($order->slot_start, $order->slot_end));
    }

    public function test_confirmation_email_renders_with_interac_instructions_and_tracking_link(): void
    {
        $this->postJson('/panier/lignes', ['format_id' => $this->malta->id, 'qty' => '2']);
        $this->post('/commande', $this->form());
        $order = Order::firstOrFail();

        $html = (new OrderConfirmation($order))->render();

        $this->assertStringContainsString('paiement@example.com', $html);
        $this->assertStringContainsString($order->number, $html);
        $this->assertStringContainsString('/suivi/'.$order->number.'?signature=', $html);
    }

    public function test_tracking_page_needs_the_signed_link(): void
    {
        $order = Order::factory()->create();
        $order->history()->create(['to' => OrderStatus::Received]);

        $this->get('/suivi/'.$order->number)->assertForbidden();
        $this->get(URL::signedRoute('shop.track', ['order' => $order->number]))->assertOk()->assertSee('Reçue');
    }

    public function test_confirmation_page_is_private_to_the_session(): void
    {
        $order = Order::factory()->create();

        $this->get("/commande/{$order->number}/merci")->assertNotFound();
    }

    public function test_consent_and_contact_fields_are_required_in_french(): void
    {
        $this->postJson('/panier/lignes', ['format_id' => $this->malta->id, 'qty' => '1']);

        $this->post('/commande', $this->form(['consent' => null, 'email' => 'pas-un-courriel', 'phone' => '12']))
            ->assertRedirect('/')
            ->assertSessionHasErrors(['consent', 'email', 'phone'])
            ->assertSessionHas('open_checkout', true);

        $this->assertSame('Vous devez accepter le champ consentement.', session('errors')->first('consent'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_changed_availability_is_reported_before_creating_anything(): void
    {
        $this->postJson('/panier/lignes', ['format_id' => $this->malta->id, 'qty' => '6']);
        $form = $this->form();

        // Un autre client réserve 6 bouteilles entre l'affichage et l'envoi : la ligne passe en précommande
        ExpectedArrival::factory()->create(['product_id' => $this->malta->product_id]);
        $other = Order::factory()->create();
        $other->items()->create([
            'product_id' => $this->malta->product_id, 'sale_format_id' => $this->malta->id, 'product_name' => 'Malta',
            'format_label' => 'x', 'channel' => 'detail', 'qty' => '6', 'stock_qty' => '6', 'unit_price_cents' => 229, 'line_total_cents' => 1374,
        ]);

        $this->post('/commande', $form)->assertSessionHasErrors(['cart' => __('shop.checkout.changed')]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_slot_must_be_one_of_the_offered_slots(): void
    {
        $this->postJson('/panier/lignes', ['format_id' => $this->malta->id, 'qty' => '1']);

        $this->post('/commande', $this->form(['slot' => '2020-01-01T10:00/12:00']))->assertSessionHasErrors('slot');
    }

    public function test_delivery_only_when_enabled_and_never_for_frozen_by_default(): void
    {
        $this->postJson('/panier/lignes', ['format_id' => $this->malta->id, 'qty' => '1']);
        $this->post('/commande', $this->form(['fulfilment' => 'livraison', 'delivery_address' => '1 rue Test']))->assertSessionHasErrors('fulfilment');

        Settings::set(['delivery.enabled' => true, 'delivery.fee' => 800]);
        $this->post('/commande', $this->form(['fulfilment' => 'livraison', 'delivery_address' => '']))->assertSessionHasErrors('delivery_address');
        $this->post('/commande', $this->form(['fulfilment' => 'livraison', 'delivery_address' => '1 rue Test']))->assertRedirect();
        $this->assertSame(800, Order::first()->delivery_fee_cents);

        $frozen = Product::factory()->stockIn('unite')->create(['is_frozen' => true]);
        Lot::factory()->qty('5')->create(['product_id' => $frozen->id]);
        $f = SaleFormat::factory()->create(['product_id' => $frozen->id]);
        $this->postJson('/panier/lignes', ['format_id' => $f->id, 'qty' => '1']);
        $this->post('/commande', $this->form(['fulfilment' => 'livraison', 'delivery_address' => '1 rue Test']))
            ->assertSessionHasErrors(['fulfilment' => __('shop.checkout.frozen_pickup_only')]);
    }

    public function test_order_submissions_are_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/commande', ['name' => '']);
        }

        $this->post('/commande', ['name' => ''])->assertStatus(429);
    }

    public function test_order_page_shows_the_demo_catalog(): void
    {
        $this->seed([CatalogDemoSeeder::class, StockDemoSeeder::class]);

        $this->get('/')->assertOk()
            ->assertSee('Nos catégories')
            ->assertSee('Water Leaf')
            ->assertSee('viewport-fit=cover', false)
            ->assertDontSee('fonts.googleapis.com', false);
    }
}
