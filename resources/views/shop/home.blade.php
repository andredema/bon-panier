@extends('layouts.shop')

@php
    $config = [
        'catalog' => $catalog,
        'cart' => $cart,
        'slots' => $slots,
        'openCheckout' => (bool) session('open_checkout'),
        'urls' => [
            'add' => route('shop.cart.add'),
            'line' => route('shop.cart.update', ['format' => '__ID__']),
        ],
        'texts' => [
            'all' => __('shop.categories.all'),
            'allWholesale' => __('shop.categories.all_wholesale'),
            'product' => ':n produit',
            'products' => ':n produits',
            'inStock' => __('shop.filters.in_stock'),
            'preorder' => __('shop.filters.preorder'),
            'frozen' => __('shop.filters.frozen'),
            'dry' => __('shop.filters.dry'),
            'units' => __('shop.filters.units'),
            'soldIn' => __('shop.filters.sold_in_chip'),
            'added' => __('shop.card.added'),
            'preordered' => __('shop.card.preordered'),
            'network' => __('shop.cart.errors.network'),
        ],
    ];
    $chevron = '<svg width="16" height="16" aria-hidden="true"><use href="#i-fleche"/></svg>';
@endphp

@section('body')
x-data="orderPage(@js($config))" @keydown.escape.window="fermer(); menu = null"
@endsection

@section('header-tools')
  <div class="recherche">
    <label for="q" class="sr">{{ __('shop.header.search') }}</label>
    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="#4A4033" stroke-width="2"/><path d="m20 20-4-4" stroke="#4A4033" stroke-width="2" stroke-linecap="round"/></svg>
    <input id="q" type="search" x-model.debounce.150ms="q" placeholder="{{ __('shop.header.search_placeholder') }}" autocomplete="off">
  </div>
  @if ($catalog['wholesale'])
    <div class="canal" role="group" aria-label="{{ __('shop.header.channel') }}">
      <button type="button" @click="changerCanal('detail')" :aria-pressed="(canal === 'detail').toString()" aria-pressed="true">{{ __('shop.header.retail') }}</button>
      <button type="button" @click="changerCanal('gros')" :aria-pressed="(canal === 'gros').toString()" aria-pressed="false">{{ __('shop.header.wholesale') }}</button>
    </div>
  @endif
  <button class="btn btn-panier" type="button" @click="ouvrir()" :aria-label="`{{ __('shop.header.cart') }} : ${cart.labels.count}, ${cart.labels.total}`">
    {{ __('shop.header.cart') }} <span class="nb num" x-text="cart.count">{{ $cart['count'] }}</span><span class="mt" x-text="cart.labels.total">{{ $cart['labels']['total'] }}</span>
  </button>
@endsection

@section('content')
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-fleche" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></symbol>
</svg>

@if ($catalog['wholesale'])
  <div class="bandeau-gros" x-show="canal === 'gros'" x-cloak><div>{!! __('shop.wholesale_banner') !!}</div></div>
@endif

