@extends('layouts.auth')

@section('title', __('app.reset.forgot_title'))
@section('heading', __('app.reset.forgot_title'))

@section('form')
    <p class="note">{{ __('app.reset.forgot_intro') }}</p>
    <form method="POST" action="{{ route('password.email') }}" novalidate>
      @csrf
      <div class="champ">
        <label for="email">{{ __('app.login.email') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus
               @error('email') aria-invalid="true" aria-describedby="email-erreur" @enderror>
      </div>
      <button class="btn btn-primaire" type="submit">{{ __('app.reset.send') }}</button>
      <p class="lien-aide"><a href="{{ route('login') }}">{{ __('app.reset.back') }}</a></p>
    </form>
@endsection
