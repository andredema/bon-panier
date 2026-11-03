@php
    use App\Support\Format;
    $slot = app(\App\Services\Orders\PickupSlots::class)->label($order->slot_start, $order->slot_end, now());
    $mode = $order->fulfilment === \App\Models\Order::DELIVERY ? __('shop.checkout.delivery') : __('shop.checkout.pickup');
@endphp
<!doctype html>
<html lang="fr-CA">
<head><meta charset="utf-8"><title>{{ __('preorders.mail.subject', ['number' => $order->number]) }}</title></head>
<body style="margin:0;background:#F7F2E8;font-family:Arial,Helvetica,sans-serif;color:#1E1B16;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F2E8;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:1px solid #E6DCC8;border-radius:14px;">
  <tr><td style="background:#1F4D2E;color:#F7F2E8;padding:18px 24px;border-radius:14px 14px 0 0;font-size:20px;font-weight:bold;">{{ $shopName }}</td></tr>
  <tr><td style="padding:24px;font-size:15px;line-height:1.5;">
    <p style="margin:0 0 12px;">{{ __('shop.mail.hello', ['name' => $order->customer->name]) }}</p>
    <p style="margin:0 0 12px;">{{ __('preorders.mail.arrived', ['number' => $order->number]) }}</p>
    <ul style="margin:0 0 16px;padding-left:20px;">
      @foreach ($arrived as $item)
        <li>{{ $item->product_name }} — {{ $item->format_label }} × {{ Format::quantity($item->qty) }}{{ $item->qty_unit ? ' '.$item->qty_unit : '' }}</li>
      @endforeach
    </ul>
    @if ($stillWaiting->isNotEmpty())
      <p style="margin:0 0 16px;background:#F6E8CF;border-radius:10px;padding:12px;color:#6A430A;">{{ __('preorders.mail.still_waiting', ['count' => $stillWaiting->count()]) }}</p>
    @else
      <p style="margin:0 0 16px;">{{ __('preorders.mail.preparing') }}</p>
    @endif
    <p style="margin:0 0 16px;"><b>{{ __('shop.mail.reception', ['mode' => $mode, 'slot' => $slot]) }}</b><br>{{ $shopAddress }}</p>
    <p style="margin:16px 0;"><a href="{{ $order->trackingUrl() }}" style="display:inline-block;background:#1F4D2E;color:#FFFFFF;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;">{{ __('shop.mail.track') }}</a></p>
    <p style="margin:0;">{{ __('shop.mail.thanks') }}</p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
