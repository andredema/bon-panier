{{-- Barres horizontales (maquette .barre-cat) : [libellé, cents, orange?] --}}
@php $max = max(1, collect($rows)->max(fn ($r) => $r[1])); @endphp
@foreach ($rows as [$label, $cents, $alt])
  <div class="barre-cat">
    <span>{{ $label }}</span>
    <span class="piste" aria-hidden="true"><i class="{{ $alt ? 'tx' : '' }}" style="width:{{ round($cents / $max * 100, 1) }}%"></i></span>
    <span class="d num">@money($cents)</span>
  </div>
@endforeach
