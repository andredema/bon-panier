@extends('layouts.admin')

@section('title', __('catalog.units.title'))

@section('content')
  @include('admin.settings._tabs')

  <section class="bloc carte" aria-labelledby="t-unites">
    <div class="bloc-tete"><h2 id="t-unites">{{ __('catalog.units.title') }}</h2></div>
    <p class="note">{{ __('catalog.units.intro') }}</p>

    <div class="unites-g">
      @foreach ($units as $u)
        <form class="unite-c" method="POST" action="{{ route('admin.settings.units.update', $u) }}" aria-label="{{ $u->label }}">
          @csrf
          @method('PUT')
          <label class="bascule"><input type="checkbox" name="is_active" value="1" @checked($u->is_active)> <b>{{ $u->label }}</b></label>
          <small>{{ __('catalog.unit_kinds.'.$u->kind->value) }}@if ($u->is_system) · {{ __('catalog.units.system') }}@endif
            · {{ trans_choice('catalog.units.in_use', $usage[$u->id] ?? 0) }}</small>
          <div class="champ"><label for="l-{{ $u->id }}">{{ __('catalog.units.label') }}</label><input id="l-{{ $u->id }}" name="label" value="{{ $u->label }}" required maxlength="60"></div>
          <div class="deux-champs">
            <div class="champ"><label for="s-{{ $u->id }}">{{ __('catalog.units.short_label') }}</label><input id="s-{{ $u->id }}" name="short_label" value="{{ $u->short_label }}" required maxlength="20"></div>
            <div class="champ"><label for="d-{{ $u->id }}">{{ __('catalog.units.default_step') }}</label><input id="d-{{ $u->id }}" name="default_step" value="{{ \App\Support\Format::quantity($u->default_step) }}" inputmode="decimal"></div>
          </div>
          <div class="champ"><label for="x-{{ $u->id }}">{{ __('catalog.units.description') }}</label><input id="x-{{ $u->id }}" name="description" value="{{ $u->description }}" maxlength="255"></div>
          @unless ($u->isMeasure())
            <label class="case"><input type="checkbox" name="allows_decimals" value="1" @checked($u->allows_decimals)> {{ __('catalog.units.allows_decimals') }}</label>
          @endunless
          <button class="btn btn-secondaire mini" type="submit">{{ __('catalog.units.save') }}<span class="sr"> {{ $u->label }}</span></button>
        </form>
      @endforeach

      <form class="unite-c unite-nouvelle" method="POST" action="{{ route('admin.settings.units.store') }}" aria-labelledby="t-new-unit">
        @csrf
        <b id="t-new-unit">{{ __('catalog.units.new') }}</b>
        <small>{{ __('catalog.units.new_hint') }}</small>
        <div class="champ"><label for="new-label">{{ __('catalog.units.label') }}</label><input id="new-label" name="label" value="{{ old('label') }}" required maxlength="60"></div>
        <div class="deux-champs">
          <div class="champ"><label for="new-short">{{ __('catalog.units.short_label') }}</label><input id="new-short" name="short_label" value="{{ old('short_label') }}" required maxlength="20"></div>
          <div class="champ"><label for="new-step">{{ __('catalog.units.default_step') }}</label><input id="new-step" name="default_step" value="{{ old('default_step', '1') }}" inputmode="decimal"></div>
        </div>
        <label class="case"><input type="checkbox" name="allows_decimals" value="1"> {{ __('catalog.units.allows_decimals') }}</label>
        <button class="btn btn-primaire mini" type="submit">{{ __('catalog.units.add') }}</button>
      </form>
    </div>
  </section>
@endsection
