@extends('layouts.admin')

@section('title', __('app.users.title'))

@section('content')
  <div class="barre">
    <div><h1>{{ __('app.users.title') }}</h1><p>{{ __('app.users.intro') }}</p></div>
    <div class="actions"><a class="btn btn-primaire" href="{{ route('admin.users.create') }}">+ {{ __('app.users.new') }}</a></div>
  </div>

  <section class="bloc carte" aria-label="{{ __('app.users.title') }}">
    <table class="tableau-cartes">
      <thead>
        <tr>
          <th scope="col">{{ __('app.users.name') }}</th>
          <th scope="col">{{ __('app.users.email') }}</th>
          <th scope="col">{{ __('app.users.role') }}</th>
          <th scope="col">{{ __('app.users.status') }}</th>
          <th scope="col"><span class="sr">{{ __('app.users.modify') }}</span></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($users as $u)
          <tr>
            <td data-lib="{{ __('app.users.name') }}"><span><b>{{ $u->name }}</b>@if ($u->is(auth()->user())) <span class="note">({{ __('app.users.you') }})</span>@endif</span></td>
            <td data-lib="{{ __('app.users.email') }}">{{ $u->email }}</td>
            <td data-lib="{{ __('app.users.role') }}">{{ $u->role->label() }}</td>
            <td data-lib="{{ __('app.users.status') }}">
              @if ($u->is_active)
                <span class="etiquette e-prete">{{ __('app.users.active') }}</span>
              @else
                <span class="etiquette e-inactif">{{ __('app.users.inactive') }}</span>
              @endif
            </td>
            <td class="droite">@if ($u->is(auth()->user()) || auth()->user()->canManage($u))<a class="btn btn-secondaire mini" href="{{ route('admin.users.edit', $u) }}">{{ __('app.users.modify') }}<span class="sr"> {{ $u->name }}</span></a>@endif</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>
@endsection
