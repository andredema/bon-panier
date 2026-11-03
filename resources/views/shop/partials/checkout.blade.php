{{-- Tiroir de finalisation : total fixé en bas, au-dessus de « Envoyer ma commande » --}}
<aside class="tiroir" id="tiroir" role="dialog" aria-modal="true" aria-labelledby="t-tiroir" :inert="!tiroir">
  <form method="POST" action="{{ route('shop.checkout') }}" novalidate style="display:contents" x-data="{ mode: @js(old('fulfilment', 'ramassage')) }">
    @csrf
    <input type="hidden" name="cart_signature" :value="cart.signature">

    <div class="tiroir-tete">
      <div><h2 id="t-tiroir">{{ __('shop.checkout.title') }}</h2><div class="etapes">{!! __('shop.checkout.step') !!}</div></div>
      <button class="x" type="button" id="fermer" @click="fermer()" aria-label="{{ __('shop.checkout.close') }}">×</button>
    </div>

    <div class="tiroir-corps">
      @if ($errors->any())
        <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
      @endif

      <div class="bloc-t"><h3>{{ __('shop.checkout.your_cart') }}</h3><div class="lignes">@include('shop.partials.cart-lines')</div></div>

      <div class="bloc-t">
        <h3>{{ __('shop.checkout.reception') }}</h3>
        <div class="choix" role="radiogroup" aria-label="{{ __('shop.checkout.reception') }}">
          <label><input type="radio" name="fulfilment" value="ramassage" x-model="mode"> <span>{{ __('shop.checkout.pickup') }}<small>{{ __('shop.checkout.pickup_note', ['address' => $checkout['address']]) }}</small></span></label>
          @if ($checkout['delivery'])
            <label :class="{ inactif: cart.has_frozen }"><input type="radio" name="fulfilment" value="livraison" x-model="mode" :disabled="cart.has_frozen"> <span>{{ __('shop.checkout.delivery') }}<small>{{ __('shop.checkout.delivery_note', ['zone' => $checkout['delivery_zone'] ?: '—', 'fee' => $checkout['delivery_fee']]) }}</small></span></label>
          @endif
        </div>
        @if ($checkout['delivery'])
          <p class="note-tiroir" x-show="cart.has_frozen" x-cloak>{{ __('shop.checkout.frozen_note') }}</p>
        @endif
        @error('fulfilment')<span class="erreur">{{ $message }}</span>@enderror
      </div>

      @if ($checkout['delivery'])
        <div class="champ" x-show="mode === 'livraison'" x-cloak>
          <label for="delivery_address">{{ __('shop.checkout.delivery_address') }}</label>
          <input id="delivery_address" name="delivery_address" value="{{ old('delivery_address') }}" autocomplete="street-address" maxlength="255">
          @error('delivery_address')<span class="erreur">{{ $message }}</span>@enderror
        </div>
      @endif

      <div class="champ">
        <label for="slot">{{ __('shop.checkout.slot') }}</label>
        <select id="slot" name="slot" x-show="slots.length">
          <template x-for="s in slots" :key="s.value">
            <option :value="s.value" x-text="s.label" :selected="s.value === @js(old('slot'))"></option>
          </template>
        </select>
        <p class="note-tiroir" x-show="!slots.length" x-cloak>{{ __('shop.checkout.no_slot') }}</p>
        <p class="note-tiroir" x-show="cart.has_preorder" x-cloak>{{ __('shop.checkout.preorder_slot_note') }}</p>
        @error('slot')<span class="erreur">{{ $message }}</span>@enderror
      </div>

      <div class="bloc-t">
        <h3>{{ __('shop.checkout.payment') }}</h3>
        <div class="choix" role="radiogroup" aria-label="{{ __('shop.checkout.payment') }}">
          <label><input type="radio" name="payment_method" value="interac" @checked(old('payment_method', 'interac') === 'interac')> <span>{{ __('shop.checkout.interac') }}<small>{{ __('shop.checkout.interac_note') }}</small></span></label>
          @if ($checkout['pay_on_handover'])
            <label><input type="radio" name="payment_method" value="remise" @checked(old('payment_method') === 'remise')> <span>{{ __('shop.checkout.on_handover') }}<small>{{ __('shop.checkout.on_handover_note') }}</small></span></label>
          @endif
        </div>
        @error('payment_method')<span class="erreur">{{ $message }}</span>@enderror
      </div>

      <div class="champ">
        <label for="nom">{{ __('shop.checkout.name') }}</label>
        <input id="nom" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120" required>
        @error('name')<span class="erreur">{{ $message }}</span>@enderror
      </div>
      <div class="deux">
        <div class="champ">
          <label for="tel">{{ __('shop.checkout.phone') }}</label>
          <input id="tel" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="40" required>
          @error('phone')<span class="erreur">{{ $message }}</span>@enderror
        </div>
        <div class="champ">
          <label for="courriel">{{ __('shop.checkout.email') }}</label>
          <input id="courriel" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
          @error('email')<span class="erreur">{{ $message }}</span>@enderror
        </div>
      </div>
      <div class="champ">
        <label for="notes">{{ __('shop.checkout.notes') }}</label>
        <textarea id="notes" name="notes" maxlength="500" rows="2">{{ old('notes') }}</textarea>
      </div>
      <label class="consent"><input type="checkbox" name="consent" value="1" required> <span>{!! __('shop.checkout.consent', ['url' => route('shop.privacy')]) !!}</span></label>
      @error('consent')<span class="erreur">{{ $message }}</span>@enderror
    </div>

    <div class="totaux" aria-live="polite">
      <div><span>{{ __('shop.cart.subtotal') }}</span><span class="num" x-text="cart.labels.subtotal"></span></div>
      <div><span>{{ __('shop.cart.taxes') }}</span><span class="num" x-text="cart.labels.taxes"></span></div>
      <div class="total"><span>{{ __('shop.cart.total') }}</span><span class="num" x-text="cart.labels.total"></span></div>
      <button class="btn btn-primaire" type="submit" :disabled="!cart.count || cart.has_unavailable || !slots.length">{{ __('shop.checkout.send') }}</button>
    </div>
  </form>
</aside>
