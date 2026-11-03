@extends('layouts.admin')

@php
    $labels = [
        'canal' => ['detail' => __('orders.filters.retail'), 'gros' => __('orders.filters.wholesale')],
        'mode' => ['ramassage' => __('orders.filters.pickup'), 'livraison' => __('orders.filters.delivery')],
        'jour' => ['auj' => __('orders.filters.today'), 'demain' => __('orders.filters.tomorrow'), 'semaine' => __('orders.filters.week')],
    ];
    $active = collect(['canal', 'mode', 'jour'])->sum(fn ($k) => count($filters[$k])) + ($filters['preco'] ? 1 : 0);
    // Lien qui garde les autres filtres
    $url = fn (array $changes) => route('admin.orders.index', array_filter(array_merge(request()->query(), $changes), fn ($v) => $v !== null && $v !== [] && $v !== ''));
@endphp

@section('title', __('orders.title'))

@section('content')
  <div class="barre">
    <div><h1>{{ __('orders.title') }}</h1><p>{{ __('orders.intro') }}</p></div>
    <div class="actions"><a class="btn btn-primaire" href="{{ route('admin.orders.create') }}">{{ __('orders.new_counter') }}</a></div>
  </div>

  @if ($errors->any())
    <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
  @endif

  <section class="bloc carte" aria-labelledby="t-cmd">
    <h2 id="t-cmd" class="sr">{{ __('orders.title') }}</h2>

    <div class="outils-cmd">
      <nav class="segment segment-liens" aria-label="{{ __('orders.cols.status') }}">
        @foreach (__('orders.segments') as $key => $label)
          <a href="{{ $url(['statut' => $key, 'page' => null]) }}" @if ($segment === $key) aria-current="page" @endif>{{ $label }}<span class="n">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
      </nav>

      <div class="pop" x-data="{ ouvert: false }" @click.outside="ouvert = false" @keydown.escape="ouvert = false" :class="{ ouvert }">
        <button class="btn btn-secondaire mini" type="button" @click="ouvert = !ouvert" :aria-expanded="ouvert.toString()" aria-controls="panneau">
          {{ __('orders.filters.button') }} @if ($active)({{ $active }})@endif
        </button>
        <form class="panneau carte" id="panneau" method="GET" action="{{ route('admin.orders.index') }}">
          <input type="hidden" name="statut" value="{{ $segment }}">
          @foreach (['canal' => __('orders.filters.channel'), 'mode' => __('orders.filters.reception'), 'jour' => __('orders.filters.slot')] as $key => $legend)
            <fieldset><legend>{{ $legend }}</legend><div class="ligne-opt">
              @foreach ($labels[$key] as $v => $l)
                <label><input type="checkbox" name="{{ $key }}[]" value="{{ $v }}" @checked(in_array($v, $filters[$key], true))> {{ $l }}</label>
              @endforeach
            </div></fieldset>
          @endforeach
          <fieldset><legend>{{ __('orders.filters.content') }}</legend><div class="ligne-opt">
            <label><input type="checkbox" name="preco" value="1" @checked($filters['preco'])> {{ __('orders.filters.with_preorder') }}</label>
          </div></fieldset>
          <div class="champ"><label for="q">{{ __('orders.search') }}</label><input id="q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="{{ __('orders.search_placeholder') }}"></div>
          <div class="pied-p">
            <a class="lien-btn" href="{{ route('admin.orders.index', ['statut' => $segment]) }}">{{ __('orders.filters.clear') }}</a>
            <button class="btn btn-primaire mini" type="submit">{{ __('orders.filters.apply') }}</button>
          </div>
        </form>
      </div>
    </div>

    @if ($active || $filters['q'] !== '')
      <div class="actifs">
        @foreach (['canal', 'mode', 'jour'] as $key)
          @foreach ($filters[$key] as $v)
            <a class="puce" href="{{ $url([$key => array_values(array_diff($filters[$key], [$v])), 'page' => null]) }}" aria-label="{{ __('orders.filters.remove', ['name' => $labels[$key][$v]]) }}">{{ $labels[$key][$v] }} ×</a>
          @endforeach
        @endforeach
        @if ($filters['preco'])<a class="puce" href="{{ $url(['preco' => null, 'page' => null]) }}">{{ __('orders.filters.with_preorder') }} ×</a>@endif
        @if ($filters['q'] !== '')<a class="puce" href="{{ $url(['q' => null, 'page' => null]) }}">« {{ $filters['q'] }} » ×</a>@endif
      </div>
    @endif

    <p class="note" aria-live="polite">{{ trans_choice('orders.count', $orders->total()) }}</p>
    @if ($orders->isEmpty())
      <p class="note">{{ __('orders.empty') }}</p>
    @else
      <table class="tableau-cartes">
        <thead><tr>
          @foreach (['number', 'customer', 'channel', 'slot'] as $c)<th scope="col">{{ __('orders.cols.'.$c) }}</th>@endforeach
          <th scope="col" class="droite">{{ __('orders.cols.total') }}</th>
          <th scope="col">{{ __('orders.cols.status') }}</th>
          <th scope="col"><span class="sr">{{ __('orders.cols.action') }}</span></th>
        </tr></thead>
        <tbody>
          @foreach ($orders as $order)
            @include('admin.orders._row')
          @endforeach
        </tbody>
      </table>
      {{ $orders->links('admin.partials.pagination') }}
    @endif
  </section>
@endsection
