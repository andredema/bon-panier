@extends('layouts.shop')

@php
    $slot = app(\App\Services\Orders\PickupSlots::class)->label($order->slot_start, $order->slot_end);
    $mode = $order->fulfilment === \App\Models\Order::DELIVERY ? __('shop.checkout.delivery') : __('shop.checkout.pickup');
@endphp

@section('title', __('shop.confirmation.number', ['number' => $order->number]))

@section('content')
<main class="page-simple" id="contenu">
  <section class="carte" aria-labelledby="t-merci">
    <h1 id="t-merci">{{ __('shop.confirmation.title') }}</h1>
    <p><b>{{ __('shop.confirmation.number', ['number' => $order->number]) }}</b></p>
    <p>{{ __('shop.confirmation.intro', ['email' => $order->customer->email]) }}</p>
    <p>{{ __('shop.confirmation.slot', ['mode' => $mode, 'slot' => $slot]) }}</p>
  </section>

  @if ($order->payment_method === \App\Models\Order::PAY_INTERAC)
    <section class="paiement-interac" aria-labelledby="t-interac">
      <h2 id="t-interac">{{ __('shop.confirmation.interac_title') }}</h2>
      <p>
        @if ($interacEmail)
          {!! __('shop.confirmation.interac', ['total' => '<b>'.e(\App\Support\Format::money($order->total_cents)).'</b>', 'email' => '<b>'.e($interacEmail).'</b>', 'number' => '<b>'.e($order->number).'</b>']) !!}
        @else
          {{ __('shop.confirmation.interac_missing') }}
        @endif
      </p>
    </section>
  @else
    <p class="carte">{{ __('shop.confirmation.on_handover') }}</p>
  @endif

  @if ($order->has_preorder)
    <p class="carte">{{ __('shop.confirmation.preorder') }}</p>
  @endif

  <section class="carte" aria-label="{{ __('shop.tracking.lines') }}">@include('shop.partials.order-recap')</section>

  <p>
    <a class="btn btn-primaire" href="{{ $order->trackingUrl() }}">{{ __('shop.confirmation.track') }}</a>
    <a class="btn btn-secondaire" href="{{ route('shop.home') }}">{{ __('shop.confirmation.back') }}</a>
  </p>
</main>
@endsection
