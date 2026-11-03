@extends('layouts.shop')

@section('content')
  <section class="accueil carte" aria-labelledby="t-accueil">
    <img src="{{ asset('images/logo-porteuse.svg') }}" alt="" width="140" height="140">
    <div>
      <h1 id="t-accueil">{{ __('app.home.soon_title') }}</h1>
      <p>{{ __('app.home.soon_text') }}</p>
      <p>{{ __('app.home.visit', ['address' => __('app.shop_address')]) }}</p>
    </div>
  </section>
@endsection
