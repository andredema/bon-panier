<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\Billing\BillingPdf;
use App\Services\Billing\CreditNoteIssuer;
use App\Services\Billing\InvoiceIssuer;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/** Factures et notes de crédit (cahier 5.4 et 6). */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $status = $request->query('statut');
        $from = $request->date('du')?->toDateString();
        $to = $request->date('au')?->toDateString();

        $invoices = Invoice::query()
            ->with(['order'])
            ->withSum('creditNotes', 'total_cents')
            ->withSum('payments', 'amount_cents')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('number', 'like', "%{$q}%")
                ->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%{$q}%"))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
            ->when($from, fn ($w) => $w->whereDate('issued_at', '>=', $from))
            ->when($to, fn ($w) => $w->whereDate('issued_at', '<=', $to))
            ->orderByDesc('issued_at')->orderByDesc('id')
            ->get()
            ->map(function (Invoice $i) {
                $i->balance = $i->total_cents - (int) $i->credit_notes_sum_total_cents - (int) $i->payments_sum_amount_cents;
                $i->computed_status = $i->balance <= 0 ? Invoice::PAID : ((int) $i->payments_sum_amount_cents > 0 ? Invoice::PARTIAL : Invoice::DUE);

                return $i;
            })
            ->when(in_array($status, [Invoice::DUE, Invoice::PARTIAL, Invoice::PAID], true), fn ($c) => $c->where('computed_status', $status));

        return view('admin.billing.index', [
            'invoices' => $invoices,
            'q' => $q, 'status' => $status, 'from' => $from, 'to' => $to,
            'totalDue' => $invoices->sum(fn ($i) => max(0, $i->balance)),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['order.customer', 'lines.creditLines', 'creditNotes.user', 'payments.user']);

        return view('admin.billing.show', ['invoice' => $invoice]);
    }

    public function pdf(Invoice $invoice, BillingPdf $pdf): Response
    {
        return $this->pdfResponse($pdf->invoice($invoice), $invoice->number);
    }

    public function email(Invoice $invoice, InvoiceIssuer $issuer): RedirectResponse
    {
        $issuer->email($invoice);

        return back()->with('status', __('billing.show.sent', ['number' => $invoice->number]));
    }

    public function createCredit(Invoice $invoice): View
    {
        $invoice->load(['lines.creditLines', 'creditNotes']);

        return view('admin.billing.credit', [
            'invoice' => $invoice,
            'deliveryLeft' => $invoice->delivery_fee_cents - (int) $invoice->creditNotes->sum('delivery_cents'),
        ]);
    }

    public function storeCredit(Request $request, Invoice $invoice, CreditNoteIssuer $issuer): RedirectResponse
    {
        $amounts = [];
        foreach ((array) $request->input('amounts', []) as $lineId => $value) {
            if (blank($value)) {
                continue;
            }
            try {
                $amounts[(int) $lineId] = Format::parseMoney((string) $value);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(["amounts.{$lineId}" => __('billing.errors.money')]);
            }
        }

        $note = $issuer->issue($invoice, $amounts, $request->boolean('delivery'), (string) $request->input('reason'), $request->user());

        return redirect()->route('admin.invoices.show', $invoice)->with('status', __('billing.credit.saved', ['number' => $note->number]));
    }

    public function creditPdf(CreditNote $creditNote, BillingPdf $pdf): Response
    {
        return $this->pdfResponse($pdf->creditNote($creditNote), $creditNote->number);
    }

    private function pdfResponse(string $content, string $name): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'.pdf"',
        ]);
    }
}
