@extends('layouts.admin')

@section('title', __('catalog.products.title'))

@section('content')
  <div class="barre">
    <div><h1>{{ __('catalog.products.title') }}</h1><p>{{ __('catalog.products.intro') }}</p></div>
    @if ($canEdit)
      <div class="actions"><a class="btn btn-primaire" href="{{ route('admin.products.create') }}">+ {{ __('catalog.products.new') }}</a></div>
    @endif
  </div>

  <form class="filtres-liste carte" method="GET" action="{{ route('admin.products.index') }}" role="search">
    <div class="champ">
      <label for="q">{{ __('catalog.products.search') }}</label>
      <input id="q" name="q" type="search" value="{{ $q }}" placeholder="{{ __('catalog.products.search_placeholder') }}">
    </div>
    <div class="champ">
      <label for="categorie">{{ __('catalog.products.category') }}</label>
      <select id="categorie" name="categorie">
        <option value="">{{ __('catalog.products.all_categories') }}</option>
        @foreach ($categories as $c)
          <option value="{{ $c->id }}" @selected($categoryId === $c->id)>{{ $c->name }}</option>
        @endforeach
      </select>
    </div>
    <button class="btn btn-secondaire" type="submit">{{ __('catalog.products.filter') }}</button>
  </form>

  <section class="bloc carte" aria-labelledby="t-liste">
    <h2 id="t-liste" class="sr">{{ __('catalog.products.title') }}</h2>
    <p class="note" aria-live="polite">{{ trans_choice('catalog.products.count', $products->total()) }}</p>

    @if ($products->isEmpty())
      <p class="note">{{ __('catalog.products.empty') }}</p>
    @else
      <table class="tableau-cartes">
        <thead>
          <tr>
            <th scope="col">{{ __('catalog.products.col_product') }}</th>
            <th scope="col">{{ __('catalog.products.col_category') }}</th>
            <th scope="col">{{ __('stock.product.col_stock') }}</th>
            <th scope="col">{{ __('catalog.products.col_formats') }}</th>
            <th scope="col">{{ __('catalog.products.col_online') }}</th>
            <th scope="col"><span class="sr">{{ __('catalog.products.edit') }}</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($products as $p)
            <tr>
              <td data-lib="{{ __('catalog.products.col_product') }}">
                <span class="produit-ligne">
                  <span class="vignette" style="background:{{ $p->category->palette->background() }};color:{{ $p->category->palette->ink() }}" aria-hidden="true">
                    @if ($p->photo_path)<img src="{{ $p->photoUrl() }}" alt="" width="40" height="40" loading="lazy">@else<svg width="24" height="24"><use href="#i-{{ $p->category->icon }}"/></svg>@endif
                  </span>
                  <span><b>{{ $p->name }}</b>@if ($p->brand)<br><small class="note">{{ $p->brand }}</small>@endif</span>
                </span>
              </td>
              <td data-lib="{{ __('catalog.products.col_category') }}">{{ $p->category->name }}</td>
              @php $milli = $onHand->get($p->id, 0); @endphp
              <td data-lib="{{ __('stock.product.col_stock') }}" class="num">
                {{ \App\Support\Format::quantity(\App\Support\Quantity::fromMilli($milli)) }} {{ $p->stockUnit->short_label }}
                @if ($milli <= \App\Support\Quantity::toMilli($p->alert_threshold))
                  <span class="etiquette e-annulee">{{ $milli > 0 ? __('stock.product.below_threshold') : __('stock.product.at_zero') }}</span>
                @endif
              </td>
              <td data-lib="{{ __('catalog.products.col_formats') }}">{{ __('catalog.products.formats_summary', ['retail' => $p->retail_count, 'wholesale' => $p->wholesale_count]) }}</td>
              <td data-lib="{{ __('catalog.products.col_online') }}">
                @if ($p->is_visible)
                  <span class="etiquette e-prete">{{ __('catalog.products.online') }}</span>
                @else
                  <span class="etiquette e-remise">{{ __('catalog.products.hidden') }}</span>
                @endif
                @if ($p->is_frozen)<span class="etiquette e-surgele">Surgelé</span>@endif
              </td>
              <td class="droite">
                <a class="btn btn-secondaire mini" href="{{ route('admin.products.edit', $p) }}">{{ $canEdit ? __('catalog.products.edit') : __('catalog.products.view') }}<span class="sr"> {{ $p->name }}</span></a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      {{ $products->links('admin.partials.pagination') }}
    @endif
  </section>
@endsection
