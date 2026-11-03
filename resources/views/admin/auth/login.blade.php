<!doctype html>
<html lang="fr-CA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1F4D2E">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('app.login.page_title') }} — {{ __('app.shop_name') }}</title>
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
    <h1>{{ __('app.login.title') }}</h1>

    @if ($errors->any())
      <div class="alerte alerte-erreur" role="alert" id="email-erreur">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" novalidate>
      @csrf
      <div class="champ">
        <label for="email">{{ __('app.login.email') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus
               @error('email') aria-invalid="true" aria-describedby="email-erreur" @enderror>
      </div>
      <div class="champ">
        <label for="password">{{ __('app.login.password') }}</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <label class="case"><input type="checkbox" name="remember" value="1"> {{ __('app.login.remember') }}</label>
      <button class="btn btn-primaire" type="submit">{{ __('app.login.submit') }}</button>
    </form>
  </div>
</main>
</body>
</html>
