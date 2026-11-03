<div class="barre">
  <div><h1>{{ __('catalog.settings.title') }}</h1><p>{{ __('catalog.settings.intro') }}</p></div>
</div>
<nav class="onglets" aria-label="{{ __('catalog.settings.title') }}">
  @foreach ([
      'admin.settings.edit' => __('catalog.settings.groups.shop').' et taxes',
      'admin.settings.categories.index' => __('catalog.categories.title'),
      'admin.settings.units.index' => __('catalog.units.title'),
  ] as $route => $label)
    <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>
@if ($errors->any())
  <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
@endif
