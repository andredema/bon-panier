@extends('layouts.admin')

@php use App\Support\Format; @endphp

@section('title', __('billing.title'))

@section('content')
  <div class="barre">
    <div><h1>{{ __('billing.title') }}</h1><p>{{ __('billing.intro') }}</p></div>
  </div>

  @include('admin.billing._tabs')

  <form class="filtres-liste filtres-factures carte" method="GET" action="{{ route('admin.invoices.index') }}">
    <div class="champ"><label for="q">{{ __('orders.search') }}</label><input id="q" name="q" type="search" value="{{ $q }}" placeholder="{{ __('billing.search') }}"></div>
    <div class="champ">
      <label for="statut">{{ __('billing.cols.status') }}</label>
      <select id="statut" name="statut">
        <option value="">{{ __('billing.all_statuses') }}</option>
        @foreach (__('billing.status') as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach
      </select>
    </div>
    <div class="champ"><label for="du">{{ __('billing.from') }}</label><input id="du" name="du" type="date" value="{{ $from }}"></div>
    <div class="champ"><label for="au">{{ __('billing.to') }}</label><input id="au" name="au" type="date" value="{{ $to }}"></div>
    <button class="btn btn-secondaire" type="submit">{{ __('billing.filter') }}</button>
  </form>

  <section class="bloc carte" aria-labelledby="t-fact">
    <div class="bloc-tete">
      <h2 id="t-fact">{{ trans_choice('billing.count', $invoices->count()) }}</h2>
      <span class="note">{{ __('billing.show.balance') }} : <b class="num">@money($totalDue)</b></span>
    </div>
    @if ($invoices->isEmpty())
      <p class="note">{{ __('billing.empty') }}</p>
    @else
      <table class="tableau-cartes">
        <thead><tr>
          @foreach (['number', 'date', 'customer', 'order'] as $c)<th scope="col">{{ __('billing.cols.'.$c) }}</th>@endforeach
          <th scope="col" class="droite">{{ __('billing.cols.total') }}</th>
          <th scope="col" class="droite">{{ __('billing.cols.balance') }}</th>
          <th scope="col">{{ __('billing.cols.status') }}</th>
        </tr></thead>
        <tbody>
          @foreach ($invoices as $i)
            <tr>
              <td data-lib="{{ __('billing.cols.number') }}"><a href="{{ route('admin.invoices.show', $i) }}">{{ $i->number }}</a></td>
              <td data-lib="{{ __('billing.cols.date') }}">{{ Format::date($i->issued_at) }}</td>
              <td data-lib="{{ __('billing.cols.customer') }}">{{ $i->customer_snapshot['name'] }}</td>
              <td data-lib="{{ __('billing.cols.order') }}"><a href="{{ route('admin.orders.show', $i->order) }}">{{ $i->order->number }}</a></td>
              <td data-lib="{{ __('billing.cols.total') }}" class="num droite-large">@money($i->total_cents)</td>
              <td data-lib="{{ __('billing.cols.balance') }}" class="num droite-large">@money(max(0, $i->balance))</td>
              <td data-lib="{{ __('billing.cols.status') }}"><span class="etiquette {{ ['a_payer' => 'e-annulee', 'partielle' => 'e-confirmee', 'payee' => 'e-prete'][$i->computed_status] }}">{{ __('billing.status.'.$i->computed_status) }}</span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </section>
@endsection
