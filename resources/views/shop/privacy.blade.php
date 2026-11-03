@extends('layouts.shop')

@php
    $s = fn ($k) => \App\Support\Settings::get($k);
    $officer = $s('shop.privacy_officer') ?: '[Responsable de la protection des renseignements personnels — à désigner]';
@endphp

@section('title', __('shop.privacy.title'))

@section('content')
{{-- Version de travail : le texte définitif est à valider avec l'épicerie (étape 9, Loi 25) --}}
<main class="page-texte" id="contenu">
  <h1>{{ __('shop.privacy.title') }}</h1>
  <p>{{ $s('shop.name') }}, {{ $s('shop.address') }}.</p>

  <h2>Renseignements recueillis</h2>
  <p>Pour traiter votre commande, nous recueillons votre nom, votre numéro de téléphone, votre courriel et, en cas de livraison, votre adresse. Nous ne recueillons rien d’autre et n’utilisons aucun traceur publicitaire.</p>

  <h2>Utilisation</h2>
  <p>Ces renseignements servent uniquement à préparer votre commande, à vous joindre à son sujet (confirmation, commande prête, précommande arrivée) et à tenir nos documents comptables.</p>

  <h2>Conservation et hébergement</h2>
  <p>Les données sont hébergées au Canada (Montréal). Les factures sont conservées le temps exigé par les lois fiscales ; les autres renseignements sont supprimés ou rendus anonymes sur demande.</p>

  <h2>Vos droits</h2>
  <p>Vous pouvez demander l’accès à vos renseignements, leur correction ou leur suppression en écrivant à {{ $s('shop.email') ?: '[courriel de l’épicerie]' }}.</p>

  <h2>Responsable</h2>
  <p>{{ $officer }}</p>
</main>
@endsection
