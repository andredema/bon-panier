@extends('layouts.auth')

@section('title', __('app.login.page_title'))
@section('heading', __('app.login.title'))

@section('form')
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
      <p class="lien-aide"><a href="{{ route('password.request') }}">{{ __('app.reset.forgot_link') }}</a></p>
    </form>
@endsection
