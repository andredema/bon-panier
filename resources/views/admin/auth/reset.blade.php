@extends('layouts.auth')

@section('title', __('app.reset.new_title'))
@section('heading', __('app.reset.new_title'))

@section('form')
    <form method="POST" action="{{ route('password.update') }}" novalidate>
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <div class="champ">
        <label for="email">{{ __('app.login.email') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" required
               @error('email') aria-invalid="true" aria-describedby="email-erreur" @enderror>
      </div>
      <div class="champ">
        <label for="password">{{ __('app.reset.new_password') }}</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required autofocus aria-describedby="aide-mdp">
        <span class="note" id="aide-mdp">{{ __('app.reset.rule') }}</span>
      </div>
      <div class="champ">
        <label for="password_confirmation">{{ __('app.reset.confirm') }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
      </div>
      <button class="btn btn-primaire" type="submit">{{ __('app.reset.save') }}</button>
    </form>
@endsection
