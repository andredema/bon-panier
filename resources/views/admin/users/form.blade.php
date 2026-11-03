@extends('layouts.admin')

@php $editing = $user->exists; @endphp

@section('title', $editing ? __('app.users.edit') : __('app.users.new'))

@section('content')
  <div class="barre">
    <div><h1>{{ $editing ? __('app.users.edit') : __('app.users.new') }}</h1>@if ($editing)<p>{{ $user->email }}</p>@endif</div>
  </div>

  <form class="bloc carte" method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    @if ($errors->any())
      <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="grille-form">
      <div class="champ">
        <label for="name">{{ __('app.users.name') }}</label>
        <input id="name" name="name" value="{{ old('name', $user->name) }}" autocomplete="off" required>
        @error('name')<span class="erreur">{{ $message }}</span>@enderror
      </div>
      <div class="champ">
        <label for="email">{{ __('app.users.email') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="off" required>
        @error('email')<span class="erreur">{{ $message }}</span>@enderror
      </div>
      <div class="champ">
        <label for="role">{{ __('app.users.role') }}</label>
        <select id="role" name="role" aria-describedby="role-aide">
          @foreach ($roles as $role)
            <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
          @endforeach
        </select>
        <span class="note" id="role-aide">{{ __('app.users.roles_help') }}</span>
        @error('role')<span class="erreur">{{ $message }}</span>@enderror
      </div>
      @if ($editing)
        <div class="champ">
          <span>{{ __('app.users.status') }}</span>
          <input type="hidden" name="is_active" value="0">
          <label class="case"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> {{ __('app.users.is_active') }}</label>
        </div>
      @endif
      <div class="champ">
        <label for="password">{{ __('app.users.password') }}</label>
        <input id="password" name="password" type="password" autocomplete="new-password" aria-describedby="password-aide" @unless ($editing) required @endunless>
        <span class="note" id="password-aide">{{ $editing ? __('app.users.password_keep') : __('app.users.password_hint') }}</span>
        @error('password')<span class="erreur">{{ $message }}</span>@enderror
      </div>
      <div class="champ">
        <label for="password_confirmation">{{ __('app.users.password_confirmation') }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
      </div>
    </div>

    <div class="barre">
      <a href="{{ route('admin.users.index') }}">{{ __('app.users.cancel') }}</a>
      <button class="btn btn-primaire" type="submit">{{ __('app.users.save') }}</button>
    </div>
  </form>
@endsection
