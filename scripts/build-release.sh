#!/usr/bin/env bash
# Construit l'archive à déposer sur PlanetHoster : code, dépendances de production
# (vendor/) et fichiers front compilés (public/build). Rien à installer sur le serveur.
#
#   bash scripts/build-release.sh            # PHP et Composer du PATH
#   PHP=php83 bash scripts/build-release.sh  # autre binaire PHP (8.3 minimum)
#
# Résultat : dist/bon-panier-AAAAMMJJ-HHMM-<commit>.zip
# Exclus : .env, base SQLite locale, tests, maquettes, docs, node_modules.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP="${PHP:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"
STAGE="$ROOT/dist/stage"
cd "$ROOT"

"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
  || { echo "PHP 8.3 ou plus récent est requis (trouvé : $("$PHP" -r 'echo PHP_VERSION;'))." >&2; exit 1; }

if [ -n "$(git status --porcelain)" ]; then
  echo "Attention : des modifications ne sont pas validées ; elles seront incluses dans l'archive." >&2
fi

echo "1/4 Compilation des fichiers front (Vite)…"
[ -d node_modules ] || npm ci
npm run build

echo "2/4 Copie des fichiers de l'application…"
rm -rf "$STAGE" && mkdir -p "$STAGE"
git ls-files -co --exclude-standard \
  | grep -vE '^(maquettes|docs|tests|node_modules|dist)/' \
  | grep -vE '^(index\.html|\.nojekyll|README\.md|CLAUDE\.md|phpunit\.xml|package\.json|package-lock\.json|vite\.config\.js|\.editorconfig|\.npmrc|scripts/build-release\.sh)$' \
  > "$ROOT/dist/files.txt"
tar -cf - -T "$ROOT/dist/files.txt" | (cd "$STAGE" && tar -xf -)
cp -R public/build "$STAGE/public/build"
rm -f "$ROOT/dist/files.txt"

echo "3/4 Dépendances PHP de production…"
"$COMPOSER" install --working-dir="$STAGE" --no-dev --optimize-autoloader --no-interaction --no-progress
rm -f "$STAGE"/bootstrap/cache/{config,routes-v7,events}.php

echo "4/4 Archive…"
NAME="bon-panier-$(date +%Y%m%d-%H%M)-$(git rev-parse --short HEAD)"
OUT="$ROOT/dist/$NAME.zip"
if command -v zip >/dev/null 2>&1; then
  (cd "$STAGE" && zip -qr "$OUT" .)
elif [ -x /c/Windows/System32/tar.exe ]; then
  # tar de Windows (bsdtar) sait écrire un .zip ; on liste les entrées une à une,
  # car un « ./ » en tête des chemins rend l'archive illisible pour l'Explorateur Windows.
  (cd "$STAGE" && shopt -s dotglob && /c/Windows/System32/tar.exe -a -c -f "$(cygpath -w "$OUT")" -- *)
else
  echo "Aucun outil pour créer le .zip (installez zip)." >&2; exit 1
fi
rm -rf "$STAGE"

echo "Archive prête : dist/$NAME.zip"
echo "Sur le serveur, après décompression : bash scripts/post-deploy.sh (voir docs/deploiement-planethoster.md)"
