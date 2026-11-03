@extends('layouts.shop')

@php
    $slot = app(\App\Services\Orders\PickupSlots::class)->label($order->slot_start, $order->slot_end);
    $mode = $order->fulfilment === \App\Models\Order::DELIVERY ? __('shop.checkout.delivery') : __('shop.checkout.pickup');
@endphp

@section('title', __('shop.tracking.title', ['number' => $order->number]))

@section('content')
<main class="page-simple" id="contenu">
  <section class="carte" aria-labelledby="t-suivi">
    <h1 id="t-suivi">{{ __('shop.tracking.title', ['number' => $order->number]) }}</h1>
    <p>{{ __('shop.tracking.status') }} : <span class="etiquette {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></p>
    <p>{{ __('shop.confirmation.slot', ['mode' => $mode, 'slot' => $slot]) }}</p>
  </section>

  <section class="carte" aria-labelledby="t-hist">
    <h2 id="t-hist">{{ __('shop.tracking.history') }}</h2>
    <ul class="etapes-suivi">
      @foreach ($order->history as $h)
        <li>@date($h->created_at), {{ \App\Support\Format::time($h->created_at) }} — {{ $h->to->label() }}</li>
      @endforeach
    </ul>
  </section>

  @if ($order->payment_method === \App\Models\Order::PAY_INTERAC && $interacEmail)
    <p class="paiement-interac">
      {!! __('shop.confirmation.interac', ['total' => '<b>'.e(\App\Support\Format::money($order->total_cents)).'</b>', 'email' => '<b>'.e($interacEmail).'</b>', 'number' => '<b>'.e($order->number).'</b>']) !!}
    </p>
  @endif

  <section class="carte" aria-labelledby="t-lignes">
    <h2 id="t-lignes">{{ __('shop.tracking.lines') }}</h2>
    @include('shop.partials.order-recap')
  </section>
</main>
@endsection
