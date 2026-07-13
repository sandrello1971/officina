#!/usr/bin/env bash
#
# rollback-atheneum.sh — riporta la PRODUZIONE al commit deployato PRECEDENTE,
# riusando la stessa sequenza collaudata di deploy-atheneum.sh (rsync + composer
# + build + cache + restart). Git-native: nessuna ristrutturazione dell'infra.
#
# Legge lo storico scritto da deploy-atheneum.sh (/home/noscite/atheneum-deploy-history.log):
# l'ULTIMA riga è il rilascio corrente, la PENULTIMA è il target del rollback.
#
# ⚠️ NON annulla le migrazioni DB del rilascio più recente: le migrazioni sono
# additive e i loro `down` spesso non sono affidabili. Se il rilascio da annullare
# ha introdotto migrazioni distruttive, valutare a mano un ripristino DB (backup).
#
# Uso (come noscite, con sudo a disposizione per fpm/queue):
#   ./rollback-atheneum.sh              # rollback al deploy precedente (con conferma)
#   ./rollback-atheneum.sh <sha>        # rollback a uno SHA specifico dello storico
#
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEST="/var/www/noscite-atheneum"
FPM_SERVICE="php8.4-fpm"
QUEUE_SERVICE="noscite-atheneum-queue.service"
HISTORY="/home/noscite/atheneum-deploy-history.log"

# Gli stessi EXCLUDES del deploy: proteggono ciò che vive solo in prod.
EXCLUDES=(
  --exclude='.env'
  --exclude='/storage/'
  --exclude='/vendor/'
  --exclude='/node_modules/'
  --exclude='/bootstrap/cache/'
  --exclude='/noscite-videoai/'
  --exclude='.git/'
)

[[ -f "$HISTORY" ]] || { echo "ERRORE: storico assente ($HISTORY). Serve almeno un deploy registrato."; exit 1; }

CURRENT_SHA="$(tail -n 1 "$HISTORY" | awk '{print $2}')"
if [[ -n "${1:-}" ]]; then
  TARGET_SHA="$1"
else
  TARGET_SHA="$(tail -n 2 "$HISTORY" | head -n 1 | awk '{print $2}')"
fi

if [[ -z "$TARGET_SHA" || "$TARGET_SHA" == "$CURRENT_SHA" ]]; then
  echo "ERRORE: nessun rilascio precedente diverso da quello corrente ($CURRENT_SHA)."
  echo "Storico:"; tail -n 5 "$HISTORY"
  exit 1
fi

echo "=== ROLLBACK ==="
echo "corrente: $CURRENT_SHA"
echo "target  : $TARGET_SHA"
git -C "$REPO" --no-pager log -1 --oneline "$TARGET_SHA" 2>/dev/null || true
read -rp "Confermi il rollback della PRODUZIONE? [y/N] " ok
[[ "$ok" == "y" || "$ok" == "Y" ]] || { echo "annullato."; exit 0; }

echo "==> checkout $TARGET_SHA nel repo sorgente"
git -C "$REPO" fetch --quiet origin || true
git -C "$REPO" checkout --quiet "$TARGET_SHA"

echo "==> manutenzione ON"
php "$DEST/artisan" down || true

echo "==> rsync codice (preserva .env, storage/, vendor/, node_modules/)"
rsync -a --delete "${EXCLUDES[@]}" "$REPO/" "$DEST/"

echo "==> composer install --no-dev (ripristina le deps del rilascio target)"
composer install --no-dev --optimize-autoloader --working-dir="$DEST"

echo "==> npm ci && build (asset del rilascio target)"
( cd "$DEST" && npm ci && npm run build )

echo "==> cache config/route/view"
php "$DEST/artisan" config:cache
php "$DEST/artisan" route:cache
php "$DEST/artisan" view:cache

echo "==> reload php-fpm + restart queue"
sudo systemctl reload "$FPM_SERVICE"
sudo systemctl restart "$QUEUE_SERVICE"

echo "==> manutenzione OFF"
php "$DEST/artisan" up

printf '%s %s (rollback da %s)\n' "$(date +%Y-%m-%dT%H:%M:%S)" "$TARGET_SHA" "$CURRENT_SHA" >> "$HISTORY"
git -C "$REPO" checkout --quiet main || true

echo "=== ROLLBACK COMPLETATO a $TARGET_SHA ==="
echo "⚠️  Le migrazioni DB del rilascio $CURRENT_SHA NON sono state annullate."
echo "    Se necessario, ripristina il DB da backup manualmente."
