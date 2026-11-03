@extends('layouts.admin')

@php use App\Support\Format; @endphp

@section('title', __('billing.credit.title', ['number' => $invoice->number]))

@section('content')
  <div class="barre">
    <div>
      <p class="fil"><a href="{{ route('admin.invoices.show', $invoice) }}">{{ __('billing.show.title', ['number' => $invoice->number]) }}</a></p>
      <h1>{{ __('billing.credit.title', ['number' => $invoice->number]) }}</h1>
      <p>{{ __('billing.credit.intro') }}</p>
    </div>
  </div>

  @if ($errors->any())
    <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
  @endif

  <form class="bloc carte" method="POST" action="{{ route('admin.invoices.credit.store', $invoice) }}" novalidate
        x-data="{ toutCrediter() { this.$root.querySelectorAll('[data-max]').forEach(i => i.value = i.dataset.max); const d = this.$root.querySelector('#delivery'); if (d) d.checked = true; } }">
    @csrf
    <table class="tableau-cartes">
      <thead><tr>
        <th scope="col">{{ __('billing.credit.line') }}</th>
        <th scope="col" class="droite">{{ __('billing.credit.invoiced') }}</th>
        <th scope="col" class="droite">{{ __('billing.credit.creditable') }}</th>
        <th scope="col">{{ __('billing.credit.amount') }}</th>
      </tr></thead>
      <tbody>
        @foreach ($invoice->lines as $l)
          @php $max = $l->creditableCents(); @endphp
          <tr>
            <td data-lib="{{ __('billing.credit.line') }}"><span><b>{{ $l->product_name }}</b><small>{{ $l->format_label }} · {{ $l->is_taxable ? 'T' : 'D' }}</small></span></td>
            <td data-lib="{{ __('billing.credit.invoiced') }}" class="num droite-large">@money($l->line_total_cents)</td>
            <td data-lib="{{ __('billing.credit.creditable') }}" class="num droite-large">@money($max)</td>
            <td data-lib="{{ __('billing.credit.amount') }}">
              @if ($max > 0)
                <label class="sr" for="a-{{ $l->id }}">{{ __('billing.credit.amount') }} — {{ $l->product_name }}</label>
                <input class="saisie-montant" id="a-{{ $l->id }}" name="amounts[{{ $l->id }}]" inputmode="decimal" value="{{ old('amounts.'.$l->id) }}" placeholder="0,00" data-max="{{ number_format($max / 100, 2, ',', '') }}">
                @error('amounts.'.$l->id)<span class="erreur">{{ $message }}</span>@enderror
              @else — @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>

    @if ($deliveryLeft > 0)
      <label class="check"><input type="checkbox" id="delivery" name="delivery" value="1" @checked(old('delivery'))> {{ __('billing.credit.delivery', ['amount' => Format::money($deliveryLeft)]) }}</label>
    @endif
    <button class="lien-btn" type="button" @click="toutCrediter()">{{ __('billing.credit.all') }}</button>

    <div class="champ">
      <label for="reason">{{ __('billing.credit.reason') }}</label>
      <input id="reason" name="reason" value="{{ old('reason') }}" maxlength="255" required>
      @error('reason')<span class="erreur">{{ $message }}</span>@enderror
    </div>
    <div><button class="btn btn-primaire" type="submit">{{ __('billing.credit.save') }}</button></div>
  </form>
@endsection
