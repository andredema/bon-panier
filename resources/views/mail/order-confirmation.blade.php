@php
    use App\Support\Format;
    $slot = app(\App\Services\Orders\PickupSlots::class)->label($order->slot_start, $order->slot_end, $order->created_at);
    $mode = $order->fulfilment === \App\Models\Order::DELIVERY ? __('shop.checkout.delivery') : __('shop.checkout.pickup');
@endphp
<!doctype html>
<html lang="fr-CA">
<head><meta charset="utf-8"><title>{{ __('shop.mail.confirmation_subject', ['number' => $order->number]) }}</title></head>
<body style="margin:0;background:#F7F2E8;font-family:Arial,Helvetica,sans-serif;color:#1E1B16;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F2E8;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:1px solid #E6DCC8;border-radius:14px;">
  <tr><td style="background:#1F4D2E;color:#F7F2E8;padding:18px 24px;border-radius:14px 14px 0 0;font-size:20px;font-weight:bold;">{{ $shopName }}</td></tr>
  <tr><td style="padding:24px;font-size:15px;line-height:1.5;">
    <p style="margin:0 0 12px;">{{ __('shop.mail.hello', ['name' => $order->customer->name]) }}</p>
    <p style="margin:0 0 12px;">{{ __('shop.mail.received', ['number' => $order->number]) }}</p>
    <p style="margin:0 0 16px;"><b>{{ __('shop.mail.reception', ['mode' => $mode, 'slot' => $slot]) }}</b><br>{{ $shopAddress }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;">
      @foreach ($order->items as $item)
        <tr>
          <td style="padding:6px 0;border-bottom:1px solid #F0EADF;">{{ $item->product_name }} — {{ $item->format_label }} × {{ Format::quantity($item->qty) }}{{ $item->qty_unit ? ' '.$item->qty_unit : '' }}
            @if ($item->is_preorder)<br><span style="color:#6A430A;">{{ __('shop.mail.preorder', ['date' => $item->expected_on ? Format::shortDate($item->expected_on) : '—']) }}</span>@endif</td>
          <td style="padding:6px 0;border-bottom:1px solid #F0EADF;text-align:right;white-space:nowrap;">{{ Format::money($item->line_total_cents) }}</td>
        </tr>
      @endforeach
      <tr><td style="padding:6px 0;">{{ __('shop.cart.subtotal') }}</td><td style="text-align:right;">{{ Format::money($order->subtotal_cents) }}</td></tr>
      @if ($order->delivery_fee_cents)<tr><td style="padding:6px 0;">{{ __('shop.checkout.delivery') }}</td><td style="text-align:right;">{{ Format::money($order->delivery_fee_cents) }}</td></tr>@endif
      <tr><td style="padding:6px 0;">TPS</td><td style="text-align:right;">{{ Format::money($order->tax_tps_cents) }}</td></tr>
      <tr><td style="padding:6px 0;">TVQ</td><td style="text-align:right;">{{ Format::money($order->tax_tvq_cents) }}</td></tr>
      <tr><td style="padding:8px 0;font-weight:bold;font-size:16px;">{{ __('shop.cart.total') }}</td><td style="text-align:right;font-weight:bold;font-size:16px;">{{ Format::money($order->total_cents) }}</td></tr>
    </table>

    @if ($order->payment_method === \App\Models\Order::PAY_INTERAC)
      <div style="background:#F6E8CF;border-radius:10px;padding:14px;margin:16px 0;">
        <b>{{ __('shop.mail.interac_title') }}</b><br>
        @if ($interacEmail)
          {{ __('shop.mail.interac', ['total' => Format::money($order->total_cents), 'email' => $interacEmail, 'number' => $order->number]) }}
        @else
          {{ __('shop.mail.interac_missing') }}
        @endif
      </div>
    @else
      <p style="margin:16px 0;">{{ __('shop.mail.on_handover') }}</p>
    @endif

    <p style="margin:16px 0;"><a href="{{ $order->trackingUrl() }}" style="display:inline-block;background:#1F4D2E;color:#FFFFFF;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;">{{ __('shop.mail.track') }}</a></p>
    <p style="margin:0;">{{ __('shop.mail.thanks') }}</p>
    @include('mail.partials.unsubscribe', ['customer' => $order->customer])
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
