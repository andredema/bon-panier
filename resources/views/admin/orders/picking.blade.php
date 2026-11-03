<!doctype html>
<html lang="fr-CA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('orders.picking.title', ['number' => $order->number]) }} — {{ __('app.shop_name') }}</title>
@fonts
@vite(['resources/css/admin.css'])
</head>
<body class="page-etiquettes">
@php
    use App\Support\Format;
    use App\Support\Quantity;
    $slot = app(\App\Services\Orders\PickupSlots::class)->label($order->slot_start, $order->slot_end);
@endphp
<div class="barre ne-pas-imprimer">
  <a class="btn btn-secondaire" href="{{ route('admin.orders.show', $order) }}">{{ __('orders.picking.back') }}</a>
  <button class="btn btn-primaire" type="button" onclick="window.print()">{{ __('orders.picking.print') }}</button>
</div>

<main class="bon-prep">
  <h1>{{ __('orders.picking.title', ['number' => $order->number]) }}</h1>
  <p>
    <b>{{ $order->customer->name }}</b> · {{ $order->customer->phone }}<br>
    {{ $order->fulfilment === \App\Models\Order::DELIVERY ? __('shop.checkout.delivery') : __('shop.checkout.pickup') }} · {{ $slot }}
    @if ($order->delivery_address)<br>{{ $order->delivery_address }}@endif
  </p>

  <table>
    <thead><tr>
      <th scope="col">{{ __('orders.picking.col_done') }}</th>
      <th scope="col">{{ __('orders.picking.col_product') }}</th>
      <th scope="col">{{ __('orders.picking.col_qty') }}</th>
      <th scope="col">{{ __('orders.picking.col_lots') }}</th>
    </tr></thead>
    <tbody>
      @foreach ($order->items as $item)
        @php $plan = $plans[$item->id]; $u = $item->product->stockUnit->short_label; @endphp
        <tr>
          <td><span class="case-vide" aria-hidden="true"></span></td>
          <td><b>{{ $item->product_name }}</b><br><small>{{ $item->format_label }}</small></td>
          <td class="num">{{ Format::quantity($item->qty) }}{{ $item->qty_unit ? ' '.$item->qty_unit : '' }}</td>
          <td>
            @if ($plan === null){{ __('orders.picking.waiting') }}
            @elseif ($plan === false){{ __('orders.picking.no_stock') }}
            @else
              @foreach ($plan as $p){{ $p['lot']->lot_number }} : {{ Format::quantity(Quantity::fromMilli($p['milli'])) }} {{ $u }}{{ $p['lot']->best_before ? ' ('.Format::shortDate($p['lot']->best_before).')' : '' }}@if (! $loop->last)<br>@endif @endforeach
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>

  @if ($order->notes)<p><b>{{ __('orders.show.notes') }} :</b> {{ $order->notes }}</p>@endif
  <p>{{ __('orders.picking.prepared_by') }}</p>
</main>
</body>
</html>
