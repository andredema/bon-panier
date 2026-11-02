# Déploiement sur PlanetHoster (N0C)

Guide de mise en ligne de l'application Laravel Le Bon Panier sur un hébergement mutualisé PlanetHoster géré avec le panneau N0C. Les noms exacts des menus de N0C peuvent varier : vérifier dans le panneau et dans la documentation de PlanetHoster avant la première mise en ligne.

## 1. Ce que l'hébergement fournit

- Centre de données à choisir : **Montréal** (données au Canada, Loi 25).
- PHP en plusieurs versions (choisir **8.3** ou plus récent compatible avec Laravel 11).
- Bases MySQL, accès **SSH** et **Git**, certificats **Let's Encrypt** gratuits et renouvelés automatiquement.
- Ce qui manque sur le mutualisé : Docker, processus permanents (workers), Redis. On s'en passe :
  - file d'attente `database`, vidée par cron ;
  - cache et sessions `file` ou `database`.

## 2. Préparer le compte

1. Créer la base MySQL et son utilisateur dans N0C ; noter l'hôte, le nom, l'utilisateur et le mot de passe.
2. Choisir la version de PHP du domaine (8.3+) et activer les extensions nécessaires : `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `fileinfo`, `openssl`.
3. Créer l'adresse courriel d'envoi (ex. `commandes@lebonpanier.ca`) et noter les paramètres SMTP.
4. Activer le certificat SSL du domaine.

## 3. Arborescence recommandée

Le code de l'application reste **hors du dossier public** ; seul `public/` est exposé.

```
~/apps/bon-panier/          ← dépôt Git (code Laravel)
~/apps/bon-panier/public/   ← racine web du domaine
~/backups/                  ← sauvegardes de la base (hors du web)
```

Dans N0C, faire pointer la **racine du document** du domaine vers `~/apps/bon-panier/public`. Si ce n'est pas possible, garder `public_html` comme racine et y placer le contenu de `public/` en adaptant `index.php`.

## 4. Première mise en ligne (SSH)

```bash
cd ~/apps
git clone <adresse-du-depot> bon-panier
cd bon-panier
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# éditer .env : APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, MAIL_*, QUEUE_CONNECTION=database
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Les fichiers front (`public/build`) sont compilés **en local** (`npm run build`), puis ajoutés au dépôt ou envoyés par SFTP : on ne lance pas `npm` sur le serveur.

## 5. Tâches planifiées (cron)

Créer dans N0C une tâche qui s'exécute **chaque minute** :

```
* * * * * cd ~/apps/bon-panier && php artisan schedule:run >> /dev/null 2>&1
```

Dans `routes/console.php`, le planificateur :

- vide la file d'attente : `queue:work --stop-when-empty --max-time=50` chaque minute (courriels, avis de précommande) ;
- fait une sauvegarde quotidienne de la base dans `~/backups` et garde 30 jours ;
- envoie le rappel des factures impayées (si activé).

À confirmer : l'intervalle minimal permis par N0C pour le cron. S'il est de 5 minutes, adapter `--max-time` en conséquence.

## 6. Mises à jour

```bash
cd ~/apps/bon-panier
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## 7. Vérifications après la mise en ligne

- [ ] `https://` forcé, certificat valide.
- [ ] `APP_DEBUG=false` ; la page d'erreur ne montre aucun détail technique.
- [ ] Le dossier `storage/` et le fichier `.env` ne sont pas accessibles depuis le web.
- [ ] Un courriel de test part et arrive (confirmation de commande).
- [ ] Le cron tourne (la file d'attente se vide, la sauvegarde du jour existe).
- [ ] La page de commande s'affiche correctement sur un vrai iPhone et un vrai Android.
- [ ] Une facture PDF se génère avec les accents et le logo.
