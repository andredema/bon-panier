{{-- Formulaire d'encaissement (fiche facture, fiche commande, rapprochement) --}}
<form class="form-paiement" method="POST" action="{{ route('admin.payments.store', $order) }}" novalidate>
  @csrf
  <div class="grille-form">
    <div class="champ">
      <label for="method-{{ $order->id }}">{{ __('billing.payment.method') }}</label>
      <select id="method-{{ $order->id }}" name="method">
        @foreach (\App\Models\Payment::METHODS as $m)
          <option value="{{ $m }}" @selected(old('method', $defaultMethod ?? ($order->payment_method === 'interac' ? 'interac' : 'comptant')) === $m)>{{ __('billing.methods.'.$m) }}</option>
        @endforeach
      </select>
    </div>
    <div class="champ">
      <label for="amount-{{ $order->id }}">{{ __('billing.payment.amount') }}</label>
      <input id="amount-{{ $order->id }}" name="amount" inputmode="decimal" value="{{ old('amount', number_format(max(0, $due) / 100, 2, ',', '')) }}">
      @error('amount')<span class="erreur">{{ $message }}</span>@enderror
    </div>
    <div class="champ">
      <label for="paid-{{ $order->id }}">{{ __('billing.payment.paid_at') }}</label>
      <input id="paid-{{ $order->id }}" name="paid_at" type="date" value="{{ old('paid_at', today()->toDateString()) }}" max="{{ today()->toDateString() }}">
      @error('paid_at')<span class="erreur">{{ $message }}</span>@enderror
    </div>
    <div class="champ">
      <label for="ref-{{ $order->id }}">{{ __('billing.payment.reference') }}</label>
      <input id="ref-{{ $order->id }}" name="reference" value="{{ old('reference') }}" maxlength="120">
    </div>
  </div>
  <button class="btn btn-primaire mini" type="submit">{{ __('billing.payment.save') }}</button>
</form>
