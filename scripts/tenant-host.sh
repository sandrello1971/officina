#!/usr/bin/env bash
#
# tenant-host.sh — pubblica un host di Officina su nginx + TLS (da ROOT).
#
# Un ente con host base B è servito su admin.B e learn.B; la console di
# piattaforma sul suo host singolo. Il vhost include lo stesso snippet
# dell'app (snippets/officina-app.conf) usato da admin./learn. di Effetto
# Glitch; certbot aggiunge poi i blocchi 443 e il redirect da 80.
#
# Prerequisito: DNS di tutti gli host verso questo server (per i sottodomini di
# officina.effettoglitch.it basta il record wildcard *.officina.effettoglitch.it).
#
# Uso:
#   sudo scripts/tenant-host.sh ente.officina.effettoglitch.it          # ente: admin.* + learn.*
#   sudo scripts/tenant-host.sh --single platform.officina.effettoglitch.it  # console
#
set -euo pipefail

SINGLE=0
if [[ "${1:-}" == "--single" ]]; then SINGLE=1; shift; fi
BASE="${1:?host mancante}"

if ! [[ "$BASE" =~ ^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$ ]]; then
  echo "Host non valido: $BASE"; exit 1
fi

if [[ $SINGLE -eq 1 ]]; then HOSTS=("$BASE"); else HOSTS=("admin.$BASE" "learn.$BASE"); fi

CONF="/etc/nginx/sites-available/officina-host-$BASE"
if [[ -e "$CONF" ]]; then
  echo "Esiste già $CONF: per rigenerarlo rimuovilo prima."; exit 1
fi

{
  echo "# Generato da scripts/tenant-host.sh — host: ${HOSTS[*]}"
  for h in "${HOSTS[@]}"; do
    printf '\nserver {\n    listen 80;\n    listen [::]:80;\n    server_name %s;\n\n    include snippets/officina-app.conf;\n}\n' "$h"
  done
} > "$CONF"

ln -s "$CONF" "/etc/nginx/sites-enabled/officina-host-$BASE"
nginx -t
systemctl reload nginx

CERT_ARGS=()
for h in "${HOSTS[@]}"; do CERT_ARGS+=(-d "$h"); done
certbot --nginx --non-interactive --agree-tos --redirect --cert-name "officina-$BASE" "${CERT_ARGS[@]}"

echo "OK: ${HOSTS[*]} pubblicati con TLS."
