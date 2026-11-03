@extends('layouts.admin')

@section('title', __('app.nav.dashboard'))

@section('content')
  <div class="barre">
    <div>
      <h1>{{ __('app.dashboard.greeting', ['name' => auth()->user()->name]) }}</h1>
      <p>{{ __('app.dashboard.intro', ['date' => ucfirst(\App\Support\Format::longDate(now()))]) }}</p>
    </div>
  </div>

  <section class="bloc carte" aria-labelledby="t-socle">
    <h2 id="t-socle">{{ __('app.dashboard.socle_title') }}</h2>
    <p class="note">{{ __('app.dashboard.socle_text') }}</p>
  </section>
@endsection
