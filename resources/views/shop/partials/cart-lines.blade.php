{{-- Lignes du panier (panneau latéral et tiroir) ; précommandes regroupées à part --}}
@foreach (['lignesDispo' => null, 'lignesPreco' => __('shop.cart.preorders')] as $groupe => $titre)
  @if ($titre)
    <div class="sep-preco" x-show="lignesPreco.length" x-cloak>{{ $titre }}</div>
  @endif
  <template x-for="l in {{ $groupe }}" :key="l.format_id">
    <div class="ligne">
      <b x-text="l.name"></b><span class="num" x-text="l.line_total_label"></span>
      <small x-text="`${l.label} · ${l.channel === 'gros' ? @js(__('shop.cart.channel_wholesale')) : @js(__('shop.cart.channel_retail'))}${l.preorder && l.expected_label ? ' · ' + @js(__('shop.cart.arrives', ['date' => '__D__'])).replace('__D__', l.expected_label) : ''}`"></small><span></span>
      <span class="note-preco" x-show="l.partial_preorder" x-text="l.partial_preorder ? @js(__('shop.cart.partial', ['qty' => '__Q__', 'date' => '__D__'])).replace('__Q__', l.parts[1].qty_label).replace('__D__', l.expected_label) : ''"></span>
      <div class="actions-l">
        <div class="pas" role="group" :aria-label="@js(__('shop.card.qty_of', ['name' => '__N__'])).replace('__N__', l.name)">
          <button type="button" @click="changerLigne(l, -1)" aria-label="{{ __('shop.cart.remove_one') }}" :disabled="occupe">−</button>
          <output class="num" x-text="l.qty_label"></output>
          <button type="button" @click="changerLigne(l, 1)" aria-label="{{ __('shop.cart.add_one') }}" :disabled="occupe">+</button>
        </div>
        <button class="retirer" type="button" @click="retirer(l)" :disabled="occupe">{{ __('shop.cart.remove') }}<span class="sr" x-text="' ' + l.name"></span></button>
      </div>
    </div>
  </template>
@endforeach
<p class="vide" x-show="!cart.count">{{ __('shop.cart.empty') }}</p>
