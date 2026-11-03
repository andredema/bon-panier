<!doctype html>
<html lang="fr-CA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1F4D2E">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', __('app.home.title')) — {{ __('app.shop_name') }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
@fonts
@vite(['resources/css/shop.css', 'resources/js/app.js'])
</head>
<body>
<a class="saut" href="#contenu">Aller au contenu</a>

<header class="entete">
  <div class="entete-in">
    <a class="marque" href="{{ route('shop.home') }}">
      <img src="{{ asset('images/icone-porteuse.svg') }}" alt="" width="46" height="46">
      <div><b>{{ __('app.shop_name') }}</b><span>{{ __('app.shop_tagline') }}</span></div>
    </a>
    <div class="entete-outils">@yield('header-tools')</div>
  </div>
</header>

<main class="corps" id="contenu">
  @yield('content')
</main>

<footer class="pied-public">{{ __('app.shop_name') }} · Épicerie africaine · {{ __('app.shop_address') }}</footer>
</body>
</html>
