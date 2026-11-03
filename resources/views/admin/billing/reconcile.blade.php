@extends('layouts.admin')

@php use App\Support\Format; @endphp

@section('title', __('billing.reconcile.title'))

@section('content')
  <div class="barre">
    <div><h1>{{ __('billing.reconcile.title') }}</h1><p>{{ __('billing.reconcile.intro') }}</p></div>
  </div>

  @include('admin.billing._tabs')

  @if ($errors->any())
    <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
  @endif

  <section class="bloc carte" aria-labelledby="t-interac">
    <div class="bloc-tete">
      <h2 id="t-interac">{{ __('billing.reconcile.expected') }} : <span class="num">@money($orders->sum('due'))</span></h2>
    </div>
    @forelse ($orders as $o)
      <article class="ligne-rappro" aria-label="{{ $o->number }}">
        <div class="haut">
          <div>
            <a href="{{ route('admin.orders.show', $o) }}"><b>{{ $o->number }}</b></a> · {{ $o->customer->name }}
            @if ($o->invoice) · <a href="{{ route('admin.invoices.show', $o->invoice) }}">{{ $o->invoice->number }}</a>@endif
            <br><small class="note">{{ __('billing.reconcile.message', ['number' => $o->invoice?->number ?? $o->number]) }} · {{ $o->status->label() }}</small>
          </div>
          <b class="num montant-du">@money($o->due)</b>
        </div>
        <details>
          <summary>{{ __('billing.payment.received') }}</summary>
          @include('admin.billing._payment-form', ['order' => $o, 'due' => $o->due, 'defaultMethod' => 'interac'])
        </details>
      </article>
    @empty
      <p class="note">{{ __('billing.reconcile.empty') }}</p>
    @endforelse
  </section>
@endsection
