@extends('layouts.admin')

@php use App\Support\Format; @endphp

@section('title', __('customers.title'))

@section('content')
  <div class="barre">
    <div><h1>{{ __('customers.title') }}</h1><p>{{ __('customers.intro') }}</p></div>
    @if (auth()->user()->isManager())
      <div class="actions"><a class="btn btn-secondaire" href="{{ route('admin.customers.subscribers') }}">{{ __('customers.marketing.export') }}</a></div>
    @endif
  </div>

  <form class="filtres-liste carte" method="GET" action="{{ route('admin.customers.index') }}">
    <div class="champ"><label for="q">{{ __('orders.search') }}</label><input id="q" name="q" type="search" value="{{ $q }}" placeholder="{{ __('customers.search') }}"></div>
    <div class="champ">
      <label for="type">{{ __('customers.cols.type') }}</label>
      <select id="type" name="type">
        <option value="">{{ __('customers.all_types') }}</option>
        @foreach (\App\Models\Customer::TYPES as $t)<option value="{{ $t }}" @selected($type === $t)>{{ __('customers.types.'.$t) }}</option>@endforeach
      </select>
    </div>
    <label class="check"><input type="checkbox" name="abonnes" value="1" @checked($subscribed)> {{ __('customers.marketing.filter') }}</label>
    <button class="btn btn-secondaire" type="submit">{{ __('billing.filter') }}</button>
  </form>

  <section class="bloc carte" aria-labelledby="t-clients">
    <div class="bloc-tete"><h2 id="t-clients">{{ trans_choice('customers.count', $customers->total()) }}</h2></div>
    @if ($customers->isEmpty())
      <p class="note">{{ __('customers.empty') }}</p>
    @else
      <table class="tableau-cartes">
        <thead><tr>
          @foreach (['name', 'contact', 'type', 'orders', 'last'] as $c)<th scope="col">{{ __('customers.cols.'.$c) }}</th>@endforeach
          <th scope="col" class="droite">{{ __('customers.cols.balance') }}</th>
        </tr></thead>
        <tbody>
          @foreach ($customers as $c)
            @php $bal = $c->balanceCents(); @endphp
            <tr>
              <td data-lib="{{ __('customers.cols.name') }}"><a href="{{ route('admin.customers.show', $c) }}">{{ $c->name }}</a></td>
              <td data-lib="{{ __('customers.cols.contact') }}"><span class="contact">{{ $c->phone ?: '—' }}@if ($c->email)<br><span class="note">{{ $c->email }}</span>@endif</span></td>
              <td data-lib="{{ __('customers.cols.type') }}"><span class="etiquette {{ $c->type === 'gros' ? 'e-gros' : 'e-detail' }}">{{ __('customers.types.'.$c->type) }}</span></td>
              <td data-lib="{{ __('customers.cols.orders') }}" class="num">{{ $c->orders_count }}</td>
              <td data-lib="{{ __('customers.cols.last') }}">{{ $c->orders_max_created_at ? Format::date($c->orders_max_created_at) : '—' }}</td>
              <td data-lib="{{ __('customers.cols.balance') }}" class="num droite-large">{{ $bal > 0 ? Format::money($bal) : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      {{ $customers->links('admin.partials.pagination') }}
    @endif
  </section>
@endsection
