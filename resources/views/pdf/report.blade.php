@php
    use App\Support\Format;
    $has = fn ($s) => in_array($s, $sections, true);
    $bars = function (array $rows) {
        $max = max(1, collect($rows)->max(fn ($r) => $r[1]));
        return collect($rows)->map(fn ($r) => $r + [3 => round($r[1] / $max * 100)])->all();
    };
@endphp
<!doctype html>
<html lang="fr-CA">
<head>
<meta charset="utf-8"><title>{{ $title }}</title>
@include('pdf._style')
<style>
  h1 { font-size: 17px; color: #1F4D2E; }
  h2 { font-size: 12px; margin: 16px 0 6px; color: #1F4D2E; }
  .sous { color: #6B5F4F; margin-top: 2px; }
  .kpis { width: 100%; margin-top: 12px; border-collapse: separate; border-spacing: 6px 0; }
  .kpis td { background: #F7F2E8; padding: 8px; width: 25%; vertical-align: top; }
  .kpis .lib { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #6B5F4F; }
  .kpis .val { font-size: 14px; font-weight: bold; margin-top: 2px; }
  .kpis .det { font-size: 8.5px; color: #4A4033; }
  .tab { width: 100%; border-collapse: collapse; }
  .tab td, .tab th { padding: 5px 4px; border-bottom: 1px solid #E6DCC8; text-align: left; }
  .tab th { font-size: 8.5px; text-transform: uppercase; color: #6B5F4F; }
  .tab tfoot td { font-weight: bold; border-top: 2px solid #1E1B16; border-bottom: 0; }
  .barre { height: 8px; background: #1F4D2E; }
  .barre.tx { background: #D49A3A; }
  .piste { background: #F0EADF; width: 100%; }
</style>
</head>
<body>
  <h1>{{ $title }}</h1>
  <p class="sous">{{ $shopName }} · {{ $period->span() }} · {{ __('billing.pdf.correction', ['date' => Format::date(today())]) }}</p>

  @if ($has('kpis'))
    <table class="kpis"><tr>
      <td><div class="lib">{{ __('reports.kpi.sales') }}</div><div class="val">{{ Format::money($kpis['sales']) }}</div><div class="det">{{ trans_choice('reports.kpi.invoices', $kpis['count']) }}</div></td>
      <td><div class="lib">{{ __('reports.kpi.average') }}</div><div class="val">{{ Format::money($kpis['average']) }}</div><div class="det">{{ __('reports.kpi.before_tax') }}</div></td>
      <td><div class="lib">{{ __('reports.kpi.taxes') }}</div><div class="val">{{ Format::money($kpis['tps'] + $kpis['tvq']) }}</div><div class="det">{{ __('reports.kpi.taxes_split', ['tps' => Format::money($kpis['tps']), 'tvq' => Format::money($kpis['tvq'])]) }}</div></td>
      <td><div class="lib">{{ __('reports.kpi.collected') }}</div><div class="val">{{ Format::money($kpis['collected']) }}</div><div class="det">{{ __('reports.kpi.collected_note') }}</div></td>
    </tr></table>
  @endif

  @if ($has('taxes'))
    <h2>{{ __('reports.taxes.title') }}</h2>
    <p class="sous">{{ __('reports.taxes.note') }}</p>
    <table class="tab">
      <tr><td>{{ __('reports.taxes.zero_rated') }}</td><td class="d">{{ Format::money($taxes['zero_rated']) }}</td></tr>
      <tr><td>{{ __('reports.taxes.taxable') }}</td><td class="d">{{ Format::money($taxes['taxable']) }}</td></tr>
      <tr><td>{{ __('reports.taxes.credits') }}</td><td class="d">{{ Format::money(-$taxes['credits']) }}</td></tr>
      <tr><td>{{ __('reports.taxes.tps') }}</td><td class="d">{{ Format::money($taxes['tps']) }}</td></tr>
      <tr><td>{{ __('reports.taxes.tvq') }}</td><td class="d">{{ Format::money($taxes['tvq']) }}</td></tr>
      <tfoot><tr><td>{{ __('reports.taxes.total') }}</td><td class="d">{{ Format::money($taxes['total']) }}</td></tr></tfoot>
    </table>
  @endif

  @foreach (array_filter([
      'payments' => $has('payments') ? [__('reports.payments.title'), collect($payments)->map(fn ($c, $m) => [__('billing.methods.'.$m), $c, $m !== 'interac'])->values()->all()] : null,
      'channels' => $has('channels') ? [__('reports.channels.title'), [[__('reports.channels.detail'), $channels['detail'], false], [__('reports.channels.gros'), $channels['gros'], true]]] : null,
      'categories' => $has('categories') ? [__('reports.categories.title'), array_map(fn ($c) => [$c['name'], $c['cents'], $c['taxable']], $categories)] : null,
  ]) as [$heading, $rows])
    <h2>{{ $heading }}</h2>
    <table class="tab">
      @forelse ($bars($rows) as [$label, $cents, $alt, $pct])
        <tr>
          <td style="width:32%">{{ $label }}</td>
          <td style="width:48%"><div class="piste"><div class="barre {{ $alt ? 'tx' : '' }}" style="width:{{ max(1, $pct) }}%"></div></div></td>
          <td class="d">{{ Format::money($cents) }}</td>
        </tr>
      @empty
        <tr><td>{{ __('reports.none') }}</td></tr>
      @endforelse
    </table>
  @endforeach

  @if ($has('top'))
    <h2>{{ __('reports.top.title') }}</h2>
    <table class="tab">
      @forelse ($top as $t)<tr><td>{{ $t['name'] }}</td><td class="d">{{ Format::money($t['cents']) }}</td></tr>@empty<tr><td>{{ __('reports.none') }}</td></tr>@endforelse
    </table>
  @endif

  @if ($has('receivables'))
    <h2>{{ __('reports.receivables.title') }} — {{ Format::money($receivables['total']) }}</h2>
    <p class="sous">{{ __('reports.receivables.note') }}
      @foreach ($receivables['buckets'] as $b => $c) · {{ __('reports.receivables.buckets.'.$b) }} : {{ Format::money($c) }}@endforeach</p>
    <table class="tab">
      <thead><tr><th>{{ __('reports.receivables.invoice') }}</th><th>{{ __('billing.cols.date') }}</th><th>{{ __('reports.receivables.customer') }}</th><th class="d">{{ __('reports.receivables.balance') }}</th><th>{{ __('reports.receivables.age') }}</th></tr></thead>
      @forelse ($receivables['rows'] as $r)
        <tr><td>{{ $r['invoice']->number }}</td><td>{{ Format::date($r['invoice']->issued_at) }}</td><td>{{ $r['invoice']->customer_snapshot['name'] }}</td><td class="d">{{ Format::money($r['balance']) }}</td><td>{{ __('reports.receivables.buckets.'.$r['bucket']) }}</td></tr>
      @empty
        <tr><td colspan="5">{{ __('reports.receivables.none') }}</td></tr>
      @endforelse
    </table>
  @endif

  @if ($has('inventory'))
    <h2>{{ __('reports.inventory.title', ['date' => Format::date(today())]) }} — {{ Format::money($inventory['total']) }}</h2>
    <p class="sous">{{ __('reports.inventory.note') }} @if ($inventory['without_cost']){{ trans_choice('reports.inventory.without_cost', $inventory['without_cost']) }}@endif</p>
    <table class="tab">
      <thead><tr><th>{{ __('reports.inventory.product') }}</th><th>{{ __('reports.inventory.lot') }}</th><th class="d">{{ __('reports.inventory.qty') }}</th><th>{{ __('reports.inventory.best_before') }}</th><th class="d">{{ __('reports.inventory.value') }}</th></tr></thead>
      @foreach ($inventory['lots'] as $l)
        <tr><td>{{ $l->product->name }}</td><td>{{ $l->lot_number }}</td><td class="d">{{ Format::quantity($l->remaining_qty) }} {{ $l->product->stockUnit->short_label }}</td>
          <td>{{ $l->best_before ? Format::date($l->best_before) : '—' }}</td><td class="d">{{ $l->value !== null ? Format::money($l->value) : '—' }}</td></tr>
      @endforeach
      <tfoot><tr><td colspan="4">{{ __('reports.inventory.value') }}</td><td class="d">{{ Format::money($inventory['total']) }}</td></tr></tfoot>
    </table>
  @endif
</body>
</html>
