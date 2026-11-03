@extends('layouts.admin')

@php
    $editing = $product->exists;
    $title = $editing ? $product->name : __('catalog.products.new');
    $config = [
        'rows' => $formatRows,
        'units' => $units->map(fn ($u) => [
            'id' => $u->id, 'code' => $u->code, 'label' => $u->label, 'short_label' => $u->short_label,
            'kind' => $u->kind->value, 'base_factor' => $u->base_factor, 'default_step' => $u->default_step,
            'is_active' => $u->is_active,
        ])->values(),
        'categories' => $categories->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'icon' => $c->icon,
            'bg' => $c->palette->background(), 'ink' => $c->palette->ink(),
        ])->values(),
        'stockUnitId' => old('stock_unit_id', $product->stock_unit_id),
        'categoryId' => old('category_id', $product->category_id ?? $categories->first()?->id),
        'name' => old('name', $product->name ?? ''),
        'errors' => $errors->getMessages(),
        'photoUrl' => $product->photoUrl(),
    ];
    $channels = \App\Enums\Channel::cases();
@endphp

@section('title', $title)

@section('content')
<form class="fiche-produit" method="POST" enctype="multipart/form-data" novalidate
      action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}"
      x-data="productForm(@js($config))">
  @csrf
  @if ($editing) @method('PUT') @endif

  <div class="barre">
    <div>
      <p class="fil"><a href="{{ route('admin.products.index') }}">{{ __('catalog.products.breadcrumb') }}</a> › <span x-text="category?.name">{{ $product->category?->name }}</span></p>
      <h1 x-text="name || @js(__('catalog.products.new'))">{{ $title }}</h1>
    </div>
    @if ($canEdit)
      <div class="actions">
        <a class="btn btn-secondaire" href="{{ route('admin.products.index') }}">{{ __('catalog.products.cancel') }}</a>
        <button class="btn btn-primaire" type="submit">{{ __('catalog.products.save') }}</button>
      </div>
    @endif
  </div>

  @if (! $canEdit)
    <p class="alerte alerte-info">{{ __('catalog.products.read_only') }}</p>
  @endif

  @if ($errors->any())
    <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
  @endif

  <fieldset class="disposition" @disabled(! $canEdit)>
    <legend class="sr">{{ $title }}</legend>
    <div class="col">
      {{-- Informations --}}
      <section class="bloc carte" aria-labelledby="t-info">
        <h2 id="t-info">{{ __('catalog.products.info') }}</h2>
        <div class="infos">
          <div class="photo-zone">
            <label class="photo-p" for="photo" :style="photoUrl ? '' : `background:${category?.bg};color:${category?.ink}`">
              <template x-if="photoUrl"><img :src="photoUrl" alt=""></template>
              <span x-show="!photoUrl">+ {{ __('catalog.products.photo_add') }}</span>
              <span class="sr">{{ __('catalog.products.photo') }}</span>
            </label>
            <input class="sr" id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" x-ref="photo" @change="pickPhoto" aria-describedby="photo-aide">
            <input type="hidden" name="remove_photo" :value="removePhoto ? 1 : 0">
            <button class="lien-btn" type="button" x-show="photoUrl" @click="clearPhoto">{{ __('catalog.products.photo_remove') }}</button>
            <span class="note" id="photo-aide">{{ __('catalog.products.photo_hint') }}</span>
            @error('photo')<span class="erreur">{{ $message }}</span>@enderror
          </div>
          <div class="champ">
            <label for="name">{{ __('catalog.products.name') }}</label>
            <input id="name" name="name" x-model="name" value="{{ old('name', $product->name) }}" required maxlength="120">
            @error('name')<span class="erreur">{{ $message }}</span>@enderror
          </div>
          <div class="champ">
            <label for="category_id">{{ __('catalog.products.category') }}</label>
            <select id="category_id" name="category_id" x-model="categoryId">
              @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected((string) old('category_id', $product->category_id) === (string) $c->id)>{{ $c->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="champ">
            <label for="brand">{{ __('catalog.products.brand') }}</label>
            <input id="brand" name="brand" value="{{ old('brand', $product->brand) }}" placeholder="{{ __('catalog.products.brand_placeholder') }}" maxlength="80">
          </div>
          <div class="champ">
            <label for="barcode">{{ __('catalog.products.barcode') }}</label>
            <input id="barcode" name="barcode" value="{{ old('barcode', $product->barcode) }}" placeholder="{{ __('catalog.products.barcode_placeholder') }}" inputmode="numeric" maxlength="40">
            @error('barcode')<span class="erreur">{{ $message }}</span>@enderror
          </div>
        </div>
        <div class="bascules">
          @foreach (['is_frozen', 'is_taxable', 'is_visible', 'allow_preorder'] as $flag)
            <label class="bascule"><input type="checkbox" name="{{ $flag }}" value="1" @checked(old($flag, $product->$flag))> {{ __('catalog.products.'.$flag) }}</label>
          @endforeach
        </div>
      </section>

      {{-- Stock --}}
      <section class="bloc carte" aria-labelledby="t-stock">
        <h2 id="t-stock">{{ __('catalog.products.stock') }}</h2>
        <div class="base">
          <div class="champ">
            <label for="stock_unit_id">{{ __('catalog.products.stock_unit') }}</label>
            <select id="stock_unit_id" name="stock_unit_id" x-model="stockUnitId" @if ($stockUnitLocked) disabled aria-describedby="unite-verrou" @endif>
              @foreach ($units as $u)
                <option value="{{ $u->id }}" @selected((string) old('stock_unit_id', $product->stock_unit_id) === (string) $u->id) @disabled(! $u->is_active && $u->id !== $product->stock_unit_id)>{{ $u->label }}</option>
              @endforeach
            </select>
            @if ($stockUnitLocked)
              <input type="hidden" name="stock_unit_id" value="{{ $product->stock_unit_id }}">
              <span class="note" id="unite-verrou">{{ __('catalog.products.stock_unit_locked') }}</span>
            @endif
            @error('stock_unit_id')<span class="erreur">{{ $message }}</span>@enderror
          </div>
          <div class="champ">
            <label for="alert_threshold">{{ __('catalog.products.alert_threshold') }} (<span x-text="stockUnit?.short_label"></span>)</label>
            <input id="alert_threshold" name="alert_threshold" value="{{ old('alert_threshold', \App\Support\Format::quantity($product->alert_threshold ?? '0')) }}" inputmode="decimal">
            @error('alert_threshold')<span class="erreur">{{ $message }}</span>@enderror
          </div>
        </div>
        <p class="explic" x-text="@js(__('catalog.products.stock_explain', ['unit' => '__U__'])).replace('__U__', stockUnit?.short_label ?? '')">{{ __('catalog.products.stock_explain', ['unit' => $product->stockUnit?->short_label]) }}</p>
      </section>

      {{-- Formats de vente --}}
      <section class="bloc carte" aria-labelledby="t-formats">
        <div class="bloc-tete"><h2 id="t-formats">{{ __('catalog.products.formats') }}</h2><span class="note">{{ __('catalog.products.formats_note') }}</span></div>
        @error('formats')<div class="alerte alerte-erreur" role="alert">{{ $message }}</div>@enderror
        <p class="note">{{ __('catalog.products.f_by_weight_help') }}</p>

        @foreach ($channels as $ch)
          <div class="groupe-formats" aria-labelledby="g-{{ $ch->value }}">
            <h3 class="groupe-canal" id="g-{{ $ch->value }}">{{ $ch->label() }}</h3>
            <template x-for="{ row, i } in rowsFor('{{ $ch->value }}')" :key="row.key">
              <div class="format-l" :data-key="row.key" role="group" :aria-label="row.label || @js(__('catalog.products.add_format'))">
                <input type="hidden" :name="`formats[${i}][id]`" :value="row.id">

                <div class="f-ligne f-ligne-1">
                  <div class="champ f-nom">
                    <label :for="`f-${row.key}-label`">{{ __('catalog.products.f_label') }}</label>
                    <input type="text" :id="`f-${row.key}-label`" :name="`formats[${i}][label]`" x-model="row.label" maxlength="120">
                    <span class="erreur" x-text="error(i, 'label')"></span>
                  </div>
                  <div class="champ">
                    <label :for="`f-${row.key}-channel`">{{ __('catalog.products.f_channel') }}</label>
                    <select :id="`f-${row.key}-channel`" :name="`formats[${i}][channel]`" x-model="row.channel">
                      @foreach ($channels as $c)<option value="{{ $c->value }}">{{ $c->label() }}</option>@endforeach
                    </select>
                  </div>
                  <div class="champ">
                    <label :for="`f-${row.key}-unit`">{{ __('catalog.products.f_unit') }}</label>
                    <select :id="`f-${row.key}-unit`" :name="`formats[${i}][unit_id]`" x-model="row.unit_id" @change="onUnitChange(row)">
                      <template x-for="u in units" :key="u.id">
                        <option :value="u.id" x-text="u.short_label" :selected="String(u.id) === String(row.unit_id)" :disabled="!u.is_active && String(u.id) !== String(row.unit_id)"></option>
                      </template>
                    </select>
                    <span class="erreur" x-text="error(i, 'unit_id')"></span>
                  </div>
                  <div class="champ f-prix">
                    <label :for="`f-${row.key}-price`" x-text="row.is_by_weight ? @js(__('catalog.products.f_price_by_weight', ['unit' => '__U__'])).replace('__U__', unit(row.unit_id)?.short_label ?? '') : @js(__('catalog.products.f_price'))"></label>
                    <input type="text" :id="`f-${row.key}-price`" :name="`formats[${i}][price]`" x-model="row.price" inputmode="decimal" placeholder="0,00">
                    <span class="erreur" x-text="error(i, 'price')"></span>
                  </div>
                  <button class="btn btn-secondaire mini f-retirer" type="button" @click="remove(i)" :aria-label="`{{ __('catalog.products.f_remove') }} ${row.label}`">×</button>
                </div>

                <div class="f-ligne f-ligne-2">
                  <label class="bascule" :class="{ inactif: !canWeigh(row) }">
                    <input type="checkbox" :name="`formats[${i}][is_by_weight]`" value="1" x-model="row.is_by_weight" :disabled="!canWeigh(row) && !row.is_by_weight" @change="onWeighChange(row)">
                    {{ __('catalog.products.f_by_weight') }}
                  </label>
                  <div class="champ f-court" x-show="!row.is_by_weight">
                    <label :for="`f-${row.key}-content`">{{ __('catalog.products.f_content') }}</label>
                    <span class="avec-unite">
                      <input type="text" :id="`f-${row.key}-content`" :name="`formats[${i}][content_qty]`" x-model="row.content_qty" inputmode="decimal">
                      <span x-text="stockUnit?.short_label"></span>
                    </span>
                    <span class="erreur" x-text="error(i, 'content_qty')"></span>
                  </div>
                  <div class="champ f-net" x-show="showNet(row)">
                    <label :for="`f-${row.key}-net`">{{ __('catalog.products.f_net') }}</label>
                    <span class="avec-unite">
                      <input type="text" :id="`f-${row.key}-net`" :name="`formats[${i}][net_qty]`" x-model="row.net_qty" inputmode="decimal">
                      <select :name="`formats[${i}][net_unit_id]`" x-model="row.net_unit_id" aria-label="{{ __('catalog.products.f_net_unit') }}">
                        <template x-for="u in measureUnits" :key="u.id">
                          <option :value="u.id" x-text="u.short_label" :selected="String(u.id) === String(row.net_unit_id)"></option>
                        </template>
                      </select>
                    </span>
                    <span class="erreur" x-text="error(i, 'net_qty')"></span>
                  </div>
                  <div class="champ f-court">
                    <label :for="`f-${row.key}-min`">{{ __('catalog.products.f_min') }}</label>
                    <input type="text" :id="`f-${row.key}-min`" :name="`formats[${i}][min_qty]`" x-model="row.min_qty" inputmode="decimal">
                    <span class="erreur" x-text="error(i, 'min_qty')"></span>
                  </div>
                  <div class="champ f-court">
                    <label :for="`f-${row.key}-step`">{{ __('catalog.products.f_step') }}</label>
                    <input type="text" :id="`f-${row.key}-step`" :name="`formats[${i}][step]`" x-model="row.step" inputmode="decimal">
                    <span class="erreur" x-text="error(i, 'step')"></span>
                  </div>
                  <label class="bascule">
                    <input type="checkbox" :name="`formats[${i}][is_visible]`" value="1" x-model="row.is_visible">
                    {{ __('catalog.products.f_visible') }}
                  </label>
                  <p class="calc num">
                    <span x-text="@js(__('catalog.products.f_per_stock', ['unit' => '__U__'])).replace('__U__', perStockLabel())"></span>
                    <b x-text="perStock(row)"></b>
                  </p>
                </div>
                <span class="erreur" x-text="error(i, 'is_by_weight')"></span>
              </div>
            </template>
            <p class="note" x-show="rowsFor('{{ $ch->value }}').length === 0">{{ __('catalog.products.no_format') }}</p>
            @if ($canEdit)
              <button class="btn btn-secondaire mini ajout-l" type="button" @click="add('{{ $ch->value }}')">+ {{ __('catalog.products.add_format') }} ({{ mb_strtolower($ch->label()) }})</button>
            @endif
          </div>
        @endforeach
      </section>

      @if ($editing)
        <section class="bloc carte" aria-labelledby="t-hist">
          <h2 id="t-hist">{{ __('catalog.products.history') }}</h2>
          @forelse ($history as $log)
            <p class="note">
              {{ \App\Support\Format::date($log->created_at) }}, {{ \App\Support\Format::time($log->created_at) }} —
              {{ __('catalog.products.history_actions.'.$log->action) }}
              {{ $log->subject_type === $product->getMorphClass() ? '' : '(format)' }}
              {{ $log->user ? __('catalog.products.history_by', ['name' => $log->user->name]) : __('catalog.products.history_system') }}
            </p>
          @empty
            <p class="note">{{ __('catalog.products.history_empty') }}</p>
          @endforelse
        </section>
      @endif
    </div>

    {{-- Aperçu --}}
    <aside class="apercu" aria-labelledby="t-apercu">
      <h2 id="t-apercu">{{ __('catalog.products.preview') }}</h2>
      <div class="segment" role="group" aria-label="{{ __('catalog.products.f_channel') }}">
        @foreach ($channels as $c)
          <button type="button" @click="preview = '{{ $c->value }}'" :aria-pressed="(preview === '{{ $c->value }}').toString()">{{ $c->label() }}</button>
        @endforeach
      </div>
      <div class="carte carte-p">
        <div class="ph" :style="`background:${category?.bg};color:${category?.ink}`">
          <template x-if="photoUrl"><img :src="photoUrl" alt=""></template>
          <svg x-show="!photoUrl" width="56" height="56" aria-hidden="true"><use :href="`#i-${category?.icon}`"/></svg>
        </div>
        <div class="in">
          <b class="nom-p" x-text="name"></b>
          <div class="opt-liste">
            <template x-for="row in visibleFor(preview)" :key="row.key">
              <div><span><span x-text="row.label"></span> <small x-text="minLabel(row)"></small></span><b class="num" x-text="priceLabel(row)"></b></div>
            </template>
            <small x-show="visibleFor(preview).length === 0">{{ __('catalog.products.no_format') }}</small>
          </div>
        </div>
      </div>
      <p class="note">{{ __('catalog.products.preview_note') }}</p>
    </aside>
  </fieldset>
</form>

@if ($editing && $canEdit)
  <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="zone-archive"
        onsubmit="return confirm(@js(__('catalog.products.archive_confirm')))">
    @csrf
    @method('DELETE')
    <button class="lien-btn lien-danger" type="submit">{{ __('catalog.products.archive') }}</button>
  </form>
@endif
@endsection
