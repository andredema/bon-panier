<!doctype html>
<html lang="fr-CA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1F4D2E">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title') — {{ __('app.shop_name') }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
@fonts
@vite(['resources/css/admin.css'])
</head>
<body>
<main class="connexion">
  <div class="carte">
    <div class="marque">
      <img src="{{ asset('images/icone-porteuse.svg') }}" alt="" width="56" height="56">
      <div><b>{{ __('app.shop_name') }}</b><span>{{ __('app.admin_tagline') }}</span></div>
    </div>
    <h1>@yield('heading')</h1>

    @if (session('status'))
      <div class="alerte alerte-ok" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div class="alerte alerte-erreur" role="alert" id="email-erreur">{{ $errors->first() }}</div>
    @endif

    @yield('form')
  </div>
</main>
</body>
</html>
