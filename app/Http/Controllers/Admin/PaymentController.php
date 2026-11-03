<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Billing\BillingPdf;
use App\Services\Billing\InteracReconciliation;
use App\Services\Billing\PaymentRecorder;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/** Paiements et rapprochement Interac manuel (cahier 5.4). */
class PaymentController extends Controller
{
    /** Commandes payables par Interac dont le solde n'est pas réglé. */
    public function reconcile(InteracReconciliation $interac): View
    {
        return view('admin.billing.reconcile', ['orders' => $interac->pending()]);
    }

    public function store(Request $request, Order $order, PaymentRecorder $recorder): RedirectResponse
    {
        try {
            $cents = Format::parseMoney((string) $request->input('amount'));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['amount' => __('billing.errors.money')]);
        }

        $paidAt = $request->filled('paid_at') ? Carbon::createFromFormat('!Y-m-d', (string) $request->input('paid_at')) : null;
        if ($paidAt?->isToday()) {
            $paidAt = now();
        }

        $payment = $recorder->record($order, (string) $request->input('method'), $cents, $paidAt ?: null,
            $request->input('reference'), $request->user(), $request->input('note'));

        return back()->with('status', __('billing.payment.saved', ['amount' => Format::money($payment->amount_cents)]));
    }

    /** Paiement saisi par erreur : réservé au gérant, inscrit au journal d'audit. */
    public function destroy(Payment $payment): RedirectResponse
    {
        $payment->delete();

        return back()->with('status', __('billing.payment.deleted'));
    }

    public function receipt(Payment $payment, BillingPdf $pdf): Response
    {
        return response($pdf->receipt($payment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="recu-'.$payment->order->number.'-'.$payment->id.'.pdf"',
        ]);
    }
}
