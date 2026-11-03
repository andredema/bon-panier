@extends('layouts.admin')

@section('title', __('catalog.settings.title'))

@section('content')
  @include('admin.settings._tabs')

  <form method="POST" action="{{ route('admin.settings.update') }}" class="col-reglages" novalidate>
    @csrf
    @method('PUT')

    @foreach ($groups as $group => $items)
      <section class="bloc carte" aria-labelledby="g-{{ $group }}">
        <h2 id="g-{{ $group }}">{{ __('catalog.settings.groups.'.$group) }}</h2>
        @if ($group === 'rules')
          <p class="note">{{ __('catalog.settings.rules_note') }}</p>
        @endif
        <div class="grille-form">
          @foreach ($items as $s)
            @php
              $field = str_replace('.', '_', $s['key']);
              // Clés de traduction sans point (Laravel découpe les clés sur les points)
              $label = __('catalog.settings.labels.'.$field);
            @endphp
            @if ($s['type'] === 'bool')
              <div class="champ plein">
                <label class="bascule"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $s['value']))> {{ $label }}
                  @if ($s['open_question'] ?? false)<span class="etiquette e-confirmee">{{ __('catalog.settings.open_question') }}</span>@endif</label>
              </div>
            @elseif ($s['type'] === 'enum')
              <div class="champ">
                <label for="{{ $field }}">{{ $label }} @if ($s['open_question'] ?? false)<span class="etiquette e-confirmee">{{ __('catalog.settings.open_question') }}</span>@endif</label>
                <select id="{{ $field }}" name="{{ $field }}">
                  @foreach ($s['options'] as $opt)
                    <option value="{{ $opt }}" @selected(old($field, $s['value']) === $opt)>{{ __('catalog.settings.options.'.$field.'.'.$opt) }}</option>
                  @endforeach
                </select>
                @error($field)<span class="erreur">{{ $message }}</span>@enderror
              </div>
            @elseif ($s['type'] === 'rate')
              <div class="champ">
                <label for="{{ $field }}">{{ $label }} ({{ __('catalog.settings.rate_suffix') }})</label>
                <input id="{{ $field }}" name="{{ $field }}" inputmode="decimal" value="{{ old($field, \App\Support\Format::quantity(\App\Support\Quantity::fromMilli($s['value']))) }}">
                @error($field)<span class="erreur">{{ $message }}</span>@enderror
              </div>
            @elseif ($s['type'] === 'days')
              @php $chosen = array_map('strval', \App\Support\Settings::days($s['key'])); @endphp
              <fieldset class="champ plein jours">
                <legend>{{ $label }} @if ($s['open_question'] ?? false)<span class="etiquette e-confirmee">{{ __('catalog.settings.open_question') }}</span>@endif</legend>
                <div class="bascules">
                  @foreach (range(1, 7) as $d)
                    <label class="case"><input type="checkbox" name="{{ $field }}[]" value="{{ $d }}" @checked(in_array((string) $d, old($field, $chosen), true))> {{ __('catalog.settings.weekdays.'.$d) }}</label>
                  @endforeach
                </div>
              </fieldset>
            @elseif (in_array($s['type'], ['int', 'money', 'slots'], true))
              <div class="champ {{ $s['type'] === 'slots' ? 'plein' : '' }}">
                <label for="{{ $field }}">{{ $label }} @if ($s['open_question'] ?? false)<span class="etiquette e-confirmee">{{ __('catalog.settings.open_question') }}</span>@endif</label>
                <input id="{{ $field }}" name="{{ $field }}" inputmode="{{ $s['type'] === 'slots' ? 'text' : 'decimal' }}"
                       value="{{ old($field, $s['type'] === 'money' ? number_format($s['value'] / 100, 2, ',', ' ') : $s['value']) }}"
                       @if ($s['type'] === 'slots') aria-describedby="{{ $field }}-aide" @endif>
                @if ($s['type'] === 'slots')<span class="note" id="{{ $field }}-aide">{{ __('catalog.settings.slots_hint') }}</span>@endif
                @error($field)<span class="erreur">{{ $message }}</span>@enderror
              </div>
            @else
              <div class="champ {{ $s['key'] === 'shop.address' ? 'plein' : '' }}">
                <label for="{{ $field }}">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $s['value']) }}"
                       @if (str_ends_with($s['key'], 'email')) type="email" autocomplete="off" @elseif (str_ends_with($s['key'], 'phone')) type="tel" @endif maxlength="255">
                @error($field)<span class="erreur">{{ $message }}</span>@enderror
              </div>
            @endif
          @endforeach
        </div>
      </section>
    @endforeach

    <div class="barre"><span></span><button class="btn btn-primaire" type="submit">{{ __('catalog.settings.save') }}</button></div>
  </form>
@endsection
