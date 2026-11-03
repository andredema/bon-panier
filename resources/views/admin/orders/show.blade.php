@extends('layouts.admin')

@php
    use App\Support\Format;
    use App\Models\Order;
    $slot = app(\App\Services\Orders\PickupSlots::class)->label($order->slot_start, $order->slot_end);
    $pending = $order->items->contains(fn ($i) => $i->isPendingPreorder());
@endphp

@section('title', __('orders.show.title', ['number' => $order->number]))

@section('content')
  <div class="barre">
    <div>
      <p class="fil"><a href="{{ route('admin.orders.index') }}">{{ __('orders.show.back') }}</a></p>
      <h1>{{ __('orders.show.title', ['number' => $order->number]) }} <span class="etiquette {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></h1>
      <p>
        {{ __('orders.show.placed', ['date' => Format::date($order->created_at), 'time' => Format::time($order->created_at)]) }}
        @if ($order->creator) · {{ __('orders.show.by', ['name' => $order->creator->name]) }}@endif
      </p>
    </div>
    <div class="actions">
      <a class="btn btn-secondaire" href="{{ route('admin.orders.picking', $order) }}">{{ __('orders.show.print') }}</a>
      @if ($order->status->next() && ! ($pending && $order->status->next()->needsPreordersServed()))
        <form method="POST" action="{{ route('admin.orders.advance', $order) }}">
          @csrf
          <button class="btn btn-primaire" type="submit">{{ $order->status->nextActionLabel() }}</button>
        </form>
      @endif
    </div>
  </div>

  @if ($errors->has('order'))
    <div class="alerte alerte-erreur" role="alert">{{ $errors->first('order') }}</div>
  @endif
  @if ($pending && $order->status->reservesStock())
    <p class="alerte alerte-avert">{{ __('orders.show.blocked') }}</p>
  @endif
  @if ($order->status === \App\Enums\OrderStatus::Cancelled)
    <p class="alerte alerte-erreur">{{ __('orders.show.cancelled', ['reason' => $order->cancel_reason]) }}</p>
  @endif

  <div class="rangee-fiche">
    <section class="bloc carte" aria-labelledby="t-lignes">
      <h2 id="t-lignes">{{ __('orders.show.lines') }}</h2>
      <table class="recap-admin">
        <tbody>
          @foreach ($order->items as $item)
            <tr>
              <td>
                <b>{{ $item->product_name }}</b>
                <small>{{ $item->format_label }} · {{ $item->channel->label() }} · {{ Format::quantity($item->qty) }}{{ $item->qty_unit ? ' '.$item->qty_unit : '' }} × @money($item->unit_price_cents)</small>
                @if ($item->is_preorder)
                  <small class="{{ $item->preorder_served_at ? '' : 'preco-txt' }}">
                    @if ($item->preorder_served_at){{ __('orders.show.preorder_served') }}
                    @elseif ($item->expected_on){{ __('orders.show.preorder_waiting', ['date' => Format::shortDate($item->expected_on)]) }}
                    @else{{ __('orders.show.preorder_waiting_no_date') }}@endif
                  </small>
                @endif
                @if ($item->lots->isNotEmpty())
                  <small>{{ __('orders.show.lots') }} :
                    @foreach ($item->lots as $l){{ $l->lot->lot_number }} ({{ Format::quantity($l->qty) }} {{ $item->product->stockUnit->short_label }})@if (! $loop->last), @endif @endforeach
                  </small>
                @endif
              </td>
              <td class="num">@money($item->line_total_cents)</td>
            </tr>
          @endforeach
          <tr><td>{{ __('shop.cart.subtotal') }}</td><td class="num">@money($order->subtotal_cents)</td></tr>
          @if ($order->delivery_fee_cents)<tr><td>{{ __('shop.checkout.delivery') }}</td><td class="num">@money($order->delivery_fee_cents)</td></tr>@endif
          <tr><td>TPS</td><td class="num">@money($order->tax_tps_cents)</td></tr>
          <tr><td>TVQ</td><td class="num">@money($order->tax_tvq_cents)</td></tr>
          <tr class="total"><td>{{ __('shop.cart.total') }}</td><td class="num">@money($order->total_cents)</td></tr>
        </tbody>
      </table>
    </section>

    <div class="col">
      <section class="bloc carte" aria-labelledby="t-client">
        <h2 id="t-client">{{ __('orders.show.customer') }}</h2>
        <p class="infos-client">
          <b>{{ $order->customer->name }}</b><br>
          <a href="tel:{{ preg_replace('/[^0-9+]/', '', $order->customer->phone) }}">{{ $order->customer->phone }}</a>
          @if ($order->customer->email)<br><a href="mailto:{{ $order->customer->email }}">{{ $order->customer->email }}</a>@endif
        </p>
        <h3>{{ __('orders.show.reception') }}</h3>
        <p class="note">{{ $order->fulfilment === Order::DELIVERY ? __('shop.checkout.delivery') : __('shop.checkout.pickup') }} · {{ $slot }}
          @if ($order->delivery_address)<br>{{ $order->delivery_address }}@endif</p>
        <h3>{{ __('orders.show.payment') }}</h3>
        <p class="note">{{ $order->payment_method === Order::PAY_INTERAC ? __('orders.show.interac') : __('orders.show.on_handover') }}</p>
        @if ($order->notes)
          <h3>{{ __('orders.show.notes') }}</h3>
          <p class="note">{{ $order->notes }}</p>
        @endif
      </section>

      <section class="bloc carte" aria-labelledby="t-hist">
        <h2 id="t-hist">{{ __('orders.show.history') }}</h2>
        <ol class="historique">
          @foreach ($order->history as $h)
            <li>
              <span class="etiquette {{ $h->to->badgeClass() }}">{{ $h->to->label() }}</span>
              {{ Format::date($h->created_at) }}, {{ Format::time($h->created_at) }} · {{ $h->user?->name ?? __('orders.show.system') }}
              @if ($h->note)<br><small class="note">{{ $h->note }}</small>@endif
            </li>
          @endforeach
        </ol>
      </section>

      @if ($order->status->reservesStock())
        <details class="bloc carte annulation" @if ($errors->has('reason')) open @endif>
          <summary>{{ __('orders.show.cancel') }}</summary>
          <form method="POST" action="{{ route('admin.orders.cancel', $order) }}">
            @csrf
            <div class="champ">
              <label for="reason">{{ __('orders.show.cancel_reason') }}</label>
              <input id="reason" name="reason" value="{{ old('reason') }}" maxlength="255" required aria-describedby="reason-aide">
              <span class="note" id="reason-aide">{{ __('orders.show.cancel_hint') }}</span>
              @error('reason')<span class="erreur">{{ $message }}</span>@enderror
            </div>
            <button class="btn btn-secondaire lien-danger" type="submit">{{ __('orders.show.cancel_confirm') }}</button>
          </form>
        </details>
      @endif
    </div>
  </div>
@endsection
