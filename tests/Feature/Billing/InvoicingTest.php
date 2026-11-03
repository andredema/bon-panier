<?php

namespace Tests\Feature\Billing;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\User;
use App\Services\Billing\BillingPdf;
use App\Services\Billing\CreditNoteIssuer;
use App\Services\Billing\DocumentNumbering;
use App\Services\Billing\InvoiceIssuer;
use App\Services\Billing\PaymentRecorder;
use App\Services\Orders\OrderWorkflow;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

/** Règle 5 et cahier 5.4 : factures, notes de crédit, paiements. */
class InvoicingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->user = User::factory()->create();
        Settings::set(['tax.tps_number' => '123456789 RT0001', 'tax.tvq_number' => '1234567890 TQ0001', 'interac.email' => 'paiement@example.com']);
    }

    /** Commande de la maquette de facture : 247,42 $ dont 27,48 $ taxables → 251,53 $. */
    private function order(array $attrs = []): Order
    {
        $customer = Customer::firstWhere('email', 'resto@example.com')
            ?? Customer::factory()->create(['name' => 'Resto Démo', 'email' => 'resto@example.com']);
        $order = Order::factory()->create($attrs + [
            'customer_id' => $customer->id, 'subtotal_cents' => 24742, 'taxable_cents' => 2748,
            'tax_tps_cents' => 137, 'tax_tvq_cents' => 274, 'total_cents' => 25153,
        ]);

        foreach ([['Water Leaf', '1', 17500, false], ['Gari jaune', '4', 2796, false], ['Huile de palme rouge', '2', 1698, false], ['Malta', '12', 2748, true]] as [$name, $qty, $total, $taxable]) {
            $p = Product::factory()->create(['name' => $name]);
            $f = SaleFormat::factory()->create(['product_id' => $p->id]);
            Lot::factory()->qty('50')->create(['product_id' => $p->id, 'lot_number' => 'L-'.$name]);
            $order->items()->create([
                'product_id' => $p->id, 'sale_format_id' => $f->id, 'product_name' => $name, 'format_label' => 'Format',
                'channel' => 'detail', 'qty' => $qty, 'stock_qty' => $qty, 'unit_price_cents' => intdiv($total, (int) $qty),
                'line_total_cents' => $total, 'is_taxable' => $taxable,
            ]);
        }

        return $order;
    }

    public function test_numbers_are_continuous_and_restart_each_year(): void
    {
        $n = app(DocumentNumbering::class);

        $numbers = DB::transaction(fn () => [$n->next('F', 2026), $n->next('F', 2026), $n->next('NC', 2026), $n->next('F', 2027)]);

        $this->assertSame(['F-2026-0001', 'F-2026-0002', 'NC-2026-0001', 'F-2027-0001'], $numbers);
    }

    public function test_a_failed_transaction_does_not_consume_a_number(): void
    {
        $n = app(DocumentNumbering::class);
        try {
            DB::transaction(function () use ($n) {
                $n->next('F', 2026);
                throw new \RuntimeException('échec');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('F-2026-0001', DB::transaction(fn () => $n->next('F', 2026)));
    }

    public function test_handover_issues_the_invoice_with_frozen_data_and_lots(): void
    {
        $order = $this->order(['status' => OrderStatus::Ready]);

        app(OrderWorkflow::class)->advance($order, $this->user);

        $invoice = Invoice::with('lines')->firstOrFail();
        $this->assertMatchesRegularExpression('/^F-\d{4}-0001$/', $invoice->number);
        $this->assertSame(25153, $invoice->total_cents);
        $this->assertSame(137, $invoice->tax_tps_cents);
        $this->assertSame('123456789 RT0001', $invoice->shop_snapshot['tps_number']);
        $this->assertSame('Resto Démo', $invoice->customer_snapshot['name']);
        $this->assertSame('L-Water Leaf', $invoice->lines->first()->lot_numbers);
        $this->assertSame(today()->addDays(14)->toDateString(), $invoice->due_on->toDateString());
        Mail::assertQueued(InvoiceMail::class, fn ($m) => $m->invoice->is($invoice) && $m->hasTo('resto@example.com'));

        // Les réglages changent ensuite : la facture émise ne bouge pas
        Settings::set(['tax.tps_number' => 'AUTRE']);
        $this->assertSame('123456789 RT0001', $invoice->fresh()->shop_snapshot['tps_number']);
    }

    public function test_an_issued_invoice_can_neither_change_nor_disappear(): void
    {
        $invoice = app(InvoiceIssuer::class)->issue($this->order(), $this->user, false);

        try {
            $invoice->update(['total_cents' => 1]);
            $this->fail('Modification refusée attendue');
        } catch (LogicException) {
        }
        $this->expectException(LogicException::class);
        $invoice->delete();
    }

    public function test_one_invoice_per_order(): void
    {
        $order = $this->order();
        $a = app(InvoiceIssuer::class)->issue($order, null, false);
        $b = app(InvoiceIssuer::class)->issue($order, null, false);

        $this->assertTrue($a->is($b));
        $this->assertSame(1, Invoice::count());
    }

    public function test_wholesale_invoice_at_confirmation_when_set(): void
    {
        Settings::set(['invoice.wholesale_issued_at' => 'confirmation']);
        $retail = $this->order();
        $wholesale = $this->order(['channel' => Channel::Wholesale]);

        app(OrderWorkflow::class)->advance($retail, $this->user);
        app(OrderWorkflow::class)->advance($wholesale, $this->user);

        $this->assertNull($retail->invoice()->first());
        $this->assertNotNull($wholesale->invoice()->first());
    }

    public function test_payments_before_and_after_invoice_drive_the_status(): void
    {
        $order = $this->order(['payment_method' => Order::PAY_INTERAC]);
        $recorder = app(PaymentRecorder::class);

        // Virement Interac reçu avant la remise
        $early = $recorder->record($order, Payment::INTERAC, 10000, null, 'CAxyz', $this->user);
        $invoice = app(InvoiceIssuer::class)->issue($order, null, false);

        $this->assertSame($invoice->id, $early->fresh()->invoice_id);
        $this->assertSame(Invoice::PARTIAL, $invoice->status());
        $this->assertSame(15153, $invoice->balanceCents());

        try {
            $recorder->record($order, Payment::CASH, 20000, null, null, $this->user);
            $this->fail('Trop-perçu refusé attendu');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $recorder->record($order, Payment::CASH, 15153, null, null, $this->user);
        $this->assertSame(Invoice::PAID, $invoice->status());
    }

    public function test_full_credit_note_returns_exactly_the_invoiced_taxes(): void
    {
        $invoice = app(InvoiceIssuer::class)->issue($this->order(), null, false)->load('lines');
        $all = $invoice->lines->mapWithKeys(fn ($l) => [$l->id => $l->line_total_cents])->all();

        $note = app(CreditNoteIssuer::class)->issue($invoice, $all, false, 'Erreur de facturation', $this->user);

        $this->assertMatchesRegularExpression('/^NC-\d{4}-0001$/', $note->number);
        $this->assertSame(24742, $note->subtotal_cents);
        $this->assertSame([137, 274, 25153], [$note->tax_tps_cents, $note->tax_tvq_cents, $note->total_cents]);
        $this->assertSame(0, $invoice->fresh()->balanceCents());
        $this->assertSame(Invoice::PAID, $invoice->fresh()->status());
    }

    public function test_partial_credit_note_recomputes_taxes_on_taxable_part_only(): void
    {
        $invoice = app(InvoiceIssuer::class)->issue($this->order(), null, false)->load('lines');
        $malta = $invoice->lines->firstWhere('product_name', 'Malta');
        $gari = $invoice->lines->firstWhere('product_name', 'Gari jaune');

        // 2 Malta (4,58 $ taxables) + 1 sac de gari (6,99 $ détaxé)
        $note = app(CreditNoteIssuer::class)->issue($invoice, [$malta->id => 458, $gari->id => 699], false, 'Bouteilles cassées', $this->user);

        $this->assertSame(1157, $note->subtotal_cents);
        $this->assertSame(458, $note->taxable_cents);
        $this->assertSame(23, $note->tax_tps_cents);  // 0,229 → 0,23
        $this->assertSame(46, $note->tax_tvq_cents);  // 0,4569 → 0,46
        $this->assertSame(2290, $malta->fresh()->creditableCents());

        // On ne crédite jamais plus que la ligne
        $this->expectException(ValidationException::class);
        app(CreditNoteIssuer::class)->issue($invoice, [$malta->id => 2291], false, 'Encore', $this->user);
    }

    public function test_credit_note_needs_a_reason_and_an_amount(): void
    {
        $invoice = app(InvoiceIssuer::class)->issue($this->order(), null, false);

        try {
            app(CreditNoteIssuer::class)->issue($invoice, [], false, '', $this->user);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
    }

    public function test_cancelling_an_invoiced_wholesale_order_issues_a_full_credit_note(): void
    {
        Settings::set(['invoice.wholesale_issued_at' => 'confirmation']);
        $order = $this->order(['channel' => Channel::Wholesale]);
        app(OrderWorkflow::class)->advance($order, $this->user);

        app(OrderWorkflow::class)->transition($order->fresh(), OrderStatus::Cancelled, $this->user, 'Client a annulé');

        $invoice = $order->invoice()->first();
        $this->assertSame(1, $invoice->creditNotes()->count());
        $this->assertSame(0, $invoice->balanceCents());
    }

    public function test_pdfs_render_with_accents_and_numbers(): void
    {
        $order = $this->order();
        $invoice = app(InvoiceIssuer::class)->issue($order, null, false);
        $payment = app(PaymentRecorder::class)->record($order, Payment::CASH, 5000, Carbon::now(), null, $this->user);
        $note = app(CreditNoteIssuer::class)->issue($invoice->load('lines'), [$invoice->lines->first()->id => 1000], false, 'Geste commercial', $this->user);

        foreach ([app(BillingPdf::class)->invoice($invoice), app(BillingPdf::class)->creditNote($note), app(BillingPdf::class)->receipt($payment)] as $pdf) {
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertLessThan(200_000, strlen($pdf), 'Police en sous-ensemble : PDF léger');
        }

        $html = view('pdf.invoice', ['invoice' => $invoice->fresh(), 'logo' => null])->render();
        $this->assertStringContainsString('FACTURE', $html);
        $this->assertStringContainsString('123456789 RT0001', $html);
        $this->assertStringContainsString('251,53', $html);
        $this->assertStringContainsString('Épicerie africaine Le Bon Panier', $html);
    }
}
