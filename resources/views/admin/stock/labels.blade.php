<!doctype html>
<html lang="fr-CA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('stock.receipts.labels_title', ['number' => $receipt->number()]) }} — {{ __('app.shop_name') }}</title>
@fonts
@vite(['resources/css/admin.css'])
</head>
<body class="page-etiquettes">
<div class="barre ne-pas-imprimer">
  <div>
    <h1>{{ __('stock.receipts.labels_title', ['number' => $receipt->number()]) }}</h1>
    @if (session('status'))<p class="alerte alerte-ok" role="status">{{ session('status') }}</p>@endif
  </div>
  <div class="actions">
    <a class="btn btn-secondaire" href="{{ route('admin.receipts.show', $receipt) }}">{{ __('stock.receipts.back') }}</a>
    <button class="btn btn-primaire" type="button" onclick="window.print()">{{ __('stock.receipts.print') }}</button>
  </div>
</div>

<div class="etiquettes">
  @foreach ($receipt->lots as $lot)
    <article class="etiquette-lot">
      <b class="nom">{{ $lot->product->name }}</b>
      @if ($lot->product->brand)<span>{{ $lot->product->brand }}</span>@endif
      <span class="lot">Lot {{ $lot->lot_number }}</span>
      <span>@qty($lot->converted_qty) {{ $lot->product->stockUnit->short_label }} · {{ __('stock.receipts.label_received', ['date' => \App\Support\Format::date($receipt->received_on)]) }}</span>
      @if ($lot->best_before)
        <span class="dlc">{{ __('stock.receipts.label_bb', ['date' => \App\Support\Format::date($lot->best_before)]) }}</span>
      @endif
    </article>
  @endforeach
</div>
</body>
</html>
