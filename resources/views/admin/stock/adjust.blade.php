@extends('layouts.admin')

@php
    use App\Support\Format;
    $u = $lot->product->stockUnit->short_label;
@endphp

@section('title', __('reports.adjust.title'))

@section('content')
  <div class="barre">
    <div>
      <p class="fil"><a href="{{ route('admin.products.edit', $lot->product) }}">{{ $lot->product->name }}</a></p>
      <h1>{{ __('reports.adjust.title') }}</h1>
      <p>{{ __('reports.adjust.intro') }}</p>
    </div>
  </div>

  @if ($errors->any())
    <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
  @endif

  <form class="bloc carte" method="POST" action="{{ route('admin.adjustments.store', $lot) }}" novalidate>
    @csrf
    <h2>{{ __('reports.adjust.lot', ['number' => $lot->lot_number, 'product' => $lot->product->name]) }}</h2>
    <p class="note">
      {{ __('reports.adjust.current', ['qty' => Format::quantity($lot->remaining_qty), 'unit' => $u]) }}
      · {{ $lot->receipt->number() }}@if ($lot->best_before) · {{ __('stock.receipts.label_bb', ['date' => Format::date($lot->best_before)]) }}@endif
    </p>
    <div class="grille-form">
      <div class="champ">
        <label for="qty">{{ __('reports.adjust.qty', ['unit' => $u]) }}</label>
        <input id="qty" name="qty" inputmode="decimal" value="{{ old('qty', str_replace(Format::NBSP, '', Format::quantity($lot->remaining_qty))) }}" required>
        @error('qty')<span class="erreur">{{ $message }}</span>@enderror
      </div>
      <div class="champ">
        <label for="reason">{{ __('reports.adjust.reason') }}</label>
        <select id="reason" name="reason">
          @foreach (\App\Models\StockAdjustment::REASONS as $r)<option value="{{ $r }}" @selected(old('reason') === $r)>{{ __('reports.adjust.reasons.'.$r) }}</option>@endforeach
        </select>
        @error('reason')<span class="erreur">{{ $message }}</span>@enderror
      </div>
      <div class="champ plein">
        <label for="note">{{ __('reports.adjust.note') }}</label>
        <input id="note" name="note" value="{{ old('note') }}" maxlength="255">
        @error('note')<span class="erreur">{{ $message }}</span>@enderror
      </div>
    </div>
    <div><button class="btn btn-primaire" type="submit">{{ __('reports.adjust.save') }}</button></div>
  </form>

  @if ($recent->isNotEmpty())
    <section class="bloc carte" aria-labelledby="t-hist">
      <h2 id="t-hist">{{ __('reports.adjust.journal') }}</h2>
      @foreach ($recent as $a)
        <p class="note">{{ Format::date($a->created_at) }} · {{ Format::quantity($a->qty_before) }} → {{ Format::quantity($a->qty_after) }} {{ $u }}
          · {{ __('reports.adjust.reasons.'.$a->reason) }}@if ($a->note) ({{ $a->note }})@endif · {{ $a->user?->name ?? '—' }}</p>
      @endforeach
    </section>
  @endif
@endsection
