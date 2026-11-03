@extends('layouts.admin')

@section('title', __('stock.suppliers.title'))

@section('content')
  <div class="barre">
    <div><h1>{{ __('stock.suppliers.title') }}</h1><p>{{ __('stock.suppliers.intro') }}</p></div>
  </div>

  @include('admin.stock._tabs')

  @if ($errors->any())
    <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
  @endif

  <div class="unites-g fournisseurs-g">
    @foreach ($suppliers as $s)
      <form class="unite-c" method="POST" action="{{ route('admin.suppliers.update', $s) }}" aria-label="{{ $s->name }}">
        @csrf @method('PUT')
        <label class="bascule"><input type="checkbox" name="is_active" value="1" @checked($s->is_active)> <b>{{ $s->name }}</b></label>
        <small>{{ trans_choice('stock.suppliers.receipts', $s->receipts_count) }}</small>
        @include('admin.stock._supplier-fields', ['s' => $s, 'p' => 's'.$s->id])
        <button class="btn btn-secondaire mini" type="submit">{{ __('stock.suppliers.save') }}<span class="sr"> {{ $s->name }}</span></button>
      </form>
    @endforeach

    <form class="unite-c unite-nouvelle" method="POST" action="{{ route('admin.suppliers.store') }}" aria-labelledby="t-new-supplier">
      @csrf
      <b id="t-new-supplier">{{ __('stock.suppliers.new') }}</b>
      @include('admin.stock._supplier-fields', ['s' => null, 'p' => 'new'])
      <button class="btn btn-primaire mini" type="submit">{{ __('stock.suppliers.add') }}</button>
    </form>
  </div>
@endsection
