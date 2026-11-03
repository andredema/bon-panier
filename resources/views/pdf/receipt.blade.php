@php
    use App\Support\Format;
    use App\Support\Settings;
    $invoice = $order->invoice;
    $shop = $invoice?->shop_snapshot ?? ['name' => Settings::get('shop.name'), 'address' => Settings::get('shop.address'), 'phone' => Settings::get('shop.phone'), 'email' => Settings::get('shop.email')];
    // Solde juste après ce paiement : total − crédits − paiements jusqu'à celui-ci inclus
    $paidUntil = $order->payments()->where(fn ($q) => $q->where('paid_at', '<', $payment->paid_at)->orWhere(fn ($w) => $w->where('paid_at', $payment->paid_at)->where('id', '<=', $payment->id)))->sum('amount_cents');
    $total = $invoice ? $invoice->total_cents - $invoice->creditedCents() : $order->total_cents;
@endphp
<!doctype html>
<html lang="fr-CA">
<head><meta charset="utf-8"><title>{{ __('billing.pdf.receipt') }}</title>@include('pdf._style')</head>
<body>
  @include('pdf._header', [
      'docTitle' => __('billing.pdf.receipt'),
      'docFields' => [__('billing.pdf.date') => Format::date($payment->paid_at), __('billing.pdf.order') => $order->number],
  ])

  <table class="parties">
    <tr>
      <td><h3>{{ __('billing.pdf.billed_to') }}</h3><p><b>{{ $order->customer->name }}</b><br>{{ $order->customer->phone }}</p></td>
      <td><h3>{{ __('billing.pdf.order') }}</h3><p>{{ __('billing.pdf.receipt_doc', ['order' => $order->number, 'invoice' => $invoice?->number ?? '—']) }}</p></td>
    </tr>
  </table>

  <table class="totaux" style="width:60%">
    <tr><td>{{ __('billing.pdf.receipt_line', ['date' => Format::date($payment->paid_at), 'method' => mb_strtolower(__('billing.methods.'.$payment->method))]) }}</td><td class="d"><b>{{ Format::money($payment->amount_cents) }}</b></td></tr>
    @if ($payment->reference)<tr><td colspan="2">{{ __('billing.pdf.reference', ['ref' => $payment->reference]) }}</td></tr>@endif
    <tr class="grand"><td>{{ __('billing.pdf.receipt_balance') }}</td><td class="d">{{ Format::money(max(0, $total - (int) $paidUntil)) }}</td></tr>
  </table>

  <table class="pied"><tr><td>{{ __('billing.pdf.thanks') }}</td></tr></table>
</body>
</html>
