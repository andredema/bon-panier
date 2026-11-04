@extends('layouts.shop')

@section('title', __('shop.unsubscribe.title'))

@section('content')
<main class="page-simple" id="contenu">
  <section class="carte" aria-labelledby="t-desabo">
    <h1 id="t-desabo">{{ __('shop.unsubscribe.title') }}</h1>
    <p>{{ __('shop.unsubscribe.text') }}</p>
    <p><a class="btn btn-primaire" href="{{ route('shop.home') }}">{{ __('shop.unsubscribe.back') }}</a></p>
  </section>
</main>
@endsection
