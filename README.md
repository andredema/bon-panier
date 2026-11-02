# Le Bon Panier — maquettes de l'application (v2)

Maquettes HTML pour la présentation au client de l'application de commande et de gestion de l'épicerie africaine Le Bon Panier (Saint-Lin–Laurentides).

## Ouvrir

Ouvrir `maquettes/index.html` dans un navigateur. Aucune installation n'est requise ; une connexion Internet sert seulement au chargement des polices.

| Fichier | Écran |
| --- | --- |
| `maquettes/index.html` | Sommaire de la présentation |
| `maquettes/commande.html` | Page de commande : Détail / En gros, filtres regroupés, formats, précommande, panier en tiroir |
| `maquettes/tableau-de-bord.html` | Tableau de bord : commandes, précommandes à servir, stock |
| `maquettes/arrivage.html` | Entrée en stock rapide (format reçu, conversion, précommandes servies) |
| `maquettes/produit.html` | Produit et formats de vente ; réglage des unités (sac, unité, L, kg, g) |
| `maquettes/facture.html` | Facture client (imprimable en PDF) |
| `maquettes/rapport-mensuel.html` | Rapport mensuel et documents comptables |
| `maquettes/assets/` | Logo « La porteuse », icône, styles communs |

Les produits, prix, noms de clients et chiffres sont des **exemples**. Les champs entre crochets (`[N° TPS]`, `[Zone et frais]`…) sont à compléter avec l'épicerie.

## Principes de la v2

- **Détail ou en gros** : chaque produit a des formats par canal, avec prix et minimum.
- **Unités configurables** : sac, unité, L, kg, g ; un seul stock par produit, chaque format indique ce qu'il retire.
- **Précommande** : un produit à 0 reste commandable ; la commande est servie à l'entrée en stock.
- **Filtres regroupés** : un panneau unique, filtres actifs en puces effaçables.

## Identité

- Vert feuille `#1F4D2E` · Osier `#D49A3A` · Terre `#A8432A` · Manioc `#F7F2E8`
- Polices : Bricolage Grotesque (titres), Manrope (texte)

Zenviko — Développement web et soutien informatique

## Développement de l'application

- `CLAUDE.md` : instructions pour Claude Code (contexte, pile technique, règles métier, exigences).
- `docs/cahier-des-charges.md` : cahier des charges complet et plan par étapes.
- `docs/deploiement-planethoster.md` : mise en ligne sur PlanetHoster (N0C, Montréal).
- `docs/prompt-claude-code.md` : message de départ à coller dans Claude Code.
