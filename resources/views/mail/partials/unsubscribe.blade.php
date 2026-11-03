{{-- Pied des courriels aux clients abonnés : désabonnement en un clic --}}
@if ($customer?->isSubscribed())
  <p style="margin:16px 0 0;font-size:12px;color:#5C5246;">{{ __('shop.unsubscribe.mail_note') }} <a href="{{ $customer->unsubscribeUrl() }}" style="color:#1F4D2E;">{{ __('shop.unsubscribe.mail_link') }}</a></p>
@endif
