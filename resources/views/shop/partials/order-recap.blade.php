{{-- Récapitulatif d'une commande (confirmation et suivi) --}}
<table class="recap">
  <tbody>
    @foreach ($order->items as $item)
      <tr>
        <td>
          <b>{{ $item->product_name }}</b><br>
          <small>{{ $item->format_label }} · {{ \App\Support\Format::quantity($item->qty) }}{{ $item->qty_unit ? ' '.$item->qty_unit : '' }}
            @if ($item->is_preorder)
              · @if ($item->preorder_served_at){{ __('preorders.tracking.arrived') }}@elseif ($item->expected_on){{ __('preorders.tracking.waiting', ['date' => \App\Support\Format::shortDate($item->expected_on)]) }}@else{{ __('preorders.tracking.waiting_no_date') }}@endif
            @endif</small>
        </td>
        <td class="num">@money($item->line_total_cents)</td>
      </tr>
    @endforeach
    <tr><td>{{ __('shop.cart.subtotal') }}</td><td class="num">@money($order->subtotal_cents)</td></tr>
    @if ($order->delivery_fee_cents)
      <tr><td>{{ __('shop.checkout.delivery') }}</td><td class="num">@money($order->delivery_fee_cents)</td></tr>
    @endif
    <tr><td>TPS</td><td class="num">@money($order->tax_tps_cents)</td></tr>
    <tr><td>TVQ</td><td class="num">@money($order->tax_tvq_cents)</td></tr>
    <tr class="total"><td>{{ __('shop.cart.total') }}</td><td class="num">@money($order->total_cents)</td></tr>
  </tbody>
</table>
