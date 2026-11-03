<nav class="onglets" aria-label="{{ __('billing.title') }}">
  <a href="{{ route('admin.invoices.index') }}" @if (request()->routeIs('admin.invoices.*')) aria-current="page" @endif>{{ __('billing.tabs.invoices') }}</a>
  @unless (auth()->user()->isAccountant())
  <a href="{{ route('admin.payments.reconcile') }}" @if (request()->routeIs('admin.payments.*')) aria-current="page" @endif>{{ __('billing.tabs.interac') }}</a>
  @endunless
</nav>
