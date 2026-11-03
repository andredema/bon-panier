@php
    // Entrées du menu ; « route » à null = écran prévu à une étape suivante.
    $icons = [
        'dashboard' => 'M3 11 12 4l9 7v9H5z',
        'orders' => 'M6 6h15l-2 9H8L6 3H3 M9 20h.01 M18 20h.01',
        'stock' => 'M3 7h11v9H3z M14 10h4l3 3v3h-7 M7 19a2 2 0 1 0 0-.1 M17 19a2 2 0 1 0 0-.1',
        'products' => 'M4 7l8-4 8 4v10l-8 4-8-4z M4 7l8 4 8-4 M12 11v10',
        'customers' => 'M9 11a4 4 0 1 0 0-.1 M2 21c0-4 3-6 7-6s7 2 7 6 M17 7a3 3 0 0 1 0 6 M22 21c0-3-2-5-4-5.5',
        'invoices' => 'M6 3h9l4 4v14H6z M9 12h7 M9 16h7 M9 8h3',
        'reports' => 'M4 20V10 M10 20V4 M16 20v-7 M22 20H2',
        'settings' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z M12 2v3 M12 19v3 M4.9 4.9l2.1 2.1 M17 17l2.1 2.1 M2 12h3 M19 12h3 M4.9 19.1 7 17 M17 7l2.1-2.1',
        'team' => 'M9 11a4 4 0 1 0 0-.1 M2 21c0-4 3-6 7-6s7 2 7 6 M16 11l2 2 4-4',
    ];
    $main = [
        ['key' => 'dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
        ['key' => 'orders', 'route' => null],
        ['key' => 'stock', 'route' => 'admin.receipts.create', 'active' => ['admin.receipts.*', 'admin.arrivals.*', 'admin.suppliers.*']],
        ['key' => 'products', 'route' => 'admin.products.index', 'active' => 'admin.products.*'],
        ['key' => 'customers', 'route' => null],
        ['key' => 'invoices', 'route' => null],
        ['key' => 'reports', 'route' => null],
    ];
    $manager = [
        ['key' => 'settings', 'route' => 'admin.settings.edit', 'active' => 'admin.settings.*'],
        ['key' => 'team', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
    ];
    $user = auth()->user();
@endphp
<!doctype html>
<html lang="fr-CA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1F4D2E">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title') — {{ __('app.shop_name') }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
@fonts
@vite(['resources/css/admin.css', 'resources/js/app.js'])
</head>
<body>
<a class="saut" href="#contenu">Aller au contenu</a>
<x-category-icons />

<div class="app">
  <aside class="menu" x-data="{ ouvert: false }" :class="{ ouvert }" @keydown.escape="ouvert = false">
    <a class="marque" href="{{ route('admin.dashboard') }}">
      <img src="{{ asset('images/icone-porteuse.svg') }}" alt="" width="44" height="44">
      <div><b>{{ __('app.shop_name') }}</b><span>{{ __('app.admin_tagline') }}</span></div>
    </a>
    <button class="btn menu-bascule" type="button" @click="ouvert = !ouvert" :aria-expanded="ouvert.toString()" aria-expanded="false" aria-controls="tiroir-menu">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
      {{ __('app.nav.menu') }}
    </button>

    <div class="tiroir-menu" id="tiroir-menu">
      @foreach ([['items' => $main], ['items' => $manager, 'title' => __('app.nav.admin_section'), 'only' => 'manager']] as $group)
        @continue(($group['only'] ?? null) === 'manager' && ! $user->isManager())
        <nav aria-label="{{ $group['title'] ?? __('app.nav.label') }}">
          @isset($group['title'])<div class="sous" aria-hidden="true">{{ $group['title'] }}</div>@endisset
          @foreach ($group['items'] as $item)
            @php $current = $item['route'] && request()->routeIs(...(array) $item['active']); @endphp
            @if ($item['route'])
              <a href="{{ route($item['route']) }}" @if ($current) aria-current="page" @endif>
            @else
              <a aria-disabled="true">
            @endif
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icons[$item['key']] }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                {{ __('app.nav.'.$item['key']) }}
                @unless ($item['route'])<span class="sr"> ({{ __('app.nav.soon') }})</span>@endunless
              </a>
          @endforeach
        </nav>
      @endforeach

      <div class="pied">
        <span>{{ __('app.nav.connected_as', ['name' => $user->name]) }} · <b>{{ $user->role->label() }}</b></span>
        <span>{{ ucfirst(\App\Support\Format::longDate(now())) }}</span>
        <a class="lien-btn" href="{{ route('shop.home') }}">{{ __('app.nav.shop') }}</a>
        <form method="POST" action="{{ route('admin.logout') }}">
          @csrf
          <button class="lien-btn" type="submit">{{ __('app.nav.logout') }}</button>
        </form>
      </div>
    </div>
  </aside>

  <main class="contenu" id="contenu" tabindex="-1">
    @if (session('status'))
      <div class="alerte alerte-ok" role="status">{{ session('status') }}</div>
    @endif
    @if (session('warnings'))
      <div class="alerte alerte-avert" role="status">
        <b>{{ __('catalog.products.warnings') }}</b>
        <ul>@foreach (session('warnings') as $w)<li>{{ $w }}</li>@endforeach</ul>
      </div>
    @endif
    @yield('content')
  </main>
</div>
</body>
</html>
