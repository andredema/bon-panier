# Cahier des charges — Application Le Bon Panier

Version 1.0 · 2 novembre 2026 · Zenviko (André Perdriel Demanou)
Référence visuelle : maquettes HTML du dossier `maquettes/` (v3.2 pour la page de commande).

---

## 1. Contexte et objectifs

L'Épicerie africaine Le Bon Panier (840, avenue du Marché, Saint-Lin–Laurentides, Québec) vend des produits africains au détail et en gros, dont des produits surgelés de marque DJM (ex. Water Leaf, cartons de 50 boules de 500 g). Aujourd'hui, les commandes arrivent par téléphone et par messages, le stock est suivi de mémoire et les factures sont faites à la main.

Objectifs de la version 1 :

1. Permettre aux clients de commander en ligne, au détail ou en gros, en ramassage ou en livraison.
2. Ne jamais perdre une vente : un produit épuisé passe en **précommande**.
3. Tenir un stock juste, par lot, avec une **entrée en stock rapide**.
4. Produire les **factures** et les **documents comptables** (taxes, journal des ventes, inventaire) sans ressaisie.

## 2. Utilisateurs et rôles

| Rôle | Accès | Actions principales |
| --- | --- | --- |
| Client (public, sans compte obligatoire) | Page de commande | Parcourir, filtrer, commander, précommander, suivre sa commande |
| Client de gros | Page de commande, canal « En gros » | Commander les formats de gros, recevoir des factures payables plus tard |
| Employé | Espace de gestion | Traiter les commandes, recevoir les arrivages, voir le stock |
| Gérant | Espace de gestion, tout | Tout ce que fait l'employé + prix, formats, réglages, rapports, utilisateurs |

Le compte client est facultatif en version 1 : on identifie le client par son téléphone et son courriel. Les prix de gros sont visibles par tous ou réservés aux comptes approuvés selon un réglage (question ouverte).

## 3. Page de commande (publique)

Référence : `maquettes/commande.html`.

### 3.1 Structure de la page, de haut en bas

1. **En-tête** : logo, recherche, bascule **Détail / En gros**, bouton **Panier** affichant le nombre d'articles et le total.
2. **Bandeau gros** (seulement en mode « En gros ») : explique les formats de gros et les quantités minimales.
3. **Section « Nos catégories »** : une carte par catégorie (icône, couleur, nom, nombre de produits), précédée d'une carte « Tous les produits ».
   - Un clic sur une carte filtre la liste, fait défiler jusqu'à elle et affiche le titre « Nom de la catégorie » suivi d'une pastille « N produits ».
   - Un second clic sur la carte active, ou un clic sur « Tous les produits », retire le filtre.
4. **Liste des produits**, triée par **ordre alphabétique** par défaut (tri français, accents compris). Autres tris : prix croissant, popularité.
5. **Filtres regroupés** : Disponibilité (en stock, en précommande), Conservation (surgelé, sec et frais), Vendu en (sac, unité, L, kg, g). Les filtres actifs s'affichent en puces effaçables.
6. **Panier** dont le total reste toujours visible (voir 3.4).

### 3.2 Carte produit

- Photo (ou espace réservé coloré par catégorie), marque, nom.
- Pastilles : « Surgelé », « Précommande », « Dans le panier ».
- Choix du **format** parmi les formats visibles du canal choisi. S'il n'y a qu'un format, il s'affiche comme un champ fixe de même hauteur.
- Prix du format et **prix comparatif** au kg ou au L.
- Zone d'information de hauteur fixe : « Arrive le 6 nov. · réservez maintenant » (précommande) ou « Minimum 2 sacs » (gros).
- Sélecteur de **quantité** pleine largeur (pas de 1, ou de 0,5 kg pour la vente au poids), puis bouton pleine largeur « Ajouter au panier » ou « Précommander ». Les deux boutons ont la même taille.
- Après un ajout : message « Water Leaf ajouté · Total 54,21 $ » pendant 2 secondes, annoncé par `aria-live`.

