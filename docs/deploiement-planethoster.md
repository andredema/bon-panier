# Déploiement sur PlanetHoster (N0C)

Guide de mise en ligne de l'application Laravel Le Bon Panier sur un hébergement mutualisé PlanetHoster géré avec le panneau N0C. Les noms exacts des menus de N0C peuvent varier : vérifier dans le panneau et dans la documentation de PlanetHoster avant la première mise en ligne.

## 1. Ce que l'hébergement fournit

- Centre de données à choisir : **Montréal** (données au Canada, Loi 25).
- PHP en plusieurs versions (choisir **8.3** ou plus récent, requis par Laravel 13).
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
#               DB_CONNECTION=mysql et DB_*, MAIL_* (SMTP PlanetHoster), LOG_LEVEL=warning,
#               BACKUP_PATH=/home/VOTRE_COMPTE/backups (dossier hors du web)
mkdir -p ~/backups && chmod 700 ~/backups
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
- fait une sauvegarde quotidienne de la base à 3 h 15 dans `BACKUP_PATH` (`~/backups`) et garde 30 jours — en place (voir section 8) ;
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
- [ ] « Mot de passe oublié ? » envoie bien le courriel de réinitialisation (vérifie le SMTP et APP_URL).
- [ ] Réglages › Commerce : courriel, téléphone et **responsable de la protection des renseignements personnels** remplis ; la page Confidentialité ne montre plus de texte entre crochets.
- [ ] `php artisan backup:database` crée un fichier dans `~/backups` ; un essai de restauration a été fait sur une base de test.
- [ ] Les en-têtes de sécurité sont présents (outil du navigateur › Réseau : `X-Frame-Options`, `Strict-Transport-Security`).
- [ ] Les fichiers de `/build/` sont servis avec `Cache-Control: max-age=31536000, immutable` (sinon, voir avec PlanetHoster si `mod_headers` est actif).

## 8. Sauvegardes et restauration

- **PlanetHoster** sauvegarde le compte (fichiers et bases) : vérifier dans N0C la fréquence et la durée de rétention, et comment restaurer.
- **En plus**, l’application exporte chaque jour la base à 3 h 15 (`php artisan backup:database`, lancé par le cron de la section 5) dans `BACKUP_PATH`, compressée, lisible seulement par le compte (droits 600). Les fichiers de plus de `BACKUP_KEEP_DAYS` jours (30 par défaut) sont supprimés.
- La commande est écrite en PHP : elle ne dépend pas de `mysqldump`. **À valider à la première mise en ligne** : elle n’a été essayée qu’avec SQLite sur le poste de développement.
- Les photos des produits sont dans `storage/app/public` : elles sont couvertes par la sauvegarde du compte PlanetHoster, pas par l’export quotidien.
- Pour garder une copie hors de PlanetHoster, télécharger de temps en temps le dernier fichier de `~/backups` (SFTP).

Restaurer (sur une base vide ou de test d’abord) :

```bash
cd ~/apps/bon-panier && php artisan down
gunzip < ~/backups/bon-panier-AAAAMMJJ-HHMMSS.sql.gz | mysql -h HOTE -u UTILISATEUR -p NOM_DE_LA_BASE
php artisan optimize && php artisan up
```

## 9. Loi 25 au quotidien

- **Demande d’accès** d’un client : Gestion › Clients › fiche du client › « Exporter ses renseignements » (gérant). Le fichier JSON contient ses coordonnées, ses commandes, ses paiements et ses factures ; le lui transmettre dans les 30 jours.
- **Demande d’effacement** : même fiche › « Effacer les renseignements personnels ». Le nom, le téléphone, le courriel et les adresses sont supprimés ; les factures émises restent intactes (obligation fiscale de 6 ans). L’effacement est refusé tant qu’une commande est en cours ou qu’un solde reste dû.
- **Correction** : la fiche client est modifiable par toute l’équipe.
- Chaque effacement est inscrit au journal d’audit, sans donnée personnelle.

## 10. Essais sur de vrais appareils (avant l’ouverture)

À faire par l’épicerie ou Zenviko, sur le site en ligne. Cocher chaque case :

| Parcours | iPhone (Safari) | Android (Chrome) | Tablette | Ordinateur |
| --- | --- | --- | --- | --- |
| Choisir une catégorie, filtrer, trier | | | | |
| Ajouter au détail et en gros, changer la quantité | | | | |
| Total du panier toujours visible, barre du bas non masquée par la barre d’accueil | | | | |
| Finaliser : champs sans zoom automatique, consentement, envoi | | | | |
| Courriel de confirmation reçu, lien de suivi qui s’ouvre | | | | |
| Gestion : confirmer → préparer → prête → remettre, facture PDF lisible | | | | |
| Entrée en stock avec précommande servie | | | | |
| Rotation de l’écran sans défilement horizontal | | | | |
