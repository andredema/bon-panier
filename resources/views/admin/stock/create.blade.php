@extends('layouts.admin')

@php
    $config = [
        'lines' => $lines,
        'soon' => $soon,
        'errors' => $errors->getMessages(),
        'searchUrl' => route('admin.receipts.products'),
    ];
@endphp

@section('title', __('stock.receipts.title'))

@section('content')
<form class="entree" method="POST" action="{{ route('admin.receipts.store') }}" novalidate x-data="stockReceipt(@js($config))">
  @csrf
  <div class="barre">
    <div><h1>{{ __('stock.receipts.title') }}</h1><p>{{ __('stock.receipts.intro') }}</p></div>
    <div class="actions"><a class="btn btn-secondaire" href="{{ route('admin.dashboard') }}">{{ __('stock.receipts.cancel') }}</a></div>
  </div>

  @include('admin.stock._tabs')

  <ol class="etapes-entree" aria-label="Comment ça marche">
    <li class="etape carte"><b class="n" aria-hidden="true">1</b>{{ __('stock.receipts.step1') }}</li>
    <li class="etape carte"><b class="n" aria-hidden="true">2</b>{{ __('stock.receipts.step2') }}</li>
    <li class="etape carte"><b class="n" aria-hidden="true">3</b>{{ __('stock.receipts.step3') }}</li>
  </ol>

  @if ($errors->any())
    <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
  @endif

  <div class="disposition-entree">
    <div class="col">
      <section class="bloc carte" aria-labelledby="t-bon">
        <h2 id="t-bon">{{ __('stock.receipts.header') }}</h2>
        <div class="entete-bon">
          <div class="champ">
            <label for="supplier_id">{{ __('stock.receipts.supplier') }}</label>
            <select id="supplier_id" name="supplier_id">
              <option value="">{{ __('stock.receipts.no_supplier') }}</option>
              @foreach ($suppliers as $s)
                <option value="{{ $s->id }}" @selected((string) old('supplier_id') === (string) $s->id)>{{ $s->name }}@if ($s->contact) — {{ $s->contact }}@endif</option>
              @endforeach
            </select>
            @error('supplier_id')<span class="erreur">{{ $message }}</span>@enderror
          </div>
          <div class="champ">
            <label for="received_on">{{ __('stock.receipts.received_on') }}</label>
            <input id="received_on" name="received_on" type="date" value="{{ old('received_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
            @error('received_on')<span class="erreur">{{ $message }}</span>@enderror
          </div>
          <div class="champ">
            <label for="delivery_note_no">{{ __('stock.receipts.delivery_note') }}</label>
            <input id="delivery_note_no" name="delivery_note_no" value="{{ old('delivery_note_no') }}" placeholder="{{ __('stock.receipts.optional') }}" maxlength="60">
          </div>
        </div>
      </section>

      <section class="bloc carte" aria-labelledby="t-ajout">
        <h2 id="t-ajout">{{ __('stock.receipts.add') }}</h2>
        <div class="scan">
          <label for="recherche" class="sr">{{ __('stock.receipts.search_label') }}</label>
          <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5v14 M6 5v14 M10 5v14 M13 5v14 M17 5v14 M21 5v14" stroke="#1F4D2E" stroke-width="2"/></svg>
          <input id="recherche" type="search" autocomplete="off" placeholder="{{ __('stock.receipts.search_placeholder') }}"
                 x-model="q" @input="search" @keydown.enter.prevent="enter"
                 role="combobox" aria-autocomplete="list" aria-controls="resultats" :aria-expanded="(results.length > 0).toString()">
        </div>
        <ul class="resultats" id="resultats" role="listbox" x-show="results.length" x-cloak>
          <template x-for="p in results" :key="p.id">
            <li role="option">
              <button type="button" @click="add(p)">
                <span><small x-text="p.brand"></small> <b x-text="p.name"></b></span>
                <span class="note" x-text="`{{ __('stock.receipts.current_stock', ['qty' => '__Q__', 'unit' => '__U__']) }}`.replace('__Q__', fmt(p.on_hand_milli / 1000)).replace('__U__', p.unit)"></span>
              </button>
            </li>
          </template>
        </ul>
        <p class="note" x-show="searched && !results.length" x-cloak role="status">{{ __('stock.receipts.search_none') }}</p>
        <div class="rapides" x-show="soon.length" x-cloak>
          {{ __('stock.receipts.expected_soon') }}
          <template x-for="p in soon" :key="p.id">
            <button type="button" @click="add(p)" x-text="`+ ${p.name}`"></button>
          </template>
        </div>
      </section>

      <section class="bloc carte" aria-labelledby="t-lignes">
        <div class="bloc-tete">
          <h2 id="t-lignes">{{ __('stock.receipts.lines') }}</h2>
          <span class="note" aria-live="polite" x-text="lines.length + (lines.length > 1 ? ' produits' : ' produit')"></span>
        </div>
        @error('lines')<div class="alerte alerte-erreur" role="alert">{{ $message }}</div>@enderror

        <template x-for="(line, i) in lines" :key="line.key">
          <article class="ligne-arr" :aria-label="line.product.name">
            <input type="hidden" :name="`lines[${i}][product_id]`" :value="line.product.id">
            <div class="haut">
              <div>
                <small x-text="line.product.brand"></small>
                <h3 x-text="line.product.name"></h3>
                <small x-text="`{{ __('stock.receipts.current_stock', ['qty' => '__Q__', 'unit' => '__U__']) }}`.replace('__Q__', fmt(before(line, i))).replace('__U__', line.product.unit)"></small>
              </div>
              <button class="suppr" type="button" @click="remove(i)" :aria-label="`{{ __('stock.receipts.remove', ['name' => '__N__']) }}`.replace('__N__', line.product.name)">×</button>
            </div>

            <div class="champs">
              <div class="champ">
                <label :for="`s-${line.key}`">{{ __('stock.receipts.source') }}</label>
                <select :id="`s-${line.key}`" :name="`lines[${i}][source]`" x-model="line.source">
                  <template x-for="o in line.product.sources" :key="o.value">
                    <option :value="o.value" x-text="o.label" :selected="o.value === line.source"></option>
                  </template>
                </select>
                <span class="erreur" x-text="error(i, 'source')"></span>
              </div>
              <div class="champ">
                <span :id="`lq-${line.key}`">{{ __('stock.receipts.qty') }}</span>
                <div class="pas" role="group" :aria-labelledby="`lq-${line.key}`">
                  <button type="button" @click="step(line, -1)" aria-label="{{ __('stock.receipts.decrease') }}">−</button>
                  <input :id="`q-${line.key}`" :name="`lines[${i}][received_qty]`" x-model="line.received_qty" :inputmode="allowsDecimals(line) ? 'decimal' : 'numeric'" :aria-labelledby="`lq-${line.key}`">
                  <button type="button" @click="step(line, 1)" aria-label="{{ __('stock.receipts.increase') }}">+</button>
                </div>
                <span class="erreur" x-text="error(i, 'received_qty')"></span>
              </div>
              <div class="champ">
                <label :for="`l-${line.key}`">{{ __('stock.receipts.lot_number') }}</label>
                <input :id="`l-${line.key}`" :name="`lines[${i}][lot_number]`" x-model="line.lot_number" placeholder="{{ __('stock.receipts.lot_placeholder') }}" maxlength="60">
                <span class="erreur" x-text="error(i, 'lot_number')"></span>
              </div>
              <div class="champ">
                <label :for="`d-${line.key}`">{{ __('stock.receipts.best_before') }}</label>
                <input type="date" :id="`d-${line.key}`" :name="`lines[${i}][best_before]`" x-model="line.best_before">
                <span class="erreur" x-text="error(i, 'best_before')"></span>
              </div>
              <div class="champ">
                <label :for="`c-${line.key}`">{{ __('stock.receipts.unit_cost') }}</label>
                <input :id="`c-${line.key}`" :name="`lines[${i}][unit_cost]`" x-model="line.unit_cost" inputmode="decimal" placeholder="{{ __('stock.receipts.optional') }}">
                <span class="erreur" x-text="error(i, 'unit_cost')"></span>
              </div>
            </div>

            <div class="champs-autre" x-show="line.source === 'other'">
              <div class="champ">
                <label :for="`ol-${line.key}`">{{ __('stock.receipts.other_label') }}</label>
                <input :id="`ol-${line.key}`" :name="`lines[${i}][other_label]`" x-model="line.other_label" placeholder="{{ __('stock.receipts.other_label_placeholder') }}" maxlength="120">
                <span class="erreur" x-text="error(i, 'other_label')"></span>
              </div>
              <div class="champ">
                <label :for="`oc-${line.key}`" x-text="`{{ __('stock.receipts.other_content', ['unit' => '__U__']) }}`.replace('__U__', line.product.unit)"></label>
                <input :id="`oc-${line.key}`" :name="`lines[${i}][other_content]`" x-model="line.other_content" inputmode="decimal">
                <span class="erreur" x-text="error(i, 'other_content')"></span>
              </div>
            </div>

            <div class="resultat" aria-live="polite">
              <span x-text="`+ ${fmt(added(line))} ${line.product.unit}`"></span>
              <span class="fleche" x-text="`Stock : ${fmt(before(line, i))} → ${fmt(before(line, i) + (added(line) || 0))}`"></span>
              <template x-if="isLastOf(line, i) && line.product.preorders.length">
                <span class="preco" x-text="(s => `${(s.count > 1 ? @js(__('preorders.receipt.will_serve')).split('|')[1] : @js(__('preorders.receipt.will_serve')).split('|')[0]).replace(':count', s.count)} (${fmt(s.qty)} ${s.unit}) · ${@js(__('preorders.receipt.left_for_sale')).replace(':qty', fmt(s.left)).replace(':unit', s.unit)}`)(serving(line.product.id))"></span>
              </template>
            </div>
          </article>
        </template>
        <p class="note" x-show="!lines.length">{{ __('stock.receipts.empty') }}</p>
      </section>
    </div>

    <aside class="resume carte" aria-labelledby="t-resume">
      <h2 id="t-resume">{{ __('stock.receipts.summary') }}</h2>
      <dl>
        <dt>{{ __('stock.receipts.sum_products') }}</dt><dd x-text="lines.length">0</dd>
        <dt>{{ __('stock.receipts.sum_packages') }}</dt><dd x-text="fmt(packages)">0</dd>
        <dt>{{ __('stock.receipts.sum_cost') }}</dt><dd class="num" x-text="costLabel">0,00 $</dd>
      </dl>
      <div class="preco-box" aria-live="polite">
        <b x-text="servings.length ? @js(__('preorders.receipt.box_title')) : @js(__('preorders.receipt.box_none'))"></b>
        <ul x-show="servings.length">
          <template x-for="s in servings" :key="s.name">
            <li x-text="@js(__('preorders.receipt.box_line')).replace(':name', s.name).replace(':served', s.count).replace(':pending', s.pending)"></li>
          </template>
        </ul>
      </div>
      <input type="hidden" name="notify_preorders" value="0">
      <label class="check"><input type="checkbox" name="notify_preorders" value="1" @checked(old('notify_preorders', true))> {{ __('stock.receipts.notify') }}</label>
      <label class="check"><input type="checkbox" name="print_labels" value="1" @checked(old('print_labels'))> {{ __('stock.receipts.print_labels') }}</label>
      <button class="btn btn-primaire" type="submit" :disabled="!lines.length">{{ __('stock.receipts.validate') }}</button>
      <p class="note">{{ __('stock.receipts.trace_note') }}</p>
    </aside>
  </div>
</form>
@endsection
