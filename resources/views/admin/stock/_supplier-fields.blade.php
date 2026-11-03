<div class="champ"><label for="{{ $p }}-name">{{ __('stock.suppliers.name') }}</label><input id="{{ $p }}-name" name="name" value="{{ $s?->name }}" required maxlength="120"></div>
<div class="champ"><label for="{{ $p }}-contact">{{ __('stock.suppliers.contact') }}</label><input id="{{ $p }}-contact" name="contact" value="{{ $s?->contact }}" maxlength="120"></div>
<div class="deux-champs">
  <div class="champ"><label for="{{ $p }}-phone">{{ __('stock.suppliers.phone') }}</label><input id="{{ $p }}-phone" name="phone" type="tel" value="{{ $s?->phone }}" maxlength="40"></div>
  <div class="champ"><label for="{{ $p }}-delay">{{ __('stock.suppliers.delay') }}</label><input id="{{ $p }}-delay" name="usual_delay_days" inputmode="numeric" value="{{ $s?->usual_delay_days }}"></div>
</div>
<div class="champ"><label for="{{ $p }}-email">{{ __('stock.suppliers.email') }}</label><input id="{{ $p }}-email" name="email" type="email" value="{{ $s?->email }}" maxlength="255"></div>
<div class="champ"><label for="{{ $p }}-notes">{{ __('stock.suppliers.notes') }}</label><input id="{{ $p }}-notes" name="notes" value="{{ $s?->notes }}" maxlength="500"></div>
