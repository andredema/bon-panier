#!/usr/bin/env bash
# À lancer sur le serveur PlanetHoster (SSH), dans le dossier de l'application,
# après chaque dépôt de l'archive. Le fichier .env doit déjà exister.
#
#   cd ~/apps/bon-panier && bash scripts/post-deploy.sh
#   PHP=/chemin/vers/php83 bash scripts/post-deploy.sh   # si « php » n'est pas en 8.3
set -euo pipefail

cd "$(dirname "$0")/.."
PHP="${PHP:-php}"

[ -f .env ] || { echo "Le fichier .env est absent : copiez .env.example en .env et remplissez-le." >&2; exit 1; }
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
  || { echo "PHP 8.3 ou plus récent est requis (trouvé : $("$PHP" -r 'echo PHP_VERSION;'))." >&2; exit 1; }

grep -q '^APP_KEY=base64:' .env || "$PHP" artisan key:generate --force

"$PHP" artisan down --retry=30 || true
"$PHP" artisan migrate --force
[ -e public/storage ] || "$PHP" artisan storage:link
"$PHP" artisan optimize
"$PHP" artisan up

echo "Mise à jour terminée."