<main class="corps" id="contenu">
  <div class="gauche">
    {{-- Catégories en cartes --}}
    <section class="categories" aria-labelledby="t-cats">
      <div class="titre-ligne"><h2 id="t-cats">{{ __('shop.categories.title') }}</h2><p>{{ __('shop.categories.hint') }}</p></div>
      <div class="grille-cats">
        <button type="button" class="cat cat-tout" @click="choisirCat(null)" :aria-pressed="(cat === null).toString()">
          <span class="ico"><svg width="26" height="26" aria-hidden="true"><use href="#i-sac"/></svg></span>
          <span class="txt"><b>{{ __('shop.categories.all') }}</b><span class="nb" x-text="nbProduits(compteCat(null))"></span></span>
        </button>
        <template x-for="c in catsTriees" :key="c.id">
          <button type="button" class="cat" @click="choisirCat(c.id)" :aria-pressed="(cat === c.id).toString()" :style="`background:${c.bg}`" x-show="compteCat(c.id) > 0">
            <span class="ico" :style="`color:${c.ink}`"><svg width="26" height="26" aria-hidden="true"><use :href="`#i-${c.icon}`"/></svg></span>
            <span class="txt"><b x-text="c.name"></b><span class="nb" x-text="nbProduits(compteCat(c.id))"></span></span>
          </button>
        </template>
      </div>
    </section>

    {{-- Titre et filtres regroupés --}}
    <section class="outils" aria-label="{{ __('shop.filters.title') }}">
      <div class="titre-ligne">
        <div class="entete-liste"><h1 id="t-res" tabindex="-1" x-text="titre">{{ __('shop.categories.all') }}</h1><span class="nb-cat" x-text="nbProduits(liste.length)"></span></div>
        <p x-text="`${ {nom: @js(__('shop.filters.order_name')), prix: @js(__('shop.filters.order_price')), pop: @js(__('shop.filters.order_popular'))}[tri] } · ${canal === 'gros' ? @js(__('shop.filters.prices_wholesale')) : @js(__('shop.filters.prices_retail'))}`"></p>
      </div>

      <div class="barre-filtres" @click.outside="menu = null">
        <span class="lib">{{ __('shop.filters.label') }}</span>
        @foreach (['dispo' => __('shop.filters.availability'), 'cons' => __('shop.filters.storage'), 'unite' => __('shop.filters.sold_in')] as $g => $label)
          <div class="deroul" :class="{ ouvert: menu === '{{ $g }}' }">
            <button type="button" @click="menu = menu === '{{ $g }}' ? null : '{{ $g }}'" :aria-expanded="(menu === '{{ $g }}').toString()" :data-actif="({{ $g }}.length > 0).toString()">
              {{ $label }} <span class="compte" x-show="{{ $g }}.length" x-text="{{ $g }}.length"></span>{!! $chevron !!}
            </button>
            <div class="menu-d" role="group" aria-label="{{ $label }}">
              <template x-for="[v, l, n] in options('{{ $g }}')" :key="v">
                <label class="opt"><input type="checkbox" :value="v" :checked="{{ $g }}.includes(v)" @change="basculer('{{ $g }}', v)"> <span x-text="l"></span><span class="n" x-text="n"></span></label>
              </template>
            </div>
          </div>
        @endforeach
        <span class="sep" aria-hidden="true"></span>
        <div class="tri">
          <label for="tri" class="lib">{{ __('shop.filters.sort') }}</label>
          <select id="tri" x-model="tri">
            <option value="nom">{{ __('shop.filters.sort_name') }}</option>
            <option value="prix">{{ __('shop.filters.sort_price') }}</option>
            <option value="pop">{{ __('shop.filters.sort_popular') }}</option>
          </select>
        </div>
      </div>

      <div class="filtres-compact">
        <button class="btn btn-secondaire" type="button" @click="ouvrirFiltres()">
          <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
          {{ __('shop.filters.open') }}<span class="compte" x-show="nbFiltres" x-text="nbFiltres" x-cloak></span>
        </button>
        <label class="sr" for="tri-m">{{ __('shop.filters.sort') }}</label>
        <select id="tri-m" x-model="tri">
          <option value="nom">{{ __('shop.filters.sort_name') }}</option>
          <option value="prix">{{ __('shop.filters.sort_price') }}</option>
          <option value="pop">{{ __('shop.filters.sort_popular') }}</option>
        </select>
      </div>

      <div class="actifs" aria-live="polite">
        <template x-for="[g, v, l] in puces" :key="g + v">
          <button class="puce" type="button" @click="basculer(g, v)" :aria-label="`{{ __('shop.filters.remove', ['name' => '__N__']) }}`.replace('__N__', l)"><span x-text="l"></span> <span aria-hidden="true">×</span></button>
        </template>
        <button class="lien-btn" type="button" x-show="puces.length" x-cloak @click="effacer()">{{ __('shop.filters.clear_all') }}</button>
      </div>
    </section>

    {{-- Produits --}}
    <section aria-labelledby="t-res">
      <div class="grille">
        <template x-for="p in liste" :key="p.id + canal">
          <article class="produit carte" :aria-label="p.name">
            <div class="photo" :style="`background:${categories.find(c => c.id === p.cat)?.bg};color:${categories.find(c => c.id === p.cat)?.ink}`">
              <template x-if="p.photo"><img :src="p.photo" alt="" loading="lazy"></template>
              <div class="badges">
                <span class="etiquette e-surgele" x-show="p.frozen">{{ __('shop.card.frozen') }}</span>
                <span class="etiquette e-preco" x-show="p.status === 'preco'">{{ __('shop.card.preorder') }}</span>
              </div>
              <span class="dans-panier" x-show="dansPanier(p)">{{ __('shop.card.in_cart') }}</span>
              <svg x-show="!p.photo" width="56" height="56" aria-hidden="true"><use :href="`#i-${categories.find(c => c.id === p.cat)?.icon}`"/></svg>
            </div>
            <div class="produit-in">
              <div><div class="marque-p" x-text="p.brand ?? ''"></div><h3 x-text="p.name"></h3></div>
              <div class="format">
                <template x-if="formatsDe(p).length > 1">
                  <div class="format">
                    <label :for="`f${p.id}`">{{ __('shop.card.format') }}</label>
                    <select :id="`f${p.id}`" @change="choix[p.id + canal] = Number($event.target.value)">
                      <template x-for="(x, j) in formatsDe(p)" :key="x.id">
                        <option :value="j" x-text="x.label" :selected="formatChoisi(p).id === x.id"></option>
                      </template>
                    </select>
                  </div>
                </template>
                <template x-if="formatsDe(p).length === 1">
                  <div class="format"><span class="l">{{ __('shop.card.format') }}</span><span class="fixe" x-text="formatChoisi(p).label"></span></div>
                </template>
              </div>
              <div class="prix-ligne">
                <span class="prix num"><span x-text="formatChoisi(p).price_label"></span><small x-show="formatChoisi(p).by_weight" x-text="`/${formatChoisi(p).unit}`"></small></span>
                <span class="prix-unit num" x-text="formatChoisi(p).cmp ?? ''"></span>
              </div>
              <div class="info info-preco" x-show="p.status === 'preco'" x-text="@js(__('shop.card.arrives', ['date' => '__D__'])).replace('__D__', p.arrival)"></div>
              <div class="info info-none" x-show="p.status === 'none'">{{ __('shop.card.back_soon') }}</div>
              <div class="info info-min" x-show="p.status !== 'none' && p.status !== 'preco' && formatChoisi(p).min_label" x-text="formatChoisi(p).min_label"></div>
              <div class="info info-vide" aria-hidden="true" x-show="p.status === 'stock' && !formatChoisi(p).min_label"></div>
              <div class="achat">
                <div class="pas" role="group" :aria-label="@js(__('shop.card.qty_of', ['name' => '__N__'])).replace('__N__', p.name)">
                  <button type="button" @click="pas(p, -1)" aria-label="{{ __('shop.card.decrease') }}" :disabled="p.status === 'none'">−</button>
                  <output class="num" x-text="qteLabel(qteDe(p), formatChoisi(p))"></output>
                  <button type="button" @click="pas(p, 1)" aria-label="{{ __('shop.card.increase') }}" :disabled="p.status === 'none'">+</button>
                </div>
                <button class="btn" type="button" :class="p.status === 'preco' ? 'btn-preco' : 'btn-primaire'" @click="ajouter(p)" :disabled="p.status === 'none' || occupe">
                  <span x-show="p.status === 'stock'">{{ __('shop.card.add') }}<span class="long">{{ __('shop.card.add_long') }}</span></span>
                  <span x-show="p.status === 'preco'">{{ __('shop.card.preorder_btn') }}</span>
                  <span x-show="p.status === 'none'">{{ __('shop.card.unavailable') }}</span>
                </button>
              </div>
            </div>
          </article>
        </template>
        <p class="vide-res carte" x-show="liste.length === 0" x-cloak>{{ __('shop.filters.none') }}</p>
      </div>
    </section>
  </div>

  {{-- Panier latéral, toujours visible sur ordinateur --}}
  <aside class="panier carte" aria-labelledby="t-panier">
    <div class="panier-tete"><h2 id="t-panier">{{ __('shop.cart.title') }}</h2><span x-text="cart.labels.count"></span></div>
    <p class="alerte alerte-erreur alerte-panier" role="alert" x-show="erreur" x-text="erreur" x-cloak></p>
    <div class="lignes">@include('shop.partials.cart-lines')</div>
    <div class="totaux" aria-live="polite">
      <div><span>{{ __('shop.cart.subtotal') }}</span><span class="num" x-text="cart.labels.subtotal"></span></div>
      <div><span>{{ __('shop.cart.taxes') }}</span><span class="num" x-text="cart.labels.taxes"></span></div>
      <div class="total"><span>{{ __('shop.cart.total') }}</span><span class="num" x-text="cart.labels.total"></span></div>
      <button class="btn btn-primaire" type="button" @click="ouvrir()" :disabled="!cart.count">{{ __('shop.cart.order') }}</button>
      <p class="note-p">{{ $checkout['pay_on_handover'] ? __('shop.cart.note') : __('shop.cart.note_interac') }}</p>
    </div>
  </aside>
