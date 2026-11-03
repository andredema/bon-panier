@php
    use App\Support\Format;
    use App\Support\Quantity;
    $shop = $invoice->shop_snapshot;
    $c = $invoice->customer_snapshot;
    $rate = fn (int $r) => Format::quantity(Quantity::fromMilli($r));
@endphp
<!doctype html>
<html lang="fr-CA">
<head><meta charset="utf-8"><title>{{ $note->number }}</title>@include('pdf._style')</head>
<body>
  @include('pdf._header', [
      'docTitle' => __('billing.pdf.credit_note'),
      'docFields' => [
          __('billing.pdf.number') => $note->number,
          __('billing.pdf.date') => Format::date($note->issued_at),
      ],
  ])

  <table class="parties">
    <tr>
      <td><h3>{{ __('billing.pdf.billed_to') }}</h3><p><b>{{ $c['name'] }}</b><br>{{ $c['phone'] }}@if ($c['email'])<br>{{ $c['email'] }}@endif</p></td>
      <td>
        <h3>{{ __('billing.pdf.reason') }}</h3>
        <p>{{ $note->reason }}<br>{{ __('billing.pdf.credit_for', ['number' => $invoice->number, 'date' => Format::date($invoice->issued_at)]) }}</p>
      </td>
    </tr>
  </table>

  <table class="lignes">
    <thead><tr><th>{{ __('billing.pdf.product') }}</th><th>{{ __('billing.pdf.tax') }}</th><th class="d">{{ __('billing.pdf.credited') }}</th></tr></thead>
    <tbody>
      @foreach ($note->lines as $l)
        <tr><td>{{ $l->label }}</td><td><span class="code {{ $l->is_taxable ? 'code-t' : 'code-d' }}">{{ $l->is_taxable ? 'T' : 'D' }}</span></td><td class="d">{{ Format::money($l->amount_cents) }}</td></tr>
      @endforeach
      @if ($note->delivery_cents)
        <tr><td>{{ __('billing.pdf.delivery_fee') }}</td><td><span class="code {{ $invoice->delivery_taxable ? 'code-t' : 'code-d' }}">{{ $invoice->delivery_taxable ? 'T' : 'D' }}</span></td><td class="d">{{ Format::money($note->delivery_cents) }}</td></tr>
      @endif
    </tbody>
  </table>

  <table class="bas"><tr>
    <td style="width:52%"></td>
    <td>
      <table class="totaux">
        <tr><td>{{ __('billing.pdf.subtotal') }}</td><td class="d">{{ Format::money($note->subtotal_cents) }}</td></tr>
        <tr><td>{{ __('billing.pdf.tps', ['rate' => $rate($invoice->tps_rate)]) }}</td><td class="d">{{ Format::money($note->tax_tps_cents) }}</td></tr>
        <tr><td>{{ __('billing.pdf.tvq', ['rate' => $rate($invoice->tvq_rate)]) }}</td><td class="d">{{ Format::money($note->tax_tvq_cents) }}</td></tr>
        <tr class="grand"><td>{{ __('billing.pdf.credited') }}</td><td class="d">{{ Format::money($note->total_cents) }}</td></tr>
      </table>
    </td>
  </tr></table>

  <table class="pied"><tr><td>{{ __('billing.pdf.thanks') }}</td><td class="d">{{ __('billing.pdf.correction', ['date' => Format::date($note->issued_at)]) }}</td></tr></table>
</body>
</html>
