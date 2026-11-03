{{-- En-tête commun : émetteur (copie figée sur la facture) et identification du document --}}
<table class="tete">
  <tr>
    <td class="emetteur">
      <table><tr>
        @if ($logo)<td style="padding-right:12px"><img class="logo" src="{{ $logo }}" alt=""></td>@endif
        <td>
          <h1>{{ $shop['name'] }}</h1>
          <p>{{ $shop['address'] }}@if ($shop['phone'] || $shop['email'])<br>{{ collect([$shop['phone'], $shop['email']])->filter()->join(' · ') }}@endif</p>
        </td>
      </tr></table>
    </td>
    <td class="doc">
      <h2>{{ $docTitle }}</h2>
      <table>
        @foreach ($docFields as $label => $value)
          <tr><td class="l">{{ $label }}</td><td class="v">{{ $value }}</td></tr>
        @endforeach
      </table>
    </td>
  </tr>
</table>
