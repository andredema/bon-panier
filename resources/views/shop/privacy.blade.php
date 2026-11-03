@extends('layouts.shop')

@php
    $s = fn ($k) => \App\Support\Settings::get($k);
    $officer = $s('shop.privacy_officer') ?: '[Responsable de la protection des renseignements personnels — à désigner]';
    $email = $s('shop.email') ?: '[courriel de l’épicerie]';
    $phone = $s('shop.phone');
@endphp

@section('title', __('shop.privacy.title'))

@section('content')
{{-- Texte proposé ; à faire valider par l'épicerie avant la mise en ligne (Loi 25). --}}
<main class="page-texte" id="contenu">
  <h1>{{ __('shop.privacy.title') }}</h1>
  <p>{{ $s('shop.name') }}, {{ $s('shop.address') }}. Dernière mise à jour : 3 novembre 2026.</p>

  <h2>Responsable de la protection des renseignements personnels</h2>
  <p>{{ $officer }} — {{ $email }}@if ($phone) · {{ $phone }}@endif. Cette personne veille à l’application de la présente politique et répond à vos demandes.</p>

  <h2>Renseignements recueillis</h2>
  <p>Pour traiter votre commande, nous recueillons votre nom, votre numéro de téléphone, votre courriel et, en cas de livraison, votre adresse, ainsi que le contenu de vos commandes et vos paiements. Nous ne recueillons rien d’autre.</p>

  <h2>Pourquoi nous les utilisons</h2>
  <ul>
    <li>préparer votre commande et vous la remettre ou la livrer ;</li>
    <li>vous joindre à son sujet : confirmation, commande prête, précommande arrivée, facture ;</li>
    <li>tenir nos documents comptables et fiscaux.</li>
  </ul>
  <p>Nous ne les vendons pas, ne les louons pas et ne les utilisons pas pour de la publicité. Votre consentement est demandé à chaque commande ; vous pouvez le retirer en tout temps.</p>

  <h2>Témoins (cookies) et traceurs</h2>
  <p>Le site utilise seulement les témoins nécessaires à son fonctionnement (votre panier et la sécurité des formulaires). Aucun outil de mesure d’audience, aucun traceur publicitaire, aucune police ou ressource chargée depuis un site tiers.</p>

  <h2>Qui y a accès</h2>
  <p>Seule l’équipe de l’épicerie, avec un compte personnel protégé par mot de passe. Les données sont hébergées au Canada, à Montréal, chez notre hébergeur (PlanetHoster), qui les conserve pour nous et ne les utilise pas. Les courriels partent par le serveur d’envoi de cet hébergeur.</p>

  <h2>Conservation</h2>
  <p>Les factures et les pièces comptables sont conservées 6 ans, comme l’exigent les lois fiscales. Vos coordonnées sont conservées tant que vous êtes client ; elles sont effacées sur demande, sauf celles qui figurent sur une facture déjà émise.</p>

  <h2>Sécurité et incidents</h2>
  <p>Connexion chiffrée (HTTPS), accès limité à l’équipe, journal des modifications et sauvegardes quotidiennes. En cas d’incident de confidentialité présentant un risque de préjudice sérieux, nous vous aviserons, ainsi que la Commission d’accès à l’information.</p>

  <h2>Vos droits</h2>
  <p>Vous pouvez demander l’accès à vos renseignements (nous vous les remettons dans un fichier lisible), leur correction ou leur effacement, et retirer votre consentement, en écrivant à {{ $email }}. Nous répondons dans un délai de 30 jours. Si vous n’êtes pas satisfait de notre réponse, vous pouvez vous adresser à la Commission d’accès à l’information du Québec.</p>
</main>
@endsection
