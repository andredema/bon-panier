<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\InvoiceIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cahier des charges 4.6 (Clients) et 9 (Loi 25 : accès, export, effacement). */
class CustomerPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->manager()->create();
    }

    private function customer(): Customer
    {
        return Customer::factory()->create(['name' => 'Awa Démo', 'phone' => '450-555-0101', 'email' => 'awa@example.com', 'address' => '12, rue Démo']);
    }

    /** Commande remise, facturée pour 1 000 cents. */
    private function invoicedOrder(Customer $c): Invoice
    {
        $order = Order::factory()->create(['customer_id' => $c->id, 'status' => OrderStatus::HandedOver, 'subtotal_cents' => 1000,
            'total_cents' => 1000, 'fulfilment' => Order::DELIVERY, 'delivery_address' => '12, rue Démo', 'notes' => 'Sonner deux fois']);

        return app(InvoiceIssuer::class)->issue($order, null, false);
    }

    public function test_team_sees_the_list_and_the_record_with_balance(): void
    {
        $c = $this->customer();
        $this->invoicedOrder($c);
        Customer::factory()->create(['name' => 'Bintou Démo']);

        $this->actingAs(User::factory()->create())->get(route('admin.customers.index', ['q' => 'Awa']))
            ->assertOk()->assertSee('Awa Démo')->assertDontSee('Bintou Démo')->assertSee('10,00');

        $this->get(route('admin.customers.show', $c))->assertOk()
            ->assertSee('450-555-0101')->assertSee('Consentement (Loi 25)')
            ->assertDontSee('Exporter ses renseignements')->assertDontSee('Effacer les renseignements');
    }

    public function test_employee_edits_details_but_not_wholesale_approval_nor_privacy_actions(): void
    {
        $c = $this->customer();
        $this->actingAs(User::factory()->create())->put(route('admin.customers.update', $c), [
            'name' => 'Awa D.', 'phone' => '450-555-0102', 'email' => 'AWA@example.com', 'type' => 'gros', 'approved_wholesale' => '1',
        ])->assertSessionHasNoErrors();

        $c->refresh();
        $this->assertSame(['Awa D.', 'awa@example.com', 'gros', false], [$c->name, $c->email, $c->type, $c->approved_wholesale]);

        $this->get(route('admin.customers.export', $c))->assertForbidden();
        $this->post(route('admin.customers.erase', $c), ['confirm' => '1'])->assertForbidden();
    }

    public function test_manager_exports_everything_held_on_a_customer(): void
    {
        $c = $this->customer();
        $invoice = $this->invoicedOrder($c);

        $res = $this->actingAs($this->manager)->get(route('admin.customers.export', $c))->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));

        $data = $res->json();
        $this->assertSame('awa@example.com', $data['client']['courriel']);
        $this->assertSame('Sonner deux fois', $data['commandes'][0]['notes']);
        $this->assertSame($invoice->number, $data['factures'][0]['numero']);
    }

    public function test_erasure_removes_personal_data_but_keeps_invoices_intact(): void
    {
        $c = $this->customer();
        $invoice = $this->invoicedOrder($c);
        Payment::create(['order_id' => $invoice->order_id, 'invoice_id' => $invoice->id, 'method' => 'comptant', 'amount_cents' => 1000, 'paid_at' => now()]);

        $this->actingAs($this->manager)->post(route('admin.customers.erase', $c), ['confirm' => '1'])
            ->assertRedirect(route('admin.customers.index'));

        $c->refresh();
        $this->assertTrue($c->isErased());
        $this->assertNull($c->phone);
        $this->assertNull($c->email);
        $this->assertNull($c->address);
        $this->assertStringNotContainsString('Awa', $c->name);

        $order = $invoice->order->fresh();
        $this->assertNull($order->delivery_address);
        $this->assertNull($order->notes);

        // Facture conservée telle quelle (obligation fiscale)
        $this->assertSame('Awa Démo', $invoice->fresh()->customer_snapshot['name']);

        // Journal sans donnée personnelle ; plus de recherche possible
        $log = AuditLog::where('action', 'erased')->sole();
        $this->assertNull($log->changes);
        $this->get(route('admin.customers.index', ['q' => 'Awa']))->assertDontSee('Awa Démo');

        // Une nouvelle commande avec le même courriel crée un nouveau client
        $this->assertNull(Customer::where('email', 'awa@example.com')->first());
    }

    public function test_erasure_is_refused_with_an_active_order_or_a_balance_or_without_confirmation(): void
    {
        $c = $this->customer();
        $this->actingAs($this->manager);

        $this->post(route('admin.customers.erase', $c))->assertSessionHasErrors('confirm');

        $active = Order::factory()->create(['customer_id' => $c->id, 'status' => OrderStatus::Confirmed]);
        $this->post(route('admin.customers.erase', $c), ['confirm' => '1'])->assertSessionHasErrors('erase');

        $active->update(['status' => OrderStatus::Cancelled]);
        $this->invoicedOrder($c); // 10,00 $ dus
        $this->post(route('admin.customers.erase', $c), ['confirm' => '1'])->assertSessionHasErrors('erase');

        $this->assertFalse($c->fresh()->isErased());
    }
}