### 3.3 Filtres selon l'appareil

| Appareil | Présentation |
| --- | --- |
| Ordinateur (> 1100 px) | Barre de menus déroulants au-dessus de la liste, tri à droite |
| Tablette (761–1100 px) | Bouton « Filtres » + tri ; fenêtre centrée avec les filtres en puces |
| Téléphone (≤ 760 px) | Bouton « Filtres » + tri côte à côte ; feuille qui monte du bas, puces sur plusieurs lignes, boutons « Effacer » et « Voir N produits » |

Le défilement horizontal est interdit partout.

### 3.4 Panier et total toujours visible

- **Ordinateur** : panier latéral collé à l'écran avec les lignes modifiables (quantité, retirer), les précommandes regroupées à part, le sous-total, les taxes, le total et « Passer la commande ».
- **Téléphone et tablette** : barre fixe en bas avec le nombre d'articles, le total et « Voir le panier ».
- **Finalisation** (tiroir) : lignes du panier, réception (ramassage gratuit ou livraison), créneau, paiement (Interac ou au ramassage), nom, téléphone, courriel, consentement Loi 25 ; total fixé en bas au-dessus de « Envoyer ma commande ».
- Le panier est conservé côté serveur (session) pour survivre au rechargement.
- Le minimum de gros est vérifié à l'ajout et à l'envoi.

### 3.5 Après la commande

- Page de confirmation avec le n° de commande.
- Courriel de confirmation ; si le paiement est Interac, il donne l'adresse courriel de virement et le message à indiquer (n° de commande).
- Courriel quand la commande est prête, et quand une précommande est arrivée.
- Page de suivi accessible par un lien unique et signé dans le courriel.

## 4. Espace de gestion

Références : `maquettes/tableau-de-bord.html`, `arrivage.html`, `produit.html`.

### 4.1 Accueil (tableau de bord)

- Indicateurs cliquables : ventes du jour (avec la répartition détail / gros), commandes à traiter, précommandes à servir, paiements Interac en attente.
- Commandes : un sélecteur de statut avec les nombres, plus un bouton « Filtres » (canal, réception, créneau, avec précommande) ; un seul bouton d'étape suivante par ligne (Confirmer, Préparer, Marquer prête, Remettre).
- Stock et précommandes : produits à 0 avec le nombre de précommandes et la date d'arrivage ; produits sous le seuil d'alerte.
- Ventes des 7 derniers jours et arrivages attendus.

### 4.2 Commandes

- Liste paginée, filtrable, triable ; fiche commande avec historique des statuts (qui, quand).
- Bon de préparation imprimable.
- Création d'une commande au comptoir ou au téléphone par l'équipe.
- Annulation avec motif ; remise du stock si des lignes étaient réservées.

### 4.3 Entrée en stock

Trois gestes par produit :

1. Scanner le code-barres ou chercher le produit (suggestions des arrivages attendus).
2. Choisir le **format reçu** (carton de 50, sac de 25 kg…) et la **quantité** ; saisir le n° de lot, la date limite et le coût (facultatif).
3. Valider.

L'écran montre pour chaque ligne la quantité convertie (« + 500 boules »), le stock avant et après, et les précommandes servies. L'en-tête du bon contient le fournisseur, la date et le n° de bon de livraison. Options à la validation : aviser les clients dont la précommande est prête, imprimer les étiquettes de lot.

### 4.4 Produits et formats

- Informations : nom, catégorie, marque, photo, code-barres, surgelé, taxable, visible en ligne, précommande permise.
- Stock : unité de stock, seuil d'alerte.
- Formats de vente, regroupés par canal (Détail, En gros) : unité de vente, nom pour le client, contenu (quantité + unité), prix, minimum, pas ; calcul automatique du prix par unité de stock pour repérer les erreurs.
- Aperçu de la carte telle que le client la voit, en mode Détail et En gros.

