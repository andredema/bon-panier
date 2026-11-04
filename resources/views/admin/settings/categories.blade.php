@extends('layouts.admin')

@php
    $palettes = \App\Enums\CategoryPalette::cases();
    $icons = \App\Models\Category::ICONS;
@endphp

@section('title', __('catalog.categories.title'))

@section('content')
  @include('admin.settings._tabs')

  <section class="bloc carte" aria-labelledby="t-cats">
    <div class="bloc-tete"><h2 id="t-cats">{{ __('catalog.categories.title') }}</h2><span class="note">{{ __('catalog.categories.intro') }}</span></div>
    @if ($errors->any())<div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>@endif

    <div class="liste-cats">
      @foreach ($categories as $c)
        <form class="ligne-cat" method="POST" action="{{ route('admin.settings.categories.update', $c) }}" enctype="multipart/form-data" aria-label="{{ $c->name }}">
          @csrf
          @method('PUT')
          <span class="vignette grande" style="background:{{ $c->palette->background() }};color:{{ $c->palette->ink() }}" aria-hidden="true">@if ($c->imageUrl())<img src="{{ $c->imageUrl() }}" alt="" width="48" height="48">@else<svg width="26" height="26"><use href="#i-{{ $c->icon }}"/></svg>@endif</span>
          <div class="champ"><label for="n-{{ $c->id }}">{{ __('catalog.categories.name') }}</label><input id="n-{{ $c->id }}" name="name" value="{{ $c->name }}" required maxlength="80"></div>
          <div class="champ"><label for="i-{{ $c->id }}">{{ __('catalog.categories.icon') }}</label>
            <select id="i-{{ $c->id }}" name="icon">@foreach ($icons as $i)<option value="{{ $i }}" @selected($c->icon === $i)>{{ __('catalog.icons.'.$i) }}</option>@endforeach</select></div>
          <div class="champ"><label for="p-{{ $c->id }}">{{ __('catalog.categories.palette') }}</label>
            <select id="p-{{ $c->id }}" name="palette">@foreach ($palettes as $p)<option value="{{ $p->value }}" @selected($c->palette === $p)>{{ $p->label() }}</option>@endforeach</select></div>
          <div class="champ f-image"><label for="img-{{ $c->id }}">{{ __('catalog.categories.image') }}</label><input id="img-{{ $c->id }}" name="image" type="file" accept="image/jpeg,image/png,image/webp">
            @if ($c->image_path)<label class="check"><input type="checkbox" name="remove_image" value="1"> {{ __('catalog.categories.remove_image') }}</label>@endif</div>
          <div class="champ f-court"><label for="o-{{ $c->id }}">{{ __('catalog.categories.sort_order') }}</label><input id="o-{{ $c->id }}" name="sort_order" value="{{ $c->sort_order }}" inputmode="numeric"></div>
          <span class="note">{{ __('catalog.categories.products') }} : {{ $c->products_count }}</span>
          <div class="actions-cat">
            <button class="btn btn-secondaire mini" type="submit">{{ __('catalog.categories.save') }}</button>
            @if ($c->products_count === 0)
              <button class="lien-btn lien-danger" type="submit" form="del-{{ $c->id }}">{{ __('catalog.categories.delete') }}<span class="sr"> {{ $c->name }}</span></button>
            @endif
          </div>
        </form>
        @if ($c->products_count === 0)
          <form id="del-{{ $c->id }}" method="POST" action="{{ route('admin.settings.categories.destroy', $c) }}" hidden>@csrf @method('DELETE')</form>
        @endif
      @endforeach
    </div>
  </section>

  <form class="bloc carte" method="POST" action="{{ route('admin.settings.categories.store') }}" enctype="multipart/form-data" aria-labelledby="t-new-cat">
    @csrf
    <h2 id="t-new-cat">{{ __('catalog.categories.new') }}</h2>
    <div class="grille-form">
      <div class="champ"><label for="new-name">{{ __('catalog.categories.name') }}</label><input id="new-name" name="name" value="{{ old('name') }}" required maxlength="80"></div>
      <div class="champ"><label for="new-icon">{{ __('catalog.categories.icon') }}</label>
        <select id="new-icon" name="icon">@foreach ($icons as $i)<option value="{{ $i }}">{{ __('catalog.icons.'.$i) }}</option>@endforeach</select></div>
      <div class="champ"><label for="new-palette">{{ __('catalog.categories.palette') }}</label>
        <select id="new-palette" name="palette">@foreach ($palettes as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach</select></div>
      <div class="champ"><label for="new-image">{{ __('catalog.categories.image') }}</label><input id="new-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="new-image-aide"><span class="note" id="new-image-aide">{{ __('catalog.categories.image_hint') }}</span></div>
      <div class="champ"><label for="new-order">{{ __('catalog.categories.sort_order') }}</label><input id="new-order" name="sort_order" value="{{ old('sort_order', $categories->max('sort_order') + 1) }}" inputmode="numeric"></div>
    </div>
    <div><button class="btn btn-primaire" type="submit">{{ __('catalog.categories.add') }}</button></div>
  </form>
@endsection
