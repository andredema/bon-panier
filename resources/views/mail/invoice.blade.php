@php
    use App\Support\Format;
    $balance = $invoice->balanceCents();
@endphp
<!doctype html>
<html lang="fr-CA">
<head><meta charset="utf-8"><title>{{ __('billing.mail.subject', ['number' => $invoice->number]) }}</title></head>
<body style="margin:0;background:#F7F2E8;font-family:Arial,Helvetica,sans-serif;color:#1E1B16;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F2E8;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:1px solid #E6DCC8;border-radius:14px;">
  <tr><td style="background:#1F4D2E;color:#F7F2E8;padding:18px 24px;border-radius:14px 14px 0 0;font-size:20px;font-weight:bold;">{{ $invoice->shop_snapshot['name'] }}</td></tr>
  <tr><td style="padding:24px;font-size:15px;line-height:1.5;">
    <p style="margin:0 0 12px;">{{ __('shop.mail.hello', ['name' => $invoice->customer_snapshot['name']]) }}</p>
    <p style="margin:0 0 12px;">{{ __('billing.mail.body', ['number' => $invoice->number, 'total' => Format::money($invoice->total_cents)]) }}</p>
    <p style="margin:0 0 12px;"><b>{{ $balance > 0 ? __('billing.mail.balance', ['balance' => Format::money($balance)]) : __('billing.mail.paid') }}</b></p>
    @if ($balance > 0 && $invoice->payment_method === \App\Models\Order::PAY_INTERAC && $invoice->shop_snapshot['interac_email'])
      <p style="margin:0 0 12px;background:#F6E8CF;border-radius:10px;padding:12px;">{{ __('shop.mail.interac', ['total' => Format::money($balance), 'email' => $invoice->shop_snapshot['interac_email'], 'number' => $invoice->number]) }}</p>
    @endif
    <p style="margin:0;">{{ __('shop.mail.thanks') }}</p>
    @include('mail.partials.unsubscribe', ['customer' => $invoice->customer])
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
