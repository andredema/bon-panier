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

## 4. Première mise en ligne

### 4.1 Méthode recommandée : l'archive prête à déposer

Sur le poste de développement (Git Bash sous Windows), à la racine du dépôt :

```bash
PHP=php83 bash scripts/build-release.sh
```

Le script compile les fichiers front, installe les dépendances PHP **de production** et produit `dist/bon-panier-AAAAMMJJ-HHMM-<commit>.zip`. L'archive contient déjà `vendor/` et `public/build/` : **ni Composer ni npm ne tournent sur le serveur**. Elle ne contient ni `.env`, ni base de données, ni tests, ni maquettes.

Sur PlanetHoster :

1. Envoyer l'archive dans `~/apps/` (gestionnaire de fichiers N0C ou SFTP), puis la décompresser dans `~/apps/bon-panier/`.
2. Faire pointer la racine du document du domaine vers `~/apps/bon-panier/public` (voir section 3).
3. Par SSH :

```bash
cd ~/apps/bon-panier
cp .env.example .env
# éditer .env : APP_ENV=production, APP_DEBUG=false, APP_URL=https://…, SESSION_SECURE_COOKIE=true,
#               DB_CONNECTION=mysql et DB_*, MAIL_* (SMTP PlanetHoster), LOG_LEVEL=warning
php -v                          # doit afficher 8.3 ou plus ; sinon PHP=<chemin du php 8.3> devant la commande suivante
bash scripts/post-deploy.sh     # clé, migrations, lien storage, caches
php artisan users:create        # premier compte gérant (mot de passe saisi au clavier, jamais dans un fichier)
```

`.env` n'est créé qu'une fois : les mises à jour suivantes ne l'écrasent pas, puisque l'archive n'en contient pas.

### 4.2 Variante : clone Git sur le serveur

Possible si le dépôt est accessible depuis le serveur (clé de déploiement). Composer doit alors tourner sur le serveur, et `public/build` doit être envoyé à part (il n'est pas versionné).

```bash
cd ~/apps && git clone <adresse-du-depot> bon-panier && cd bon-panier
composer install --no-dev --optimize-autoloader
cp .env.example .env   # puis éditer comme ci-dessus
bash scripts/post-deploy.sh
```

## 5. Tâches planifiées (cron)

Créer dans N0C une tâche qui s'exécute **chaque minute** :

```
* * * * * cd ~/apps/bon-panier && php artisan schedule:run >> /dev/null 2>&1
```

Dans `routes/console.php`, le planificateur :

- vide la file d'attente : `queue:work --stop-when-empty --max-time=50` chaque minute (courriels, avis de précommande) — **en place depuis l'étape 1** ;
- purge les tâches en échec de plus de 30 jours — en place ;
- fait une sauvegarde quotidienne de la base dans `~/backups` et garde 30 jours — étape 9 ;
- envoie le rappel des factures impayées (si activé) — étape 7.

À confirmer : l'intervalle minimal permis par N0C pour le cron. S'il est de 5 minutes, mettre `QUEUE_CRON_MAX_TIME=280` dans `.env` puis relancer `php artisan optimize`.

## 6. Mises à jour

Avec l'archive : construire la nouvelle archive en local, la décompresser **par-dessus** `~/apps/bon-panier/` (le `.env` et `storage/` restent en place), puis :

```bash
cd ~/apps/bon-panier && bash scripts/post-deploy.sh
```

Le script met le site en maintenance, lance les migrations, reconstruit les caches et remet le site en ligne.

## 7. Vérifications après la mise en ligne

- [ ] `https://` forcé, certificat valide.
- [ ] `APP_DEBUG=false` ; la page d'erreur ne montre aucun détail technique.
- [ ] Le dossier `storage/` et le fichier `.env` ne sont pas accessibles depuis le web.
- [ ] Un courriel de test part et arrive (confirmation de commande).
- [ ] Le cron tourne (la file d'attente se vide, la sauvegarde du jour existe).
- [ ] La page de commande s'affiche correctement sur un vrai iPhone et un vrai Android.
- [ ] Une facture PDF se génère avec les accents et le logo.
