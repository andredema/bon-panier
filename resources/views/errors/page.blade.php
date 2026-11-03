<!doctype html>
<html lang="fr-CA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1F4D2E">
<meta name="robots" content="noindex">
<title>{{ __("errors.$code.title") }} — {{ __('app.shop_name') }}</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
{{-- Styles en ligne : la page doit s'afficher même si les ressources compilées sont en cause --}}
<style>
  * { box-sizing: border-box; }
  body { margin: 0; min-height: 100vh; min-height: 100dvh; display: grid; place-items: center; padding: 24px max(16px, env(safe-area-inset-left));
    background: #F7F2E8; color: #1E1B16; font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  main { width: min(520px, 100%); background: #fff; border: 1px solid #E6DCC8; border-radius: 16px; padding: 28px; display: flex; flex-direction: column; gap: 14px; }
  .code { font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #6B5F4F; margin: 0; }
  h1 { font-size: 26px; line-height: 1.15; margin: 0; color: #1F4D2E; }
  p { margin: 0; color: #4A4033; }
  .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px; }
  a { display: inline-flex; align-items: center; min-height: 44px; padding: 0 18px; border-radius: 10px; font-weight: 700; text-decoration: none; }
  .primaire { background: #1F4D2E; color: #fff; }
  .secondaire { border: 1.5px solid #1F4D2E; color: #1F4D2E; }
  a:focus-visible { outline: 3px solid #D49A3A; outline-offset: 2px; }
</style>
</head>
<body>
<main id="contenu">
  <p class="code">{{ __('errors.code', ['code' => $code]) }}</p>
  <h1>{{ __("errors.$code.title") }}</h1>
  <p>{{ __("errors.$code.message") }}</p>
  <div class="actions">
    <a class="primaire" href="/">{{ __('errors.home') }}</a>
    @if ($code === 403)<a class="secondaire" href="/gestion">{{ __('errors.admin') }}</a>@endif
  </div>
</main>
</body>
</html>
