<?php

namespace Tests\Feature\Billing;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\User;
use App\Services\Billing\InvoiceIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BillingScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->employee = User::factory()->create();
    }

    private function invoiced(array $attrs = []): Invoice
    {
        $order = Order::factory()->create($attrs + ['subtotal_cents' => 2000, 'total_cents' => 2000]);
        $p = Product::factory()->create(['name' => 'Gari jaune']);
        $f = SaleFormat::factory()->create(['product_id' => $p->id]);
        $order->items()->create(['product_id' => $p->id, 'sale_format_id' => $f->id, 'product_name' => 'Gari jaune', 'format_label' => 'Sac de 1 kg',
            'channel' => 'detail', 'qty' => '2', 'stock_qty' => '2', 'unit_price_cents' => 1000, 'line_total_cents' => 2000]);

        return app(InvoiceIssuer::class)->issue($order, null, false);
    }

    public function test_invoice_list_detail_and_pdf(): void
    {
        $invoice = $this->invoiced();

        $this->actingAs($this->employee)->get('/gestion/factures')->assertOk()->assertSee($invoice->number)->assertSee('À payer');
        $this->actingAs($this->employee)->get('/gestion/factures?statut=payee')->assertDontSee($invoice->number);
        $this->actingAs($this->employee)->get("/gestion/factures/{$invoice->number}")->assertOk()->assertSee(__('billing.show.immutable'));
        $this->actingAs($this->employee)->get("/gestion/factures/{$invoice->number}/pdf")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_payment_from_the_invoice_page_in_french_amounts(): void
    {
        $invoice = $this->invoiced();

        $this->actingAs($this->employee)->post("/gestion/commandes/{$invoice->order->number}/paiements", [
            'method' => 'comptant', 'amount' => '20,00', 'paid_at' => today()->toDateString(),
        ])->assertSessionHas('status');

        $this->assertSame(Invoice::PAID, $invoice->status());
        $this->actingAs($this->employee)->post("/gestion/commandes/{$invoice->order->number}/paiements", ['method' => 'comptant', 'amount' => 'abc'])
            ->assertSessionHasErrors('amount');
    }

    public function test_credit_note_from_the_screen(): void
    {
        $invoice = $this->invoiced();
        $line = $invoice->lines()->first();

        $this->actingAs($this->employee)->get("/gestion/factures/{$invoice->number}/note-de-credit")->assertOk();
        $this->actingAs($this->employee)->post("/gestion/factures/{$invoice->number}/note-de-credit", [
            'amounts' => [$line->id => '10,00'], 'reason' => 'Sac déchiré',
        ])->assertRedirect("/gestion/factures/{$invoice->number}");

        $note = $invoice->creditNotes()->firstOrFail();
        $this->assertSame(1000, $note->total_cents);
        $this->actingAs($this->employee)->get("/gestion/notes-de-credit/{$note->number}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_interac_reconciliation_lists_unpaid_transfers(): void
    {
        $order = Order::factory()->create(['payment_method' => Order::PAY_INTERAC, 'total_cents' => 4500]);
        Order::factory()->create(['payment_method' => Order::PAY_INTERAC, 'total_cents' => 900, 'status' => OrderStatus::Cancelled]);

        $this->actingAs($this->employee)->get('/gestion/paiements/interac')->assertOk()->assertSee($order->number)->assertSee('45,00');

        $this->actingAs($this->employee)->post("/gestion/commandes/{$order->number}/paiements", ['method' => 'interac', 'amount' => '45', 'reference' => 'CA123'])
            ->assertSessionHas('status');
        $this->actingAs($this->employee)->get('/gestion/paiements/interac')->assertSee(__('billing.reconcile.empty'));
        $this->actingAs($this->employee)->get('/gestion')->assertSee('0,00');
    }

    public function test_only_the_manager_deletes_a_payment_and_it_is_audited(): void
    {
        $order = Order::factory()->create(['total_cents' => 1000]);
        $payment = Payment::create(['order_id' => $order->id, 'method' => 'comptant', 'amount_cents' => 1000, 'paid_at' => now()]);

        $this->actingAs($this->employee)->delete("/gestion/paiements/{$payment->id}")->assertForbidden();
        $this->actingAs(User::factory()->manager()->create())->delete("/gestion/paiements/{$payment->id}")->assertRedirect();

        $this->assertModelMissing($payment);
        $log = AuditLog::where('subject_type', (new Payment)->getMorphClass())->where('action', 'deleted')->firstOrFail();
        $this->assertSame(1000, $log->changes['before']['amount_cents']);
    }

    public function test_receipt_pdf(): void
    {
        $order = Order::factory()->create(['total_cents' => 1000]);
        $payment = Payment::create(['order_id' => $order->id, 'method' => 'debit', 'amount_cents' => 600, 'paid_at' => now()]);

        $this->actingAs($this->employee)->get("/gestion/paiements/{$payment->id}/recu")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_counter_sale_collected_now_is_invoiced_and_paid(): void
    {
        $p = Product::factory()->stockIn('unite')->create(['name' => 'Malta', 'is_taxable' => true]);
        Lot::factory()->qty('10')->create(['product_id' => $p->id]);
        $f = SaleFormat::factory()->create(['product_id' => $p->id, 'price_cents' => 229]);

        $this->actingAs($this->employee)->post('/gestion/commandes', [
            'name' => 'Client Comptoir', 'phone' => '450 555-0123', 'consent' => '1', 'fulfilment' => 'ramassage',
            'slot' => now()->format('Y-m-d\TH:i'), 'payment_method' => 'remise', 'hand_over' => '1', 'collected' => 'comptant',
            'lines' => [['format_id' => $f->id, 'qty' => '2']],
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame(Invoice::PAID, $invoice->status());
        $this->assertSame('comptant', $invoice->payments()->first()->method);
        Mail::assertNothingQueued();
    }
}
