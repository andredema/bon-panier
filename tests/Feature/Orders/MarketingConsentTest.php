<?php

namespace Tests\Feature\Orders;

use App\Mail\InvoiceMail;
use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\User;
use App\Services\Orders\OrderWorkflow;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Coordonnées, infolettre (consentement exprès, désabonnement en un clic) et adresse de la facture. */
class MarketingConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Settings::set(['pickup.days' => '1,2,3,4,5,6,7', 'pickup.lead_hours' => 0, 'pickup.times' => '00:00-23:59',
            'delivery.enabled' => true, 'shop.name' => 'Le Bon Panier']);

        $p = Product::factory()->stockIn('unite')->create(['name' => 'Malta']);
        Lot::factory()->qty('10')->create(['product_id' => $p->id]);
        $format = SaleFormat::factory()->create(['product_id' => $p->id, 'price_cents' => 229]);
        $this->postJson('/panier/lignes', ['format_id' => $format->id, 'qty' => '2']);
    }

    private function form(array $overrides = []): array
    {
        $cart = $this->getJson('/panier')->json();

        return array_replace([
            'name' => 'Client Démo', 'phone' => '450 555-0199', 'email' => 'client.demo@example.com',
            'fulfilment' => 'ramassage', 'slot' => $cart['slots'][0]['value'], 'payment_method' => 'interac',
            'consent' => '1', 'cart_signature' => $cart['cart']['signature'],
        ], $overrides);
    }

    public function test_checkout_shows_the_unticked_newsletter_box_and_both_reception_modes(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('soldes et nouveautés du Bon Panier', $html);
        $this->assertMatchesRegularExpression('/<input type="checkbox" name="marketing" value="1"\s*>/', $html);
        $this->assertStringContainsString('Je viens ramasser', $html);
        $this->assertStringContainsString('Livraison à domicile', $html);
    }

    public function test_name_phone_and_email_are_required(): void
    {
        $this->post('/commande', $this->form(['name' => '', 'phone' => '', 'email' => '']))
            ->assertSessionHasErrors(['name', 'phone', 'email']);
        $this->assertSame(0, Order::count());
    }

    public function test_delivery_requires_an_address(): void
    {
        $this->post('/commande', $this->form(['fulfilment' => 'livraison']))->assertSessionHasErrors('delivery_address');

        $this->post('/commande', $this->form(['fulfilment' => 'livraison', 'delivery_address' => '12, rue Démo']))->assertRedirect();
        $this->assertSame('12, rue Démo', Order::sole()->delivery_address);
    }

    public function test_no_subscription_without_the_box(): void
    {
        $this->post('/commande', $this->form())->assertRedirect();

        $this->assertFalse(Customer::sole()->isSubscribed());
    }

    public function test_ticking_the_box_subscribes_and_mails_carry_a_one_click_unsubscribe_link(): void
    {
        $this->post('/commande', $this->form(['marketing' => '1']))->assertRedirect();
        $customer = Customer::sole();
        $this->assertTrue($customer->isSubscribed());

        Mail::assertQueued(OrderConfirmation::class, fn ($m) => str_contains($m->render(), $customer->unsubscribeUrl()));

        // Un lien modifié est refusé ; le lien signé désabonne d'un seul clic
        $this->get(route('shop.unsubscribe', $customer))->assertForbidden();
        $this->get($customer->unsubscribeUrl())->assertOk()->assertSee('Vous êtes désabonné');

        $customer->refresh();
        $this->assertFalse($customer->isSubscribed());
        $this->assertNotNull($customer->marketing_unsubscribed_at);
    }

    public function test_a_later_order_without_the_box_keeps_the_subscription(): void
    {
        Customer::factory()->create(['email' => 'client.demo@example.com', 'marketing_consent_at' => now()->subMonth()]);

        $this->post('/commande', $this->form())->assertRedirect();

        $this->assertTrue(Customer::sole()->isSubscribed());
    }

    public function test_team_sees_subscribers_and_manager_exports_them(): void
    {
        $sub = Customer::factory()->create(['name' => 'Awa Abonnée', 'email' => 'awa@example.com', 'marketing_consent_at' => now()]);
        Customer::factory()->create(['name' => 'Bintou Non', 'email' => 'bintou@example.com']);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.customers.index', ['abonnes' => 1]))->assertOk()->assertSee('Awa Abonnée')->assertDontSee('Bintou Non');
        $this->get(route('admin.customers.subscribers'))->assertForbidden();

        $csv = $this->actingAs(User::factory()->manager()->create())->get(route('admin.customers.subscribers'))->assertOk()->streamedContent();
        $this->assertStringContainsString('awa@example.com', $csv);
        $this->assertStringNotContainsString('bintou@example.com', $csv);

        $this->post(route('admin.customers.unsubscribe', $sub))->assertRedirect();
        $this->assertFalse($sub->fresh()->isSubscribed());
    }

    public function test_invoice_goes_to_the_email_given_at_checkout(): void
    {
        $this->post('/commande', $this->form(['email' => 'Facture.Client@example.com']))->assertRedirect();
        $order = Order::sole();

        app(OrderWorkflow::class)->handOverNow($order, User::factory()->create());

        Mail::assertQueued(InvoiceMail::class, fn ($m) => $m->hasTo('facture.client@example.com'));
        $this->assertSame('facture.client@example.com', $order->fresh()->invoice->customer_snapshot['email']);
    }
}
