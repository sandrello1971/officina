#!/usr/bin/env bash
#
# deploy-videoai.sh — deploy di noscite-videoai dal monorepo a produzione.
#
# Sincronizza il sorgente dal monorepo (questa cartella) verso la dir di
# produzione via rsync, PRESERVANDO i dati e i segreti che vivono solo in
# produzione: .env, data/ (sqlite + video ingeriti, non ricostruibili) e il
# venv/ residuo locale. Il servizio gira col venv CONDIVISO /home/noscite/venv,
# quindi questo script NON tocca le dipendenze: le deps nuove vanno installate
# a parte (vedi requirements.txt) PRIMA del restart.
#
# Uso:
#   ./deploy-videoai.sh --dry-run   # mostra cosa cambierebbe, non scrive nulla
#   ./deploy-videoai.sh             # applica le modifiche
#
# Dopo il deploy, ricordarsi: pip install delle deps nuove nel venv condiviso
# e `sudo systemctl restart noscite-videoai`.
#
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/"
DEST="/var/www/noscite-videoai/"

# File/dir che vivono SOLO in produzione: mai sovrascritti né cancellati da
# --delete (le regole di exclude proteggono anche dalla cancellazione).
EXCLUDES=(
  --exclude='.env'           # segreti di produzione
  --exclude='data/'          # sqlite + video ingeriti (non ricostruibili)
  --exclude='data-dev/'      # dati di sviluppo del monorepo: non vanno in prod
  --exclude='venv/'          # venv residuo locale (il servizio usa /home/noscite/venv)
  --exclude='__pycache__/'
  --exclude='*.pyc'
  --exclude='.pytest_cache/'
  --exclude='.git/'
)

DRY=()
MODE="APPLY"
if [[ "${1:-}" == "--dry-run" ]]; then
  DRY=(--dry-run)
  MODE="DRY-RUN"
fi

echo "=== deploy-videoai.sh [$MODE] ==="
echo "SRC : $SRC"
echo "DEST: $DEST"
echo

rsync -a --delete --itemize-changes "${DRY[@]}" "${EXCLUDES[@]}" "$SRC" "$DEST"

if [[ "$MODE" == "DRY-RUN" ]]; then
  echo
  echo "(dry-run: nessuna modifica applicata)"
fi
