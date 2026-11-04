@extends('layouts.admin')

@php use App\Support\Format; $manager = auth()->user()->isManager(); @endphp

@section('title', $customer->name)

@section('content')
  <div class="barre">
    <div>
      <p class="fil"><a href="{{ route('admin.customers.index') }}">{{ __('customers.back') }}</a></p>
      <h1>{{ $customer->name }} <span class="etiquette {{ $customer->type === 'gros' ? 'e-gros' : 'e-detail' }}">{{ __('customers.types.'.$customer->type) }}</span></h1>
      <p>
        @if ($customer->isErased())
          {{ __('customers.erased_on', ['date' => Format::date($customer->erased_at)]) }}
        @else
          {{ $customer->consent_at ? __('customers.consent_on', ['date' => Format::date($customer->consent_at)]) : __('customers.no_consent') }}
        @endif
        · {{ __('customers.since', ['date' => Format::date($customer->created_at)]) }}
      </p>
      @unless ($customer->isErased())
        <p>
          @if ($customer->isSubscribed())
            {{ __('customers.marketing.subscribed', ['date' => Format::date($customer->marketing_consent_at)]) }}
          @elseif ($customer->marketing_unsubscribed_at)
            {{ __('customers.marketing.unsubscribed_on', ['date' => Format::date($customer->marketing_unsubscribed_at)]) }}
          @else
            {{ __('customers.marketing.none') }}
          @endif
        </p>
      @endunless
    </div>
    <div class="actions">
      @if ($customer->isSubscribed())
        <form method="POST" action="{{ route('admin.customers.unsubscribe', $customer) }}">@csrf<button class="btn btn-secondaire" type="submit">{{ __('customers.marketing.unsubscribe') }}</button></form>
      @endif
      @if ($manager)<a class="btn btn-secondaire" href="{{ route('admin.customers.export', $customer) }}">{{ __('customers.export') }}</a>@endif
    </div>
  </div>

  <div class="kpis kpis-client">
    <div class="kpi carte"><span class="lib">{{ __('customers.kpi.orders') }}</span><span class="val">{{ $customer->orders->count() }}</span></div>
    <div class="kpi carte"><span class="lib">{{ __('customers.kpi.spent') }}</span><span class="val">@money($customer->invoices->sum('total_cents'))</span><span class="det">{{ __('customers.kpi.spent_note') }}</span></div>
    <div class="kpi carte {{ $balance > 0 ? 'accent' : '' }}"><span class="lib">{{ __('customers.kpi.balance') }}</span><span class="val">@money($balance)</span></div>
  </div>

  @unless ($customer->isErased())
    <form class="bloc carte" method="POST" action="{{ route('admin.customers.update', $customer) }}" novalidate>
      @csrf @method('PUT')
      <h2>{{ __('customers.details') }}</h2>
      <div class="grille-form">
        <div class="champ">
          <label for="name">{{ __('customers.fields.name') }}</label>
          <input id="name" name="name" value="{{ old('name', $customer->name) }}" required maxlength="120" autocomplete="off">
          @error('name')<span class="erreur">{{ $message }}</span>@enderror
        </div>
        <div class="champ">
          <label for="phone">{{ __('customers.fields.phone') }}</label>
          <input id="phone" name="phone" type="tel" value="{{ old('phone', $customer->phone) }}" required maxlength="40" autocomplete="off">
          @error('phone')<span class="erreur">{{ $message }}</span>@enderror
        </div>
        <div class="champ">
          <label for="email">{{ __('customers.fields.email') }}</label>
          <input id="email" name="email" type="email" value="{{ old('email', $customer->email) }}" maxlength="255" autocomplete="off">
          @error('email')<span class="erreur">{{ $message }}</span>@enderror
        </div>
        <div class="champ">
          <label for="type">{{ __('customers.fields.type') }}</label>
          <select id="type" name="type">
            @foreach (\App\Models\Customer::TYPES as $t)<option value="{{ $t }}" @selected(old('type', $customer->type) === $t)>{{ __('customers.types.'.$t) }}</option>@endforeach
          </select>
        </div>
        <div class="champ plein">
          <label for="address">{{ __('customers.fields.address') }}</label>
          <input id="address" name="address" value="{{ old('address', $customer->address) }}" maxlength="255" autocomplete="off">
          @error('address')<span class="erreur">{{ $message }}</span>@enderror
        </div>
        @if ($manager)
          <label class="check plein"><input type="checkbox" name="approved_wholesale" value="1" @checked(old('approved_wholesale', $customer->approved_wholesale))> {{ __('customers.fields.approved_wholesale') }}</label>
        @elseif ($customer->approved_wholesale)
          <p class="note plein">{{ __('customers.fields.approved_wholesale') }}</p>
        @endif
      </div>
      <div><button class="btn btn-primaire" type="submit">{{ __('customers.save') }}</button></div>
    </form>
  @endunless

  <section class="bloc carte" aria-labelledby="t-hist">
    <h2 id="t-hist">{{ __('customers.history') }}</h2>
    @if ($customer->orders->isEmpty())
      <p class="note">{{ __('customers.no_orders') }}</p>
    @else
      <table class="tableau-cartes">
        <thead><tr>
          <th scope="col">{{ __('billing.cols.order') }}</th><th scope="col">{{ __('billing.cols.date') }}</th><th scope="col">{{ __('billing.cols.status') }}</th>
          <th scope="col">{{ __('billing.cols.number') }}</th><th scope="col" class="droite">{{ __('billing.cols.total') }}</th><th scope="col" class="droite">{{ __('billing.cols.balance') }}</th>
        </tr></thead>
        <tbody>
          @foreach ($customer->orders as $o)
            <tr>
              <td data-lib="{{ __('billing.cols.order') }}"><a href="{{ route('admin.orders.show', $o) }}">{{ $o->number }}</a></td>
              <td data-lib="{{ __('billing.cols.date') }}">{{ Format::date($o->created_at) }}</td>
              <td data-lib="{{ __('billing.cols.status') }}"><span class="etiquette {{ $o->status->badgeClass() }}">{{ $o->status->label() }}</span></td>
              <td data-lib="{{ __('billing.cols.number') }}">@if ($o->invoice)<a href="{{ route('admin.invoices.show', $o->invoice) }}">{{ $o->invoice->number }}</a>@else — @endif</td>
              <td data-lib="{{ __('billing.cols.total') }}" class="num droite-large">@money($o->total_cents)</td>
              <td data-lib="{{ __('billing.cols.balance') }}" class="num droite-large">{{ $o->invoice ? Format::money(max(0, $o->invoice->balanceCents())) : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </section>

  @if ($manager && ! $customer->isErased())
    <details class="bloc carte annulation" @if ($errors->has('erase') || $errors->has('confirm')) open @endif>
      <summary>{{ __('customers.erase.title') }}</summary>
      <p class="note">{{ __('customers.erase.intro') }}</p>
      @if ($blocker)
        <p class="alerte alerte-avert">{{ $blocker }}</p>
      @else
        @error('erase')<p class="alerte alerte-erreur" role="alert">{{ $message }}</p>@enderror
        <form method="POST" action="{{ route('admin.customers.erase', $customer) }}">
          @csrf
          <label class="check"><input type="checkbox" name="confirm" value="1"> {{ __('customers.erase.confirm', ['name' => $customer->name]) }}</label>
          @error('confirm')<span class="erreur">{{ $message }}</span>@enderror
          <button class="btn btn-secondaire lien-danger" type="submit">{{ __('customers.erase.submit') }}</button>
        </form>
      @endif
    </details>
  @endif
@endsection
