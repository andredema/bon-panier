@extends('layouts.admin')

@section('title', __('stock.receipts.show_title', ['number' => $receipt->number()]))

@section('content')
  <div class="barre">
    <div>
      <p class="fil"><a href="{{ route('admin.receipts.index') }}">{{ __('stock.tabs.journal') }}</a></p>
      <h1>{{ __('stock.receipts.show_title', ['number' => $receipt->number()]) }}</h1>
      <p>
        {{ __('stock.receipts.received_by', ['date' => \App\Support\Format::date($receipt->received_on), 'name' => $receipt->user?->name ?? '—']) }}
        @if ($receipt->supplier) · {{ $receipt->supplier->name }}@endif
        @if ($receipt->delivery_note_no) · {{ __('stock.receipts.delivery_note') }} {{ $receipt->delivery_note_no }}@endif
      </p>
    </div>
    <div class="actions">
      <a class="btn btn-secondaire" href="{{ route('admin.receipts.labels', $receipt) }}">{{ __('stock.receipts.labels') }}</a>
      <a class="btn btn-primaire" href="{{ route('admin.receipts.create') }}">+ {{ __('stock.tabs.new') }}</a>
    </div>
  </div>

  <section class="bloc carte" aria-labelledby="t-lots">
    <h2 id="t-lots">{{ __('stock.product.lots') }}</h2>
    <table class="tableau-cartes">
      <thead>
        <tr>
          <th scope="col">{{ __('stock.receipts.col_product') }}</th>
          <th scope="col">{{ __('stock.receipts.col_received') }}</th>
          <th scope="col" class="droite">{{ __('stock.receipts.col_converted') }}</th>
          <th scope="col" class="droite">{{ __('stock.receipts.col_remaining') }}</th>
          <th scope="col">{{ __('stock.receipts.col_lot') }}</th>
          <th scope="col">{{ __('stock.receipts.col_bb') }}</th>
          <th scope="col" class="droite">{{ __('stock.receipts.col_cost') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($receipt->lots as $lot)
          @php $u = $lot->product->stockUnit->short_label; @endphp
          <tr>
            <td data-lib="{{ __('stock.receipts.col_product') }}"><a href="{{ route('admin.products.edit', $lot->product) }}">{{ $lot->product->name }}</a></td>
            <td data-lib="{{ __('stock.receipts.col_received') }}">@qty($lot->received_qty) × {{ $lot->received_label }}</td>
            <td data-lib="{{ __('stock.receipts.col_converted') }}" class="num droite-large">@qty($lot->converted_qty) {{ $u }}</td>
            <td data-lib="{{ __('stock.receipts.col_remaining') }}" class="num droite-large">@qty($lot->remaining_qty) {{ $u }}</td>
            <td data-lib="{{ __('stock.receipts.col_lot') }}">{{ $lot->lot_number }}</td>
            <td data-lib="{{ __('stock.receipts.col_bb') }}">{{ $lot->best_before ? \App\Support\Format::date($lot->best_before) : '—' }}</td>
            <td data-lib="{{ __('stock.receipts.col_cost') }}" class="num droite-large">{{ $lot->cost_cents !== null ? \App\Support\Format::money($lot->cost_cents) : '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>
@endsection
