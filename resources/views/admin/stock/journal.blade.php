@extends('layouts.admin')

@section('title', __('stock.receipts.journal_title'))

@section('content')
  <div class="barre">
    <div><h1>{{ __('stock.receipts.journal_title') }}</h1><p>{{ __('stock.receipts.journal_intro') }}</p></div>
    <div class="actions"><a class="btn btn-primaire" href="{{ route('admin.receipts.create') }}">+ {{ __('stock.tabs.new') }}</a></div>
  </div>

  @include('admin.stock._tabs')

  <form class="filtres-liste filtres-dates carte" method="GET" action="{{ route('admin.receipts.index') }}">
    <div class="champ"><label for="du">{{ __('stock.receipts.from') }}</label><input id="du" name="du" type="date" value="{{ $from }}"></div>
    <div class="champ"><label for="au">{{ __('stock.receipts.to') }}</label><input id="au" name="au" type="date" value="{{ $to }}"></div>
    <button class="btn btn-secondaire" type="submit">{{ __('stock.receipts.filter') }}</button>
  </form>

  <section class="bloc carte" aria-labelledby="t-journal">
    <h2 id="t-journal" class="sr">{{ __('stock.receipts.journal_title') }}</h2>
    @if ($receipts->isEmpty())
      <p class="note">{{ __('stock.receipts.journal_empty') }}</p>
    @else
      <table class="tableau-cartes">
        <thead>
          <tr>
            <th scope="col">{{ __('stock.receipts.col_number') }}</th>
            <th scope="col">{{ __('stock.receipts.col_date') }}</th>
            <th scope="col">{{ __('stock.receipts.col_supplier') }}</th>
            <th scope="col" class="droite">{{ __('stock.receipts.col_lines') }}</th>
            <th scope="col" class="droite">{{ __('stock.receipts.col_cost') }}</th>
            <th scope="col">{{ __('stock.receipts.col_user') }}</th>
            <th scope="col"><span class="sr">{{ __('stock.receipts.view') }}</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($receipts as $r)
            <tr>
              <td data-lib="{{ __('stock.receipts.col_number') }}"><b>{{ $r->number() }}</b>@if ($r->delivery_note_no)<br><small class="note">{{ $r->delivery_note_no }}</small>@endif</td>
              <td data-lib="{{ __('stock.receipts.col_date') }}">@date($r->received_on)</td>
              <td data-lib="{{ __('stock.receipts.col_supplier') }}">{{ $r->supplier?->name ?? '—' }}</td>
              <td data-lib="{{ __('stock.receipts.col_lines') }}" class="num droite-large">{{ $r->lots_count }}</td>
              <td data-lib="{{ __('stock.receipts.col_cost') }}" class="num droite-large">{{ $r->lots_sum_cost_cents !== null ? \App\Support\Format::money((int) $r->lots_sum_cost_cents) : '—' }}</td>
              <td data-lib="{{ __('stock.receipts.col_user') }}">{{ $r->user?->name ?? '—' }}</td>
              <td class="droite"><a class="btn btn-secondaire mini" href="{{ route('admin.receipts.show', $r) }}">{{ __('stock.receipts.view') }}<span class="sr"> {{ $r->number() }}</span></a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
      {{ $receipts->links('admin.partials.pagination') }}
    @endif
  </section>
@endsection
