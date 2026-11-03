@extends('layouts.admin')

@php
    use App\Support\Format;
    use App\Support\Quantity;
    $q = fn (int $milli) => Format::quantity(Quantity::fromMilli($milli));
@endphp

@section('title', __('preorders.title'))

@section('content')
  <div class="barre">
    <div><h1>{{ __('preorders.title') }}</h1><p>{{ __('preorders.intro') }}</p></div>
    <div class="actions"><a class="btn btn-primaire" href="{{ route('admin.receipts.create') }}">{{ __('stock.tabs.new') }}</a></div>
  </div>

  @include('admin.stock._tabs')

  <section class="bloc carte" aria-labelledby="t-attente">
    <h2 id="t-attente">{{ __('preorders.pending') }}</h2>
    @forelse ($groups as $g)
      @php $u = $g['product']->stockUnit->short_label; @endphp
      <article class="groupe-preco" aria-labelledby="p-{{ $g['product']->id }}">
        <div class="bloc-tete">
          <h3 id="p-{{ $g['product']->id }}">{{ $g['product']->name }}</h3>
          <span class="etiquette e-preco">{{ trans_choice('preorders.receipt.pending', $g['items']->count(), ['qty' => $q($g['pending_milli']), 'unit' => $u]) }}</span>
        </div>
        <dl class="chiffres-preco">
          <div><dt>{{ __('preorders.col_available') }}</dt><dd class="num">{{ $q($g['available_milli']) }} {{ $u }}</dd></div>
          <div><dt>{{ __('preorders.col_arrival') }}</dt><dd>{{ $g['next_arrival'] ? Format::date($g['next_arrival']) : __('preorders.no_arrival') }}</dd></div>
        </dl>
        <details>
          <summary>{{ __('preorders.customers') }}</summary>
          <ol class="file-preco">
            @foreach ($g['items'] as $item)
              <li>
                {{ __('preorders.line', ['number' => $item->order->number, 'name' => $item->order->customer->name, 'qty' => $q(Quantity::toMilli($item->stock_qty)), 'unit' => $u]) }}
                <small class="note">· {{ __('preorders.since', ['date' => Format::date($item->order->created_at)]) }}</small>
              </li>
            @endforeach
          </ol>
        </details>
        @if ($g['servable'])
          <form method="POST" action="{{ route('admin.preorders.serve', $g['product']) }}" class="servir">
            @csrf
            <p class="note">{{ __('preorders.serve_hint') }}</p>
            <label class="check"><input type="checkbox" name="notify" value="1" checked> {{ __('preorders.serve_notify') }}</label>
            <button class="btn btn-primaire mini" type="submit">{{ __('preorders.serve') }}<span class="sr"> — {{ $g['product']->name }}</span></button>
          </form>
        @endif
      </article>
    @empty
      <p class="note">{{ __('preorders.empty') }}</p>
    @endforelse
  </section>

  <section class="bloc carte" aria-labelledby="t-recent">
    <h2 id="t-recent">{{ __('preorders.recent') }}</h2>
    @forelse ($recent as $item)
      <p class="note">
        <b>{{ $item->product_name }}</b> — {{ $item->order->number }} · {{ $item->order->customer->name }}
        · {{ __('preorders.served_on', ['date' => Format::date($item->preorder_served_at)]) }}
        · @if ($item->servedReceipt)<a href="{{ route('admin.receipts.show', $item->servedReceipt) }}">{{ __('preorders.by_receipt', ['number' => $item->servedReceipt->number()]) }}</a>@else{{ __('preorders.by_hand') }}@endif
        · {{ $item->preorder_notified_at ? __('preorders.notified') : __('preorders.not_notified') }}
      </p>
    @empty
      <p class="note">{{ __('preorders.recent_empty') }}</p>
    @endforelse
  </section>
@endsection
