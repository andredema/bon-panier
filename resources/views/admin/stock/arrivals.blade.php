@extends('layouts.admin')

@section('title', __('stock.arrivals.title'))

@section('content')
  <div class="barre">
    <div><h1>{{ __('stock.arrivals.title') }}</h1><p>{{ __('stock.arrivals.intro') }}</p></div>
  </div>

  @include('admin.stock._tabs')

  @if ($errors->any())
    <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
  @endif

  <section class="bloc carte" aria-labelledby="t-attendus">
    <h2 id="t-attendus">{{ __('stock.arrivals.title') }}</h2>
    @if ($open->isEmpty())
      <p class="note">{{ __('stock.arrivals.empty') }}</p>
    @else
      <table class="tableau-cartes">
        <thead>
          <tr>
            <th scope="col">{{ __('stock.arrivals.expected_on') }}</th>
            <th scope="col">{{ __('stock.arrivals.product') }}</th>
            <th scope="col">{{ __('stock.arrivals.supplier') }}</th>
            <th scope="col">{{ __('stock.arrivals.qty') }}</th>
            <th scope="col"><span class="sr">{{ __('stock.arrivals.cancel') }}</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($open as $a)
            <tr>
              <td data-lib="{{ __('stock.arrivals.expected_on') }}">
                @date($a->expected_on)
                @if ($a->expected_on->isToday())<span class="etiquette e-prete">{{ __('stock.arrivals.today') }}</span>
                @elseif ($a->expected_on->isPast())<span class="etiquette e-annulee">{{ __('stock.arrivals.late') }}</span>@endif
              </td>
              <td data-lib="{{ __('stock.arrivals.product') }}"><b>{{ $a->product->name }}</b>@if ($a->notes)<br><small class="note">{{ $a->notes }}</small>@endif</td>
              <td data-lib="{{ __('stock.arrivals.supplier') }}">{{ $a->supplier?->name ?? '—' }}</td>
              <td data-lib="{{ __('stock.arrivals.qty') }}">{{ $a->qty ? \App\Support\Format::quantity($a->qty).' '.$a->product->stockUnit->short_label : '—' }}</td>
              <td class="droite">
                <form method="POST" action="{{ route('admin.arrivals.cancel', $a) }}">
                  @csrf @method('PATCH')
                  <button class="lien-btn lien-danger" type="submit">{{ __('stock.arrivals.cancel') }}<span class="sr"> {{ $a->product->name }}</span></button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </section>

  <form class="bloc carte" method="POST" action="{{ route('admin.arrivals.store') }}" aria-labelledby="t-annonce" novalidate>
    @csrf
    <h2 id="t-annonce">{{ __('stock.arrivals.new') }}</h2>
    <div class="grille-form">
      <div class="champ">
        <label for="product_id">{{ __('stock.arrivals.product') }}</label>
        <select id="product_id" name="product_id" required>
          <option value=""></option>
          @foreach ($products as $p)
            <option value="{{ $p->id }}" @selected((string) old('product_id') === (string) $p->id)>{{ $p->name }} ({{ $p->stockUnit->short_label }})</option>
          @endforeach
        </select>
        @error('product_id')<span class="erreur">{{ $message }}</span>@enderror
      </div>
      <div class="champ">
        <label for="supplier_id">{{ __('stock.arrivals.supplier') }}</label>
        <select id="supplier_id" name="supplier_id">
          <option value="">—</option>
          @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected((string) old('supplier_id') === (string) $s->id)>{{ $s->name }}</option>@endforeach
        </select>
      </div>
      <div class="champ">
        <label for="expected_on">{{ __('stock.arrivals.expected_on') }}</label>
        <input id="expected_on" name="expected_on" type="date" min="{{ today()->toDateString() }}" value="{{ old('expected_on') }}" required>
        @error('expected_on')<span class="erreur">{{ $message }}</span>@enderror
      </div>
      <div class="champ">
        <label for="qty">{{ __('stock.arrivals.qty') }}</label>
        <input id="qty" name="qty" inputmode="decimal" value="{{ old('qty') }}" placeholder="{{ __('stock.receipts.optional') }}">
        @error('qty')<span class="erreur">{{ $message }}</span>@enderror
      </div>
      <div class="champ plein">
        <label for="notes">{{ __('stock.arrivals.notes') }}</label>
        <input id="notes" name="notes" value="{{ old('notes') }}" maxlength="255">
      </div>
    </div>
    <div><button class="btn btn-primaire" type="submit">{{ __('stock.arrivals.add') }}</button></div>
  </form>

  @if ($recent->isNotEmpty())
    <section class="bloc carte" aria-labelledby="t-recents">
      <h2 id="t-recents">{{ __('stock.arrivals.status.received') }} / {{ __('stock.arrivals.status.cancelled') }}</h2>
      @foreach ($recent as $a)
        <p class="note">@date($a->expected_on) — {{ $a->product->name }} · {{ __('stock.arrivals.status.'.$a->status) }}</p>
      @endforeach
    </section>
  @endif
@endsection
