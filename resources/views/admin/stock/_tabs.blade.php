<nav class="onglets" aria-label="{{ __('stock.receipts.title') }}">
  @foreach ([
      'admin.receipts.create' => __('stock.tabs.new'),
      'admin.receipts.index' => __('stock.tabs.journal'),
      'admin.arrivals.index' => __('stock.tabs.arrivals'),
      'admin.preorders.index' => __('preorders.title'),
      'admin.suppliers.index' => __('stock.tabs.suppliers'),
  ] as $route => $label)
    <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>