</main>

{{-- Barre du total, toujours visible sur téléphone et tablette --}}
<div class="barre-total" role="region" aria-label="{{ __('shop.cart.total_region') }}">
  <div class="t" aria-live="polite"><small x-text="cart.labels.count"></small><b class="num" x-text="cart.labels.total"></b></div>
  <button class="btn" type="button" @click="ouvrir()">{{ __('shop.cart.see') }}</button>
</div>

<div class="voile" @click="fermer()"></div>

{{-- Feuille des filtres (téléphone et tablette) --}}
<section class="feuille" role="dialog" aria-modal="true" aria-labelledby="t-feuille" :inert="!feuille">
  <div class="poignee" aria-hidden="true"></div>
  <div class="feuille-tete"><h2 id="t-feuille">{{ __('shop.filters.title') }}</h2><button class="x" type="button" id="fermer-filtres" @click="feuille = false" aria-label="{{ __('shop.filters.close') }}">×</button></div>
  <div class="feuille-corps">
    @foreach (['dispo' => __('shop.filters.availability'), 'cons' => __('shop.filters.storage'), 'unite' => __('shop.filters.sold_in')] as $g => $label)
      <fieldset class="groupe-f" style="border:0;margin:0;padding-left:0;padding-right:0">
        <h3>{{ $label }}</h3>
        <div class="puces-f">
          <template x-for="[v, l, n] in options('{{ $g }}')" :key="v">
            <label><input type="checkbox" :value="v" :checked="{{ $g }}.includes(v)" @change="basculer('{{ $g }}', v)"> <span x-text="l"></span> <span class="n" x-text="n"></span></label>
          </template>
        </div>
      </fieldset>
    @endforeach
  </div>
  <div class="feuille-pied">
    <button class="btn btn-secondaire" type="button" @click="effacer()">{{ __('shop.filters.clear') }}</button>
    <button class="btn btn-primaire" type="button" @click="feuille = false; $nextTick(() => document.getElementById('t-res').scrollIntoView({ behavior: 'smooth' }))" x-text="liste.length > 1 ? @js(__('shop.filters.see', ['count' => '__N__'])).split('|')[1].replace('__N__', liste.length) : @js(__('shop.filters.see', ['count' => '__N__'])).split('|')[0].replace('__N__', liste.length)"></button>
  </div>
</section>

@include('shop.partials.checkout')

<div class="avis" :class="{ vu: avisVu }" role="status" aria-live="polite" x-html="avisTexte"></div>
@endsection
