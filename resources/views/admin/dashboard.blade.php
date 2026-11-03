@extends('layouts.admin')

@php
    use App\Support\Format;
    use App\Support\Quantity;
    $q = fn (int $milli) => Format::quantity(Quantity::fromMilli($milli));

    // Graphique des ventes : barres SVG, échelle arrondie à la centaine de dollars supérieure
    $max = max(100_00, (int) ceil(max(array_column($sales, 'cents')) / 500_00) * 500_00);
    $H = 170; $base = 200; $bw = 52; $step = 84; $x0 = 64;
    $chartLabel = __('orders.dashboard.sales_chart', [
        'from' => Format::date($sales[0]['date']), 'to' => Format::date($sales[6]['date']),
        'min' => Format::money(min(array_column($sales, 'cents'))), 'max' => Format::money(max(array_column($sales, 'cents'))),
    ]);

    // Attributs de chaque commande pour les filtres côté navigateur
    $rows = $orders->mapWithKeys(fn ($o) => [$o->id => [
        's' => $o->status->value, 'canal' => $o->channel->value, 'mode' => $o->fulfilment,
        'jour' => $o->slot_start->isToday() ? 'auj' : ($o->slot_start->isTomorrow() ? 'demain' : 'plus'),
        'preco' => (bool) $o->items_pending_count,
    ]]);
@endphp

@section('title', __('app.nav.dashboard'))

