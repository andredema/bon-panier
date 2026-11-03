@extends('layouts.admin')

@php
    $config = [
        'lines' => $lines,
        'searchUrl' => route('admin.orders.products'),
        'previewUrl' => route('admin.orders.preview'),
        'texts' => [
            'unavailable' => __('orders.counter.unavailable'),
            'preorder' => __('orders.counter.preorder', ['date' => ':date']),
        ],
    ];
@endphp

@section('title', __('orders.counter.title'))

@section('content')
<form method="POST" action="{{ route('admin.orders.store') }}" novalidate x-data="counterOrder(@js($config))" x-init="refresh()" class="comptoir">
  @csrf
  <div class="barre">
    <div>
      <p class="fil"><a href="{{ route('admin.orders.index') }}">{{ __('orders.show.back') }}</a></p>
      <h1>{{ __('orders.counter.title') }}</h1>
      <p>{{ __('orders.counter.intro') }}</p>
    </div>
  </div>

  @if ($errors->any())
    <div class="alerte alerte-erreur" role="alert">
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="disposition-entree">
    <div class="col">
      <section class="bloc carte" aria-labelledby="t-lignes">
        <h2 id="t-lignes">{{ __('orders.counter.lines') }}</h2>
        <div class="scan">
          <label for="recherche" class="sr">{{ __('orders.counter.search') }}</label>
          <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="#1F4D2E" stroke-width="2"/><path d="m20 20-4-4" stroke="#1F4D2E" stroke-width="2" stroke-linecap="round"/></svg>
          <input id="recherche" type="search" autocomplete="off" x-model="q" @input="search" @keydown.enter.prevent="results.length === 1 && add(results[0])" placeholder="{{ __('orders.counter.search_placeholder') }}">
        </div>
        <ul class="resultats" x-show="results.length" x-cloak>
          <template x-for="p in results" :key="p.id">
            <li><button type="button" @click="add(p)"><span><small x-text="p.brand"></small> <b x-text="p.name"></b></span><span class="note" x-text="p.available"></span></button></li>
          </template>
        </ul>

        <template x-for="(line, i) in lines" :key="line.key">
          <div class="ligne-comptoir">
            <div class="haut">
              <div><b x-text="line.name"></b> <small class="note" x-text="line.available"></small> <small class="preco-txt" x-text="status(line)"></small></div>
              <button class="suppr" type="button" @click="remove(i)" :aria-label="@js(__('orders.counter.remove', ['name' => '__N__'])).replace('__N__', line.name)">×</button>
            </div>
            <div class="champs">
              <div class="champ">
                <label :for="`f-${line.key}`">{{ __('orders.counter.format') }}</label>
                <select :id="`f-${line.key}`" :name="`lines[${i}][format_id]`" x-model="line.format_id" @change="changeFormat(line)">
                  <template x-for="f in line.formats" :key="f.id"><option :value="f.id" x-text="f.label" :selected="String(f.id) === String(line.format_id)"></option></template>
                </select>
              </div>
              <div class="champ">
                <label :for="`q-${line.key}`">{{ __('orders.counter.qty') }}</label>
                <input :id="`q-${line.key}`" :name="`lines[${i}][qty]`" x-model="line.qty" @change="refresh()" inputmode="decimal">
              </div>
            </div>
          </div>
        </template>
        <p class="note" x-show="!lines.length">{{ __('orders.counter.empty') }}</p>
        <ul class="alerte alerte-erreur" x-show="errors.length" x-cloak role="alert"><template x-for="e in errors"><li x-text="e"></li></template></ul>
      </section>

      <section class="bloc carte" aria-labelledby="t-client">
        <h2 id="t-client">{{ __('orders.counter.customer') }}</h2>
        <div class="grille-form">
          <div class="champ"><label for="name">{{ __('orders.counter.name') }}</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="off" required maxlength="120"></div>
          <div class="champ"><label for="phone">{{ __('orders.counter.phone') }}</label><input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="off" required maxlength="40"></div>
          <div class="champ plein"><label for="email">{{ __('orders.counter.email') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="off" maxlength="255"></div>
        </div>
        <label class="check"><input type="checkbox" name="consent" value="1" @checked(old('consent'))> {{ __('orders.counter.consent') }}</label>
      </section>

      <section class="bloc carte" aria-labelledby="t-reception">
        <h2 id="t-reception">{{ __('orders.counter.reception') }}</h2>
        <div class="grille-form">
          <div class="champ">
            <label for="fulfilment">{{ __('orders.counter.reception') }}</label>
            <select id="fulfilment" name="fulfilment">
              <option value="ramassage">{{ __('shop.checkout.pickup') }}</option>
              @if ($delivery)<option value="livraison" @selected(old('fulfilment') === 'livraison')>{{ __('shop.checkout.delivery') }}</option>@endif
            </select>
          </div>
          <div class="champ"><label for="slot">{{ __('orders.counter.slot') }}</label><input id="slot" name="slot" type="datetime-local" value="{{ old('slot', $now) }}" required></div>
          @if ($delivery)
            <div class="champ plein"><label for="delivery_address">{{ __('shop.checkout.delivery_address') }}</label><input id="delivery_address" name="delivery_address" value="{{ old('delivery_address') }}" maxlength="255"></div>
          @endif
          <div class="champ">
            <label for="payment_method">{{ __('orders.counter.payment') }}</label>
            <select id="payment_method" name="payment_method">
              <option value="remise" @selected(old('payment_method', 'remise') === 'remise')>{{ __('orders.show.on_handover') }}</option>
              <option value="interac" @selected(old('payment_method') === 'interac')>{{ __('orders.show.interac') }}</option>
            </select>
          </div>
          <div class="champ plein"><label for="notes">{{ __('orders.counter.notes') }}</label><input id="notes" name="notes" value="{{ old('notes') }}" maxlength="500"></div>
        </div>
      </section>
    </div>

    <aside class="resume carte" aria-labelledby="t-totaux">
      <h2 id="t-totaux">{{ __('orders.counter.totals') }}</h2>
      <dl aria-live="polite">
        <dt>{{ __('orders.counter.subtotal') }}</dt><dd class="num" x-text="summary ? summary.labels.subtotal : '0,00 $'"></dd>
        <dt>{{ __('orders.counter.taxes') }}</dt><dd class="num" x-text="summary ? summary.labels.taxes : '0,00 $'"></dd>
        <dt>{{ __('orders.counter.total') }}</dt><dd class="num" x-text="summary ? summary.labels.total : '0,00 $'"></dd>
      </dl>
      <input type="hidden" name="hand_over" value="0">
      <label class="check"><input type="checkbox" name="hand_over" value="1" @checked(old('hand_over'))> <span>{{ __('orders.counter.hand_over') }}<br><small class="note">{{ __('orders.counter.hand_over_hint') }}</small></span></label>
      <div class="champ">
        <label for="collected">{{ __('orders.counter.collected') }}</label>
        <select id="collected" name="collected">
          <option value="">{{ __('orders.counter.not_collected') }}</option>
          @foreach (\App\Models\Payment::METHODS as $m)
            <option value="{{ $m }}" @selected(old('collected') === $m)>{{ __('billing.methods.'.$m) }}</option>
          @endforeach
        </select>
        <span class="note">{{ __('orders.counter.collected_hint') }}</span>
      </div>
      <input type="hidden" name="send_mail" value="0">
      <label class="check"><input type="checkbox" name="send_mail" value="1" @checked(old('send_mail', true))> {{ __('orders.counter.send_mail') }}</label>
      <button class="btn btn-primaire" type="submit" :disabled="!lines.length">{{ __('orders.counter.submit') }}</button>
    </aside>
  </div>
</form>
@endsection
