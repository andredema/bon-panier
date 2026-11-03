{{-- Liste des paiements d'une commande, avec reçu et suppression (gérant) --}}
@forelse ($payments as $p)
  <div class="ligne-paiement">
    <span>
      <b class="num">@money($p->amount_cents)</b> · {{ __('billing.methods.'.$p->method) }} · {{ \App\Support\Format::date($p->paid_at) }}
      @if ($p->reference)<br><small class="note">{{ $p->reference }}</small>@endif
      @if ($p->user)<br><small class="note">{{ __('billing.payment.by', ['name' => $p->user->name]) }}</small>@endif
    </span>
    <span class="actions-paiement">
      <a class="lien-btn" href="{{ route('admin.payments.receipt', $p) }}" target="_blank" rel="noopener">{{ __('billing.show.receipt') }}</a>
      @if (auth()->user()->isManager())
        <form method="POST" action="{{ route('admin.payments.destroy', $p) }}" onsubmit="return confirm(@js(__('billing.payment.delete_confirm')))">
          @csrf @method('DELETE')
          <button class="lien-btn lien-danger" type="submit">{{ __('billing.payment.delete') }}</button>
        </form>
      @endif
    </span>
  </div>
@empty
  <p class="note">{{ __('billing.show.no_payment') }}</p>
@endforelse