@section('content')
  <div class="barre">
    <div>
      <h1>{{ __('orders.dashboard.greeting') }} {{ auth()->user()->name }}</h1>
      <p>{{ __('orders.dashboard.intro', ['date' => ucfirst(Format::longDate(now()))]) }}</p>
    </div>
    <div class="actions">
      <a class="btn btn-secondaire" href="{{ route('admin.orders.create') }}">{{ __('orders.new_counter') }}</a>
      <a class="btn btn-primaire" href="{{ route('admin.receipts.create') }}">{{ __('stock.receipts.title') }}</a>
    </div>
  </div>

  <section class="kpis" aria-label="{{ __('orders.dashboard.kpis') }}">
    <a class="kpi carte" href="{{ route('admin.orders.index', ['statut' => 'remise']) }}">
      <span class="lib">{{ __('orders.dashboard.sales_today') }}</span>
      <span class="val num">@money($kpi['sales'])</span>
      <span class="det">{{ __('orders.dashboard.sales_split', ['retail' => Format::money($kpi['sales_retail']), 'wholesale' => Format::money($kpi['sales_wholesale'])]) }}</span>
    </a>
    <a class="kpi carte" href="{{ route('admin.orders.index') }}">
      <span class="lib">{{ __('orders.dashboard.to_process') }}</span>
      <span class="val num">{{ $kpi['received'] + $kpi['confirmed'] + $kpi['preparing'] }}</span>
      <span class="det">{{ __('orders.dashboard.to_process_split', ['received' => $kpi['received'], 'confirmed' => $kpi['confirmed'], 'preparing' => $kpi['preparing']]) }}</span>
    </a>
    <a class="kpi carte accent" href="{{ route('admin.preorders.index') }}">
      <span class="lib">{{ __('orders.dashboard.preorders') }}</span>
      <span class="val num">{{ $kpi['preorders'] }}</span>
      <span class="det">
        {{ trans_choice('orders.dashboard.preorders_split', $kpi['preorder_products'], ['products' => $kpi['preorder_products']]) }}
        @if ($kpi['preorder_dates']->isNotEmpty()) · {{ __('orders.dashboard.arrivals_on', ['dates' => $kpi['preorder_dates']->map(fn ($d) => Format::shortDate($d))->join(', ')]) }}@endif
      </span>
    </a>
    <a class="kpi carte" href="{{ route('admin.payments.reconcile') }}">
      <span class="lib">{{ __('orders.dashboard.interac') }}</span>
      <span class="val num">@money($kpi['interac_total'])</span>
      <span class="det">{{ trans_choice('orders.dashboard.interac_split', $kpi['interac_count'], ['count' => $kpi['interac_count']]) }}</span>
    </a>
  </section>

  <div class="rangee">
    {{-- Commandes : sélecteur de statut et filtres regroupés, comme la maquette --}}
    <section class="bloc carte" id="commandes" aria-labelledby="t-cmd"
             x-data="{ s: 'tout', canal: [], mode: [], jour: [], preco: false, ouvert: false, rows: @js($rows->values()),
                       get aucune() { return !this.rows.some((r) => this.voir(r)) },
                       voir(r) { return (this.s === 'tout' || r.s === this.s) && (!this.canal.length || this.canal.includes(r.canal)) && (!this.mode.length || this.mode.includes(r.mode)) && (!this.jour.length || this.jour.includes(r.jour)) && (!this.preco || r.preco) },
                       get nb() { return this.canal.length + this.mode.length + this.jour.length + (this.preco ? 1 : 0) } }">
      <div class="bloc-tete"><h2 id="t-cmd">{{ __('orders.dashboard.orders') }}</h2><a href="{{ route('admin.orders.index') }}">{{ __('orders.all') }}</a></div>
      <div class="outils-cmd">
        <div class="segment" role="group" aria-label="{{ __('orders.cols.status') }}">
          <button type="button" @click="s = 'tout'" :aria-pressed="(s === 'tout').toString()">{{ __('orders.segments.toutes') }}<span class="n">{{ $orders->count() }}</span></button>
          @foreach (['recue', 'confirmee', 'en_preparation', 'prete'] as $st)
            <button type="button" @click="s = '{{ $st }}'" :aria-pressed="(s === '{{ $st }}').toString()">{{ __('orders.segments.'.$st) }}<span class="n">{{ $orders->where('status.value', $st)->count() }}</span></button>
          @endforeach
        </div>
        <div class="pop" :class="{ ouvert }" @click.outside="ouvert = false" @keydown.escape="ouvert = false">
          <button class="btn btn-secondaire mini" type="button" @click="ouvert = !ouvert" :aria-expanded="ouvert.toString()" aria-controls="panneau">{{ __('orders.filters.button') }} <span x-show="nb" x-text="`(${nb})`"></span></button>
          <div class="panneau carte" id="panneau">
            <fieldset><legend>{{ __('orders.filters.channel') }}</legend><div class="ligne-opt">
              <label><input type="checkbox" value="detail" x-model="canal"> {{ __('orders.filters.retail') }}</label>
              <label><input type="checkbox" value="gros" x-model="canal"> {{ __('orders.filters.wholesale') }}</label>
            </div></fieldset>
            <fieldset><legend>{{ __('orders.filters.reception') }}</legend><div class="ligne-opt">
              <label><input type="checkbox" value="ramassage" x-model="mode"> {{ __('orders.filters.pickup') }}</label>
              <label><input type="checkbox" value="livraison" x-model="mode"> {{ __('orders.filters.delivery') }}</label>
            </div></fieldset>
            <fieldset><legend>{{ __('orders.filters.slot') }}</legend><div class="ligne-opt">
              <label><input type="checkbox" value="auj" x-model="jour"> {{ __('orders.filters.today') }}</label>
              <label><input type="checkbox" value="demain" x-model="jour"> {{ __('orders.filters.tomorrow') }}</label>
            </div></fieldset>
            <fieldset><legend>{{ __('orders.filters.content') }}</legend><div class="ligne-opt">
              <label><input type="checkbox" x-model="preco"> {{ __('orders.filters.with_preorder') }}</label>
            </div></fieldset>
            <div class="pied-p">
              <button class="lien-btn" type="button" @click="canal = []; mode = []; jour = []; preco = false">{{ __('orders.filters.clear') }}</button>
              <button class="btn btn-primaire mini" type="button" @click="ouvert = false">{{ __('orders.filters.apply') }}</button>
            </div>
          </div>
        </div>
      </div>

      @if ($orders->isEmpty())
        <p class="note">{{ __('orders.dashboard.none_active') }}</p>
      @else
        <table class="tableau-cartes">
          <thead><tr>
            @foreach (['number', 'customer', 'slot'] as $c)<th scope="col">{{ __('orders.cols.'.$c) }}</th>@endforeach
            <th scope="col" class="droite">{{ __('orders.cols.total') }}</th>
            <th scope="col">{{ __('orders.cols.status') }}</th>
            <th scope="col"><span class="sr">{{ __('orders.cols.action') }}</span></th>
          </tr></thead>
          @foreach ($orders as $order)
            <tbody x-show="voir(@js($rows[$order->id]))">@include('admin.orders._row', ['compact' => true])</tbody>
          @endforeach
        </table>
        <p class="note" x-show="aucune" x-cloak>{{ __('orders.empty') }}</p>
      @endif
    </section>

    <section class="bloc carte" id="stock" aria-labelledby="t-stock">
      <div class="bloc-tete"><h2 id="t-stock">{{ __('orders.dashboard.stock') }}</h2><a class="btn btn-primaire mini" href="{{ route('admin.receipts.create') }}">{{ __('stock.receipts.title') }}</a></div>
      <div>
        @forelse ($stock as $line)
          @php $u = $line['product']->stockUnit->short_label; @endphp
          <div class="stock-ligne">
            @if ($line['type'] === 'preorder')
              <div><b>{{ $line['product']->name }}</b><br><small>{{ trans_choice('orders.dashboard.stock_preorder', $line['count'], [
                  'qty' => $q($line['available']), 'unit' => $u, 'count' => $line['count'], 'pqty' => $q($line['qty']),
                  'arrival' => $line['arrival'] ? __('orders.dashboard.arrival', ['date' => Format::shortDate($line['arrival'])]) : __('orders.dashboard.no_arrival')]) }}</small></div>
              <a class="etiquette e-preco" href="{{ route('admin.preorders.index') }}">{{ __('orders.preorder_tag') }}</a>
            @else
              <div><b>{{ $line['product']->name }}</b><br><small>{{ __('orders.dashboard.stock_low', ['qty' => $q($line['available']), 'unit' => $u, 'threshold' => $q($line['threshold'])]) }}</small></div>
              <a class="btn btn-secondaire mini" href="{{ route('admin.receipts.create', ['produit' => $line['product']->id]) }}">{{ __('orders.dashboard.restock') }}<span class="sr"> {{ $line['product']->name }}</span></a>
              <div class="jauge" aria-hidden="true"><i style="width:{{ min(100, (int) round($line['ratio'] * 100)) }}%"></i></div>
            @endif
          </div>
        @empty
          <p class="note">{{ __('orders.dashboard.stock_ok') }}</p>
        @endforelse
      </div>
      <p class="note">{{ __('orders.dashboard.stock_note') }}</p>
    </section>
  </div>

  <div class="rangee">
    <section class="bloc carte graph" aria-labelledby="t-ventes">
      <div class="bloc-tete"><h2 id="t-ventes">{{ __('orders.dashboard.sales_week') }}</h2><span class="note">{{ __('orders.dashboard.sales_week_note') }}</span></div>
      <svg viewBox="0 0 640 240" role="img" aria-label="{{ $chartLabel }}">
        @foreach ([0, 0.25, 0.5, 0.75, 1] as $f)
          @php $y = $base - $f * $H; @endphp
          <line x1="{{ $x0 - 12 }}" x2="620" y1="{{ $y }}" y2="{{ $y }}" stroke="#ECE5D8"/>
          <text x="{{ $x0 - 18 }}" y="{{ $y + 4 }}" text-anchor="end" font-size="12" fill="#6B5F4F">{{ number_format($max * $f / 100, 0, ',', ' ') }} $</text>
        @endforeach
        @foreach ($sales as $i => $d)
          @php $h = $d['cents'] / $max * $H; $x = $x0 + $i * $step; $auj = $i === 6; @endphp
          <rect x="{{ $x }}" y="{{ $base - $h }}" width="{{ $bw }}" height="{{ $h }}" rx="6" fill="{{ $auj ? '#D49A3A' : '#1F4D2E' }}"/>
          <text x="{{ $x + $bw / 2 }}" y="{{ $base - $h - 8 }}" text-anchor="middle" font-size="12" font-weight="700" fill="#1E1B16">{{ number_format($d['cents'] / 100, 0, ',', ' ') }}</text>
          <text x="{{ $x + $bw / 2 }}" y="{{ $base + 20 }}" text-anchor="middle" font-size="12" fill="#4A4033" font-weight="{{ $auj ? 800 : 500 }}">{{ $auj ? __('orders.dashboard.today') : $d['date']->locale('fr_CA')->translatedFormat('D j') }}</text>
        @endforeach
      </svg>
    </section>

    <section class="bloc carte" aria-labelledby="t-arr">
      <div class="bloc-tete"><h2 id="t-arr">{{ __('orders.dashboard.arrivals') }}</h2><a href="{{ route('admin.arrivals.index') }}">{{ __('stock.tabs.arrivals') }}</a></div>
      <div>
        @forelse ($arrivals as $group)
          @php $first = $group->first(); @endphp
          <div class="arrivage">
            <div class="date"><b>{{ $first->expected_on->day }}</b><span>{{ rtrim(explode(' ', Format::shortDate($first->expected_on))[1], '.') }}</span></div>
            <div>
              <p><b>{{ $first->supplier?->name ?? '—' }}</b></p>
              <small>{{ $group->map(fn ($a) => $a->product->name.($a->qty ? ' '.Format::quantity($a->qty).' '.$a->product->stockUnit->short_label : ''))->join(' · ') }}</small>
            </div>
            <a class="btn btn-secondaire mini" href="{{ route('admin.receipts.create', ['produit' => $first->product_id]) }}">{{ __('orders.dashboard.receive') }}</a>
          </div>
        @empty
          <p class="note">{{ __('orders.dashboard.arrivals_none') }}</p>
        @endforelse
      </div>
    </section>
  </div>
@endsection
