@extends('layouts.admin')

@php
    use App\Support\Format;
    $q = fn (array $extra = []) => array_merge($query, $extra);
@endphp

@section('title', __('reports.title'))

@section('content')
  <div class="barre">
    <div>
      <h1>{{ __('reports.monthly', ['period' => mb_strtolower($period->label)]) }}</h1>
      <p>{{ __('reports.subtitle', ['shop' => \App\Support\Settings::get('shop.name'), 'span' => $period->span()]) }}</p>
    </div>
    <form class="outils-periode" method="GET" action="{{ route('admin.reports.index') }}">
      <div class="champ">
        <label for="periode">{{ __('reports.period.label') }}</label>
        <select id="periode" name="periode" onchange="this.form.du.value = ''; this.form.au.value = ''; this.form.submit()">
          <optgroup label="{{ __('reports.period.months') }}">
            @foreach ($options['months'] as $k => $l)<option value="{{ $k }}" @selected($period->key === $k)>{{ $l }}</option>@endforeach
          </optgroup>
          <optgroup label="{{ __('reports.period.quarters') }}">
            @foreach ($options['quarters'] as $k => $l)<option value="{{ $k }}" @selected($period->key === $k)>{{ $l }}</option>@endforeach
          </optgroup>
          @if ($period->key === 'libre')<option value="libre" selected>{{ __('reports.period.custom') }}</option>@endif
        </select>
      </div>
      <details class="dates-libres" @if ($period->key === 'libre') open @endif>
        <summary>{{ __('reports.period.custom') }}</summary>
        <div class="deux-champs">
          <div class="champ"><label for="du">{{ __('reports.period.from') }}</label><input id="du" name="du" type="date" value="{{ request('du') }}"></div>
          <div class="champ"><label for="au">{{ __('reports.period.to') }}</label><input id="au" name="au" type="date" value="{{ request('au') }}"></div>
        </div>
        <button class="btn btn-secondaire mini" type="submit">{{ __('reports.period.show') }}</button>
      </details>
    </form>
  </div>

  @if ($errors->any())
    <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
  @endif

  <section class="kpis" aria-label="{{ __('reports.kpi.label') }}">
    <div class="kpi carte"><span class="lib">{{ __('reports.kpi.sales') }}</span><span class="val num">@money($summary['sales'])</span><span class="det">{{ trans_choice('reports.kpi.invoices', $summary['count']) }}</span></div>
    <div class="kpi carte"><span class="lib">{{ __('reports.kpi.average') }}</span><span class="val num">@money($summary['average'])</span><span class="det">{{ __('reports.kpi.before_tax') }}</span></div>
    <div class="kpi carte"><span class="lib">{{ __('reports.kpi.taxes') }}</span><span class="val num">@money($summary['tps'] + $summary['tvq'])</span><span class="det">{{ __('reports.kpi.taxes_split', ['tps' => Format::money($summary['tps']), 'tvq' => Format::money($summary['tvq'])]) }}</span></div>
    <div class="kpi carte"><span class="lib">{{ __('reports.kpi.collected') }}</span><span class="val num">@money($summary['collected'])</span><span class="det">{{ __('reports.kpi.collected_note') }}</span></div>
  </section>

  <div class="grille-rapport">
    <section class="bloc carte" aria-labelledby="t-tx">
      <div class="bloc-tete"><h2 id="t-tx">{{ __('reports.taxes.title') }}</h2></div>
      <p class="note">{{ __('reports.taxes.note') }}</p>
      <table class="num tableau-rapport">
        <tbody>
          <tr><td>{{ __('reports.taxes.zero_rated') }}</td><td class="d">@money($taxes['zero_rated'])</td></tr>
          <tr><td>{{ __('reports.taxes.taxable') }}</td><td class="d">@money($taxes['taxable'])</td></tr>
          <tr><td>{{ __('reports.taxes.credits') }}</td><td class="d">@money(-$taxes['credits'])</td></tr>
          <tr><td>{{ __('reports.taxes.tps') }}</td><td class="d">@money($taxes['tps'])</td></tr>
          <tr><td>{{ __('reports.taxes.tvq') }}</td><td class="d">@money($taxes['tvq'])</td></tr>
        </tbody>
        <tfoot><tr><td>{{ __('reports.taxes.total') }}</td><td class="d">@money($taxes['total'])</td></tr></tfoot>
      </table>
    </section>

    <section class="bloc carte" aria-labelledby="t-pay">
      <div class="bloc-tete"><h2 id="t-pay">{{ __('reports.payments.title') }}</h2></div>
      @if ($payments)
        @include('admin.reports._bars', ['rows' => collect($payments)->map(fn ($c, $m) => [__('billing.methods.'.$m), $c, $m !== 'interac'])->values()->all()])
      @else
        <p class="note">{{ __('reports.payments.none') }}</p>
      @endif
    </section>

    <section class="bloc carte" aria-labelledby="t-canal">
      <div class="bloc-tete"><h2 id="t-canal">{{ __('reports.channels.title') }}</h2><span class="note">{{ __('reports.channels.note') }}</span></div>
      @include('admin.reports._bars', ['rows' => [[__('reports.channels.detail'), $channels['detail'], false], [__('reports.channels.gros'), $channels['gros'], true]]])
    </section>

    <section class="bloc carte" aria-labelledby="t-cat">
      <div class="bloc-tete"><h2 id="t-cat">{{ __('reports.categories.title') }}</h2><span class="note">{{ __('reports.categories.note') }}</span></div>
      @if ($categories)
        @include('admin.reports._bars', ['rows' => array_map(fn ($c) => [$c['name'], $c['cents'], $c['taxable']], $categories)])
      @else
        <p class="note">{{ __('reports.none') }}</p>
      @endif
    </section>

    <section class="bloc carte" aria-labelledby="t-top">
      <div class="bloc-tete"><h2 id="t-top">{{ __('reports.top.title') }}</h2></div>
      @if ($top)
        <table class="tableau-rapport">
          <thead><tr><th scope="col">{{ __('reports.top.product') }}</th><th scope="col" class="d">{{ __('reports.top.sales') }}</th></tr></thead>
          <tbody class="num">@foreach ($top as $t)<tr><td>{{ $t['name'] }}</td><td class="d">@money($t['cents'])</td></tr>@endforeach</tbody>
        </table>
      @else
        <p class="note">{{ __('reports.none') }}</p>
      @endif
    </section>

    <section class="bloc carte" aria-labelledby="t-cc">
      <div class="bloc-tete"><h2 id="t-cc">{{ __('reports.receivables.title') }}</h2><span class="num"><b>@money($receivables['total'])</b></span></div>
      <p class="note">{{ __('reports.receivables.note') }}</p>
      @if ($receivables['rows'])
        <table class="tableau-rapport">
          <thead><tr><th scope="col">{{ __('reports.receivables.invoice') }}</th><th scope="col">{{ __('reports.receivables.customer') }}</th><th scope="col" class="d">{{ __('reports.receivables.balance') }}</th><th scope="col">{{ __('reports.receivables.age') }}</th></tr></thead>
          <tbody>
            @foreach (array_slice($receivables['rows'], 0, 10) as $r)
              <tr>
                <td><a href="{{ route('admin.invoices.show', $r['invoice']) }}">{{ $r['invoice']->number }}</a></td>
                <td>{{ $r['invoice']->customer_snapshot['name'] }}</td>
                <td class="d num">@money($r['balance'])</td>
                <td><span class="age age-{{ ['0-30' => 1, '31-60' => 2, '60+' => 3][$r['bucket']] }}">{{ __('reports.receivables.buckets.'.$r['bucket']) }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <p class="note">{{ __('reports.receivables.none') }}</p>
      @endif
    </section>

    <section class="bloc carte" aria-labelledby="t-inv">
      <div class="bloc-tete"><h2 id="t-inv">{{ __('reports.inventory.title', ['date' => Format::date(today())]) }}</h2><span class="num"><b>@money($inventory['total'])</b></span></div>
      <p class="note">{{ __('reports.inventory.note') }}
        @if ($inventory['without_cost']) {{ trans_choice('reports.inventory.without_cost', $inventory['without_cost']) }}@endif</p>
      <p class="note">{{ __('reports.inventory.soon') }}</p>
      @if ($inventory['soon']->isNotEmpty())
        <table class="tableau-rapport">
          <thead><tr><th scope="col">{{ __('reports.inventory.product') }}</th><th scope="col">{{ __('reports.inventory.lot') }}</th><th scope="col" class="d">{{ __('reports.inventory.qty') }}</th><th scope="col">{{ __('reports.inventory.best_before') }}</th></tr></thead>
          <tbody class="num">
            @foreach ($inventory['soon']->take(10) as $l)
              <tr><td>{{ $l->product->name }}</td><td>{{ $l->lot_number }}</td><td class="d">@qty($l->remaining_qty) {{ $l->product->stockUnit->short_label }}</td><td>{{ Format::date($l->best_before) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      @else
        <p class="note">{{ __('reports.inventory.none_soon') }}</p>
      @endif
    </section>

    <section class="bloc carte" aria-labelledby="t-exp">
      <div class="bloc-tete"><h2 id="t-exp">{{ __('reports.exports.title') }}</h2></div>
      <p class="note">{{ __('reports.exports.note') }}</p>
      <div class="exports">
        @foreach (['ventes' => 'sales_csv', 'taxes' => 'taxes_csv', 'inventaire' => 'inventory_csv', 'entrees' => 'receipts_csv', 'ajustements' => 'adjustments_csv', 'comptes-clients' => 'receivables_csv'] as $type => $key)
          <a class="btn btn-secondaire mini" href="{{ route('admin.reports.csv', $q(['type' => $type])) }}">{{ __('reports.exports.'.$key) }}</a>
        @endforeach
        @foreach (['mensuel' => 'monthly_pdf', 'taxes' => 'taxes_pdf', 'comptes-clients' => 'receivables_pdf', 'inventaire' => 'inventory_pdf'] as $type => $key)
          <a class="btn btn-secondaire mini" href="{{ route('admin.reports.pdf', $q(['type' => $type])) }}" target="_blank" rel="noopener">{{ __('reports.exports.'.$key) }}</a>
        @endforeach
      </div>
      <form method="POST" action="{{ route('admin.reports.send', $query) }}">
        @csrf
        <button class="btn btn-primaire" type="submit" @disabled(! $accountant)>{{ __('reports.exports.send') }}</button>
        @unless ($accountant)<p class="note">{{ __('reports.exports.no_accountant') }}</p>@endunless
      </form>
      <p class="note">{{ __('reports.exports.quickbooks') }}</p>
    </section>

    <section class="bloc carte plein" aria-labelledby="t-jr">
      <div class="bloc-tete"><h2 id="t-jr">{{ __('reports.journal.title') }}</h2><span class="note">{{ trans_choice('reports.journal.note', $journalCount) }}</span></div>
      @if ($journal->isEmpty() && $credits->isEmpty())
        <p class="note">{{ __('reports.none') }}</p>
      @else
        <table class="tableau-cartes">
          <thead><tr>
            <th scope="col">{{ __('reports.journal.date') }}</th><th scope="col">{{ __('reports.journal.number') }}</th><th scope="col">{{ __('reports.journal.customer') }}</th>
            <th scope="col" class="droite">{{ __('reports.journal.before_tax') }}</th><th scope="col" class="droite">{{ __('reports.journal.tps') }}</th>
            <th scope="col" class="droite">{{ __('reports.journal.tvq') }}</th><th scope="col" class="droite">{{ __('reports.journal.total') }}</th><th scope="col">{{ __('reports.journal.payment') }}</th>
          </tr></thead>
          <tbody>
            @foreach ($journal as $i)
              @php $paid = $i->payments->sum('amount_cents'); @endphp
              <tr>
                <td data-lib="{{ __('reports.journal.date') }}">{{ Format::shortDate($i->issued_at) }}</td>
                <td data-lib="{{ __('reports.journal.number') }}"><a href="{{ route('admin.invoices.show', $i) }}">{{ $i->number }}</a></td>
                <td data-lib="{{ __('reports.journal.customer') }}">{{ $i->customer_snapshot['name'] }}</td>
                <td data-lib="{{ __('reports.journal.before_tax') }}" class="num droite-large">@money($i->subtotal_cents + $i->delivery_fee_cents)</td>
                <td data-lib="{{ __('reports.journal.tps') }}" class="num droite-large">@money($i->tax_tps_cents)</td>
                <td data-lib="{{ __('reports.journal.tvq') }}" class="num droite-large">@money($i->tax_tvq_cents)</td>
                <td data-lib="{{ __('reports.journal.total') }}" class="num droite-large">@money($i->total_cents)</td>
                <td data-lib="{{ __('reports.journal.payment') }}">
                  <span class="{{ $paid >= $i->total_cents ? 'pay-ok' : 'pay-non' }}">{{ $paid > 0 ? $i->payments->pluck('method')->unique()->map(fn ($m) => __('billing.methods.'.$m))->join(', ') : __('reports.journal.unpaid') }}</span>
                </td>
              </tr>
            @endforeach
            @foreach ($credits as $n)
              <tr>
                <td data-lib="{{ __('reports.journal.date') }}">{{ Format::shortDate($n->issued_at) }}</td>
                <td data-lib="{{ __('reports.journal.number') }}"><a href="{{ route('admin.credit-notes.pdf', $n) }}" target="_blank" rel="noopener">{{ $n->number }}</a></td>
                <td data-lib="{{ __('reports.journal.customer') }}">{{ __('reports.journal.credit_of', ['number' => $n->invoice->number]) }}</td>
                <td data-lib="{{ __('reports.journal.before_tax') }}" class="num droite-large">@money(-$n->subtotal_cents)</td>
                <td data-lib="{{ __('reports.journal.tps') }}" class="num droite-large">@money(-$n->tax_tps_cents)</td>
                <td data-lib="{{ __('reports.journal.tvq') }}" class="num droite-large">@money(-$n->tax_tvq_cents)</td>
                <td data-lib="{{ __('reports.journal.total') }}" class="num droite-large">@money(-$n->total_cents)</td>
                <td data-lib="{{ __('reports.journal.payment') }}">—</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        @if ($journalCount > $limit)<p class="note">{{ __('reports.journal.more', ['count' => $limit]) }}</p>@endif
      @endif
    </section>

    @if ($adjustments->isNotEmpty())
      <section class="bloc carte plein" aria-labelledby="t-adj">
        <h2 id="t-adj">{{ __('reports.adjust.journal') }}</h2>
        <table class="tableau-cartes">
          <thead><tr>@foreach (['date', 'product', 'lot', 'delta', 'value', 'reason', 'user'] as $c)<th scope="col">{{ __('reports.adjust.cols.'.$c) }}</th>@endforeach</tr></thead>
          <tbody>
            @foreach ($adjustments as $a)
              <tr>
                <td data-lib="{{ __('reports.adjust.cols.date') }}">{{ Format::shortDate($a->created_at) }}</td>
                <td data-lib="{{ __('reports.adjust.cols.product') }}">{{ $a->product->name }}</td>
                <td data-lib="{{ __('reports.adjust.cols.lot') }}">{{ $a->lot->lot_number }}</td>
                <td data-lib="{{ __('reports.adjust.cols.delta') }}" class="num">@qty($a->delta) {{ $a->product->stockUnit->short_label }}</td>
                <td data-lib="{{ __('reports.adjust.cols.value') }}" class="num">{{ $a->value_cents !== null ? Format::money($a->value_cents) : '—' }}</td>
                <td data-lib="{{ __('reports.adjust.cols.reason') }}">{{ __('reports.adjust.reasons.'.$a->reason) }}@if ($a->note)<small>{{ $a->note }}</small>@endif</td>
                <td data-lib="{{ __('reports.adjust.cols.user') }}">{{ $a->user?->name ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </section>
    @endif
  </div>
@endsection