### 4.5 Réglages

- **Unités de vente** : sac, unité, L, kg, g, actives ou non ; ajout d'unités (carton, botte, douzaine…) avec décimales permises et pas par défaut.
- Catégories (nom, icône, couleur, ordre).
- Taxes (TPS, TVQ, numéros d'inscription), coordonnées du commerce, courriel Interac.
- Ramassage (adresse, horaires, créneaux) ; livraison (zones, frais, jours) — à confirmer.
- Utilisateurs et rôles.

### 4.6 Clients

Coordonnées, type (particulier ou gros), historique, solde dû, consentement et date de consentement.

## 5. Règles métier

### 5.1 Stock et conversions

- Chaque produit a **une** unité de stock. `stock_disponible = Σ lots.quantité_restante − quantités réservées`.
- Chaque format a `contenu_en_unite_de_stock`. Une vente de `q` formats retire `q × contenu` du stock.
- Vente au poids : la quantité est en kg ou en g, avec le pas défini (ex. 0,5 kg).
- Sortie de stock **FEFO** : le lot dont la date limite arrive en premier sort en premier ; à date égale, le plus ancien (FIFO).

### 5.2 Précommandes

- Si `stock_disponible < quantité demandée` et que la précommande est permise : la ligne est créée avec `precommande = true` pour la partie manquante (ou toute la ligne : à décider, réglage par défaut « toute la ligne »).
- La date d'arrivage affichée vient du prochain arrivage attendu ; si aucune date n'existe, la précommande n'est pas permise.
- À la validation d'une entrée en stock, les lignes en précommande du produit sont servies dans l'ordre de création, jusqu'à épuisement ; chaque client servi reçoit un courriel.
- Une commande qui contient une précommande non servie reste « Reçue » ou « Confirmée » ; elle ne peut pas passer « Prête ».

### 5.3 Prix et taxes

- Le prix est figé sur la ligne au moment de la commande.
- Taxes calculées sur le sous-total des lignes `taxable` : TPS 5 %, TVQ 9,975 %, chacune arrondie au cent (demi vers le haut) sur le total de la facture.
- Exemple de contrôle (facture de la maquette) : sous-total 247,42 $ dont 27,48 $ taxables → TPS 1,37 $, TVQ 2,74 $, total 251,53 $.

### 5.4 Factures et paiements

- Numéro `F-AAAA-NNNN`, continu, attribué dans une transaction verrouillée.
- La facture est créée à la remise (ou à la confirmation pour les clients de gros, selon réglage).
- Une facture émise ne se modifie pas ; annulation totale ou partielle par **note de crédit** `NC-AAAA-NNNN`.
- Paiements : Interac, comptant, débit, (carte plus tard). Statuts : à payer, payée partiellement, payée. Rapprochement Interac manuel par l'équipe.
- Documents conservés au moins 6 ans (à confirmer avec le comptable).

### 5.5 Statuts de commande

`recue → confirmee → en_preparation → prete → remise`, et `annulee` depuis tout statut avant `remise`. Chaque changement est journalisé et peut déclencher un courriel (confirmée, prête).

## 6. Documents et rapports comptables

Référence : `maquettes/facture.html`, `maquettes/rapport-mensuel.html`.

| Document | Contenu | Format |
| --- | --- | --- |
| Facture | Coordonnées, n° TPS/TVQ, lignes (format, canal, lot), sous-total, TPS, TVQ, total, instructions Interac | PDF, courriel |
| Reçu de paiement | Montant, date, mode, solde | PDF |
| Note de crédit | Facture d'origine, lignes, taxes | PDF |
| Journal des ventes | Factures d'une période : date, n°, client, canal, avant taxes, TPS, TVQ, total, payé | Écran, CSV |
| Rapport de taxes | Ventes détaxées, taxables, notes de crédit, TPS et TVQ perçues | PDF, CSV |
| Comptes clients | Impayés par âge (0-30, 31-60, 60+ jours) | Écran, PDF |
| Inventaire valorisé | Stock par produit et par lot, coût, valeur, lots proches de la date limite | PDF, CSV |
| Journal des entrées en stock | Date, fournisseur, produit, format reçu, quantité, lot, coût, employé | Écran, CSV |
| Rapport mensuel | Synthèse : ventes, panier moyen, taxes, détail / gros, catégories, meilleurs produits | Écran, PDF |

Les CSV utilisent le séparateur `;`, l'encodage UTF-8 avec BOM et la virgule décimale, pour s'ouvrir directement dans Excel en français. Prévoir aussi un export compatible avec l'import de QuickBooks (à confirmer selon le logiciel du comptable).

## 7. Modèle de données (proposition)

Montants en cents (`BIGINT`), quantités en `DECIMAL(12,3)`.

| Table | Champs principaux |
| --- | --- |
| `categories` | name, slug, icon, color, sort_order |
| `units` | code (sac, unite, l, kg, g…), label, allows_decimals, default_step, active |
| `products` | category_id, name, brand, barcode, photo_path, stock_unit_id, is_frozen, is_taxable, is_visible, allow_preorder, alert_threshold |
| `sale_formats` | product_id, channel (detail/gros), unit_id, label, content_qty (en unité de stock), price_cents, min_qty, step, is_visible, sort_order |
| `suppliers` | name, contact, phone, email, usual_delay_days |
| `stock_receipts` | supplier_id, received_at, delivery_note_no, user_id |
| `lots` | product_id, stock_receipt_id, sale_format_id (format reçu), received_qty, converted_qty, remaining_qty, lot_number, best_before, cost_cents |
| `expected_arrivals` | supplier_id, product_id, expected_on, qty |
| `customers` | name, phone, email, address, type (particulier/gros), consent_at, approved_wholesale |
| `orders` | number, customer_id, channel, status, fulfilment (ramassage/livraison), slot_start, slot_end, payment_method, subtotal_cents, tax_tps_cents, tax_tvq_cents, total_cents, tracking_token |
| `order_items` | order_id, sale_format_id, product_name, format_label, qty, unit_price_cents, is_taxable, is_preorder, preorder_served_at |
| `order_item_lots` | order_item_id, lot_id, qty |
| `order_status_history` | order_id, from, to, user_id, note, created_at |
| `invoices` | number, order_id, customer_id, issued_at, subtotal, tps, tvq, total, balance_cents, status |
| `credit_notes` | number, invoice_id, issued_at, amounts, reason |
| `payments` | invoice_id, method, amount_cents, paid_at, reference, user_id |
| `settings` | key, value (taxes, numéros, courriel Interac, horaires…) |
| `users` | name, email, password, role (gerant/employe) |
| `audit_log` | user_id, action, subject_type, subject_id, changes (JSON), created_at |

## 8. Identité visuelle

- Logo « La porteuse » : `maquettes/assets/logo-porteuse.svg` (badge) et `icone-porteuse.svg` (icône, favicon).
- Couleurs : vert feuille `#1F4D2E`, osier `#D49A3A`, terre `#A8432A`, manioc `#F7F2E8`, encre `#1E1B16` ; couleurs de catégorie dans `maquettes/commande.html` (objet `teintes`).
- Polices : Bricolage Grotesque (titres), Manrope (texte et chiffres), via Google Fonts ou hébergées localement (préférable pour la Loi 25 et la vitesse).
- Composants à reprendre des maquettes : boutons, pastilles d'état, cartes produit, cartes de catégorie, tiroir, feuille de filtres, barre du total.

## 9. Exigences non fonctionnelles

- **Responsive** de 320 px à 1920 px : iPhone SE à iPhone Pro Max, Android, iPad, tablettes Android, portables, grands écrans. Aucun défilement horizontal. Tester au minimum à 320, 390, 430, 768, 1024, 1280 et 1920 px, et sur un vrai iPhone (Safari) et un vrai Android (Chrome).
- **iOS** : champs à 16 px, `viewport-fit=cover`, zones sûres, `100dvh`.
- **Performance** : page de commande utilisable en moins de 2 s sur réseau mobile ; images en WebP redimensionnées ; mise en cache HTTP des ressources compilées.
- **Accessibilité** : WCAG 2.1 AA (contraste, clavier, libellés, `aria-live` pour le total et les avis).
- **Sécurité** : HTTPS forcé, protection CSRF, limitation des envois de commande, mots de passe hachés, double authentification pour le gérant (souhaitée), journal d'audit des prix et du stock.
- **Loi 25** : responsable des renseignements personnels désigné, politique de confidentialité publiée, consentement explicite à la commande, données hébergées au Canada (Montréal), droit d'accès et de suppression, pas de traceur publicitaire.
- **Charte de la langue française** : interface, courriels et documents en français.
- **Sauvegardes** : sauvegardes PlanetHoster + export quotidien de la base par cron vers un dossier hors du web.

## 10. Hébergement PlanetHoster

Voir `docs/deploiement-planethoster.md`. Résumé : hébergement mutualisé N0C, centre de données de Montréal, PHP 8.x, MySQL, SSH, Git, SSL gratuit. Pas de Docker ni de processus permanent : files d'attente et tâches planifiées par cron.

## 11. Données de démonstration

Les seeders reprennent les produits des maquettes : Water Leaf et feuilles de ndolé DJM, gari jaune, couscous de manioc (précommande), plantain mûr (précommande, au poids), machoiron fumé, crevettes séchées, huile de palme rouge, piment rouge moulu, graines de njansang, Malta (taxable). Ces données servent à la démonstration seulement.

## 12. Plan de réalisation par étapes

Chaque étape se termine par des tests verts, un résumé et un commit.

1. **Socle** : projet Laravel, configuration fr-CA, authentification de l'équipe, rôles, gabarits Blade de base, jetons CSS des maquettes, déploiement de test sur PlanetHoster.
2. **Catalogue** : catégories, unités, produits, formats de vente, réglages ; écran Produits et formats.
3. **Stock** : fournisseurs, entrées en stock, lots, conversions, arrivages attendus ; écran Entrée en stock.
4. **Page de commande** : catégories en cartes, liste alphabétique, filtres, détail / gros, panier avec total toujours visible, finalisation, courriels.
5. **Précommandes** : création, affichage, service automatique à l'entrée en stock, avis aux clients.
6. **Gestion des commandes** : tableau de bord, statuts, bon de préparation, commande au comptoir.
7. **Facturation** : factures PDF, notes de crédit, paiements, rapprochement Interac.
8. **Rapports** : journal des ventes, rapport de taxes, comptes clients, inventaire valorisé, journal des entrées, rapport mensuel, exports CSV.
9. **Finition** : accessibilité, tests sur appareils réels, performance, Loi 25 (politique, consentement, export et suppression), sauvegardes, mise en production.

## 13. Questions ouvertes (réponses attendues du client)

- Nombre de produits au catalogue ; qui fournit les photos ?
- Prix de gros visibles par tous ou réservés aux comptes approuvés ?
- Minimums de gros par produit (quantité ou montant) ?
- Précommandes : acompte ou paiement à la remise ? Délai maximal ? Ligne entière ou seulement la partie manquante ?
- Vente au poids : pesée au comptoir, pas de 0,5 kg ou 100 g ?
- Livraison : zone, frais, jours ; les surgelés sont-ils livrés ?
- Paiements acceptés : Interac seulement, comptant, débit, carte ?
- Numéros TPS et TVQ, nom du comptable et logiciel comptable utilisé.
- Nombre de comptes employés ; qui peut modifier les prix et les formats ?
- Codes-barres existants sur les produits, ou étiquettes à imprimer ?
- Nom de domaine (ex. lebonpanier.ca) et adresse courriel d'envoi.
