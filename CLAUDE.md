# CLAUDE.md — Le Bon Panier

Instructions pour Claude Code. Lis ce fichier, puis `docs/cahier-des-charges.md`, avant d'écrire du code.

## Le projet

Application web de commande et de gestion pour **l'Épicerie africaine Le Bon Panier**, 840, avenue du Marché, Saint-Lin–Laurentides (Québec). Projet réalisé par **Zenviko** (André Perdriel Demanou).

Deux espaces partagent la même base de données :

1. **Page de commande publique** : les clients commandent au **détail** ou **en gros**, en ramassage ou en livraison, et paient par virement Interac ou au ramassage.
2. **Espace de gestion** (gérant, employés) : commandes, précommandes, entrées en stock, produits et formats de vente, clients, factures, rapports comptables.

Les **maquettes HTML validées** sont dans `maquettes/` et servent de référence visuelle et fonctionnelle. Reproduis leur mise en page, leurs couleurs et leurs comportements. Leurs données (produits, prix, clients) sont des exemples : ne les recopie pas en dur, elles iront dans les seeders de démonstration.

| Maquette | Écran à coder |
| --- | --- |
| `maquettes/commande.html` | Page de commande publique (référence principale) |
| `maquettes/tableau-de-bord.html` | Accueil de l'espace de gestion |
| `maquettes/arrivage.html` | Entrée en stock |
| `maquettes/produit.html` | Fiche produit, formats de vente, réglage des unités |
| `maquettes/facture.html` | Facture PDF |
| `maquettes/rapport-mensuel.html` | Rapports et documents comptables |
| `maquettes/assets/` | Logo « La porteuse », icône, styles et couleurs |

## Hébergement : PlanetHoster

- Hébergement mutualisé PlanetHoster, panneau **N0C**, centre de données de **Montréal** (exigé : données au Canada, Loi 25).
- Disponible : PHP (jusqu'à 8.x), bases MySQL, accès SSH et Git, certificat SSL Let's Encrypt gratuit.
- **Pas de Docker, pas de processus permanent, pas de Redis.** Tout doit tourner en PHP classique derrière Apache/LiteSpeed.
- Tâches planifiées et files d'attente : uniquement par **cron** (à confirmer dans N0C ; voir `docs/deploiement-planethoster.md`).

## Pile technique (choisie pour cet hébergement)

- **PHP 8.3 + Laravel 11** ; **MySQL / MariaDB** (InnoDB, utf8mb4, `utf8mb4_unicode_ci`).
- Gabarits **Blade** + **Alpine.js** pour l'interactivité (panier, filtres, tiroirs). Pas de SPA.
- CSS : reprendre les jetons de `maquettes/assets/styles.css` et `admin.css` (variables CSS). Tailwind est permis s'il reproduit fidèlement les maquettes.
- Construction des fichiers front avec **Vite en local** ; on déploie les fichiers compilés (`public/build`), jamais `node_modules`.
- PDF : `barryvdh/laravel-dompdf`. CSV : génération native en flux (`StreamedResponse`).
- Authentification de l'espace de gestion : Laravel Breeze (Blade) ou équivalent léger, en français.
- Courriels : SMTP de PlanetHoster (variables `.env`), envoi par la file `database` vidée par cron.
- Files d'attente : `QUEUE_CONNECTION=database`. Cache et sessions : `file` ou `database`.

Si une contrainte d'hébergement rend un choix impossible, arrête-toi et propose une alternative avant de changer la pile.

## Règles métier non négociables

Le détail complet est dans `docs/cahier-des-charges.md`. À retenir :

1. **Un seul stock par produit**, compté dans son **unité de stock** (sac, unité, L, kg ou g). Chaque **format de vente** indique combien il retire du stock (ex. carton de 50 boules = 50 ; sac de 25 kg = 25 si l'unité est le kg).
2. **Formats de vente** par produit et par **canal** (`detail` ou `gros`) : unité de vente (sac, unité, L, kg, g), nom affiché, contenu, prix, minimum, pas (ex. 0,5 kg), visible. La liste des unités de vente se règle dans les réglages.
3. **Précommande, jamais « rupture »** : un produit à 0 reste commandable si la précommande est permise. Ces lignes sont marquées `precommande` et servies dans l'ordre d'arrivée à la prochaine entrée en stock ; le client reçoit un courriel.
4. **Taxes du Québec** : TPS 5 % et TVQ 9,975 % uniquement sur les produits marqués `taxable` (la plupart des aliments de base sont détaxés). Taux stockés en réglages, pas en dur.
5. **Factures** : numérotation continue sans trou (`F-AAAA-NNNN`), jamais modifiées après émission ; une correction passe par une **note de crédit**.
6. **Traçabilité** : chaque entrée en stock crée un **lot** (n° de lot, date limite, fournisseur, coût) ; chaque ligne vendue garde le lot sorti.
7. **Statuts de commande** : `recue` → `confirmee` → `en_preparation` → `prete` → `remise`, ou `annulee`. La remise génère la facture.

## Exigences d'interface

- **Tout en français (Québec)**, formats `fr-CA` : `1 284,50 $`, dates « 2 novembre 2026 ».
- **Responsive sur tous les appareils** : ordinateur, tablette, iPhone, Android, de 320 px à 1920 px, sans défilement horizontal.
- Page de commande :
  - catégories en **cartes** en haut, avec leur nombre de produits ; un clic filtre la liste et affiche « Catégorie · N produits » ;
  - produits par **ordre alphabétique** par défaut ;
  - le **total du panier reste toujours visible** : panier latéral collé sur ordinateur, barre fixe en bas sur téléphone et tablette, montant dans le bouton Panier ;
  - filtres regroupés : barre de menus déroulants sur ordinateur, bouton « Filtres » qui ouvre une feuille sur téléphone et tablette (**jamais de défilement horizontal**) ;
  - boutons « Ajouter » et « Précommander » de même taille, pleine largeur sous la quantité.
- iOS : champs à 16 px minimum, `viewport-fit=cover` et `env(safe-area-inset-*)` pour les barres fixes, `100dvh` pour les tiroirs.
- Accessibilité : contraste de 4,5:1 minimum, cibles tactiles de 44 px, vrais `<button>`, `<label>` sur tous les champs, navigation au clavier, `aria-live` sur le total.

## Façon de travailler

- Avance **par étapes** (voir le plan de `docs/cahier-des-charges.md`, section 12). À la fin de chaque étape : tests verts, courte note de ce qui est fait, puis commit.
- Écris des **tests** (Pest ou PHPUnit) pour toute règle métier : conversion de stock, précommandes, calcul des taxes et arrondis, numérotation des factures, transitions de statut.
- Montants en **cents (entiers)** dans la base ; quantités en `DECIMAL(12,3)`. Jamais de `float` pour l'argent.
- Migrations, seeders de démonstration (les produits des maquettes) et factories pour chaque modèle.
- Code, noms de tables et de colonnes **en anglais** ; textes affichés **en français**, dans `lang/fr/`.
- Ne mets **aucun secret** dans le dépôt : `.env` est ignoré, fournis `.env.example`.
- Aucune donnée personnelle réelle dans les seeders.

## Commandes utiles

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm install && npm run dev      # en local
npm run build                   # avant le déploiement
php artisan test
```

## Questions encore ouvertes (ne pas inventer de réponse)

Voir `docs/cahier-des-charges.md`, section 13. Tant qu'une réponse manque, rends le comportement configurable dans les réglages et signale-le dans le résumé d'étape.
