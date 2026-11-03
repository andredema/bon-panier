@php
    use App\Support\Format;
    use App\Support\Quantity;
    $shop = $invoice->shop_snapshot;
    $c = $invoice->customer_snapshot;
    $rate = fn (int $r) => Format::quantity(Quantity::fromMilli($r));
    $paid = $invoice->paidCents();
    $credited = $invoice->creditedCents();
    $balance = $invoice->balanceCents();
    $status = $invoice->status();
    $stamp = ['a_payer' => 'stamp_due', 'partielle' => 'stamp_partial', 'payee' => 'stamp_paid'][$status];
    $mode = $invoice->fulfilment === \App\Models\Order::DELIVERY ? __('billing.pdf.delivery') : __('billing.pdf.pickup');
    $zeroRated = $invoice->subtotal_cents - $invoice->taxable_cents;
@endphp
<!doctype html>
<html lang="fr-CA">
<head><meta charset="utf-8"><title>{{ $invoice->number }}</title>@include('pdf._style')</head>
<body>
  @include('pdf._header', [
      'docTitle' => __('billing.pdf.invoice'),
      'docFields' => array_filter([
          __('billing.pdf.number') => $invoice->number,
          __('billing.pdf.date') => Format::date($invoice->issued_at),
          __('billing.pdf.order') => $invoice->order->number,
          __('billing.pdf.due') => $invoice->due_on ? Format::date($invoice->due_on) : null,
      ]),
  ])

  <table class="parties">
    <tr>
      <td>
        <h3>{{ __('billing.pdf.billed_to') }}</h3>
        <p><b>{{ $c['name'] }}</b>@if ($c['address'])<br>{{ $c['address'] }}@endif<br>{{ $c['phone'] }}@if ($c['email'])<br>{{ $c['email'] }}@endif</p>
      </td>
      <td>
        <h3>{{ $mode }}</h3>
        <p>
          @if ($invoice->slot_start){{ __('billing.pdf.reception', ['mode' => $mode, 'date' => Format::date($invoice->slot_start).', '.Format::time($invoice->slot_start).' – '.Format::time($invoice->slot_end)]) }}@endif
          @if ($invoice->lines->contains('is_frozen', true))<br>{{ __('billing.pdf.frozen_note') }}@endif
        </p>
      </td>
    </tr>
  </table>

  <table class="lignes">
    <thead><tr>
      <th>{{ __('billing.pdf.product') }}</th><th>{{ __('billing.pdf.lot') }}</th><th class="d">{{ __('billing.pdf.qty') }}</th>
      <th class="d">{{ __('billing.pdf.unit_price') }}</th><th>{{ __('billing.pdf.tax') }}</th><th class="d">{{ __('billing.pdf.amount') }}</th>
    </tr></thead>
    <tbody>
      @foreach ($invoice->lines as $l)
        <tr>
          <td><b>{{ $l->product_name }}</b><small>{{ $l->channel === 'gros' ? __('billing.pdf.wholesale') : __('billing.pdf.retail') }} · {{ $l->format_label }}@if ($l->is_frozen) · {{ __('billing.pdf.frozen') }}@endif</small></td>
          <td>{{ $l->lot_numbers ?? '—' }}</td>
          <td class="d">{{ Format::quantity($l->qty) }}{{ $l->qty_unit ? ' '.$l->qty_unit : '' }}</td>
          <td class="d">{{ Format::money($l->unit_price_cents) }}</td>
          <td><span class="code {{ $l->is_taxable ? 'code-t' : 'code-d' }}">{{ $l->is_taxable ? 'T' : 'D' }}</span></td>
          <td class="d">{{ Format::money($l->line_total_cents) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <p class="legende">{{ __('billing.pdf.legend', ['tps' => $rate($invoice->tps_rate), 'tvq' => $rate($invoice->tvq_rate)]) }}</p>

  <table class="bas">
    <tr>
      <td style="width:52%;padding-right:24px">
        <div class="paiement">
          @if ($invoice->payment_method === \App\Models\Order::PAY_INTERAC && $balance > 0)
            <h3>{{ __('billing.pdf.interac_title') }}</h3>
            @if ($shop['interac_email'])
              {!! __('billing.pdf.interac', ['total' => '<b>'.e(Format::money($balance)).'</b>', 'email' => '<b>'.e($shop['interac_email']).'</b>']) !!}
            @else
              {{ __('billing.pdf.interac_missing') }}
            @endif
            <br>{!! __('billing.pdf.interac_message', ['number' => '<b>'.e($invoice->number).'</b>']) !!}
            <br>{{ __('billing.pdf.also_cash') }}
          @elseif ($invoice->payments->isNotEmpty())
            <h3>{{ __('billing.pdf.paid_title') }}</h3>
            @foreach ($invoice->payments as $p){{ Format::date($p->paid_at) }} · {{ __('billing.methods.'.$p->method) }} · {{ Format::money($p->amount_cents) }}<br>@endforeach
          @else
            {{ __('billing.pdf.also_cash') }}
          @endif
          <div><span class="statut {{ $status === 'payee' ? 'statut-ok' : '' }}">{{ __('billing.pdf.'.$stamp) }}</span></div>
        </div>
      </td>
      <td>
        <table class="totaux">
          <tr><td>{{ __('billing.pdf.subtotal') }}</td><td class="d">{{ Format::money($invoice->subtotal_cents) }}</td></tr>
          <tr><td>{{ __('billing.pdf.zero_rated') }}</td><td class="d">{{ Format::money($zeroRated) }}</td></tr>
          <tr><td>{{ __('billing.pdf.taxable') }}</td><td class="d">{{ Format::money($invoice->taxable_cents) }}</td></tr>
          @if ($invoice->delivery_fee_cents)
            <tr><td>{{ __('billing.pdf.delivery_fee') }}</td><td class="d">{{ Format::money($invoice->delivery_fee_cents) }}</td></tr>
          @endif
          <tr><td>{{ __('billing.pdf.tps', ['rate' => $rate($invoice->tps_rate)]) }} — {{ $shop['tps_number'] ? __('billing.pdf.tax_number', ['number' => $shop['tps_number']]) : __('billing.pdf.tax_number_missing') }}</td><td class="d">{{ Format::money($invoice->tax_tps_cents) }}</td></tr>
          <tr><td>{{ __('billing.pdf.tvq', ['rate' => $rate($invoice->tvq_rate)]) }} — {{ $shop['tvq_number'] ? __('billing.pdf.tax_number', ['number' => $shop['tvq_number']]) : __('billing.pdf.tax_number_missing') }}</td><td class="d">{{ Format::money($invoice->tax_tvq_cents) }}</td></tr>
          <tr class="grand"><td>{{ __('billing.pdf.total') }}</td><td class="d">{{ Format::money($invoice->total_cents) }}</td></tr>
          @if ($credited)<tr><td>{{ __('billing.show.credited') }}</td><td class="d">− {{ Format::money($credited) }}</td></tr>@endif
          @if ($paid)<tr><td>{{ __('billing.show.paid') }}</td><td class="d">− {{ Format::money($paid) }}</td></tr>@endif
          @if ($credited || $paid)<tr><td><b>{{ __('billing.pdf.balance') }}</b></td><td class="d"><b>{{ Format::money(max(0, $balance)) }}</b></td></tr>@endif
        </table>
      </td>
    </tr>
  </table>

  <table class="pied"><tr>
    <td>{{ __('billing.pdf.thanks') }}</td>
    <td class="d">{{ __('billing.pdf.correction', ['date' => Format::date($invoice->issued_at)]) }}</td>
  </tr></table>
</body>
</html>
