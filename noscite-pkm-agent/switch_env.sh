#!/bin/bash
# switch_env.sh — Scambia ambiente produzione/sviluppo PKM Agent
#
# Uso:
#   bash switch_env.sh           → mostra stato attuale
#   bash switch_env.sh prod      → attiva produzione (ferma sviluppo)
#   bash switch_env.sh dev       → attiva sviluppo (ferma produzione)
#   bash switch_env.sh stop      → ferma tutto
#
# Suggerimento: symlink rapido
#   ln -sf ~/Scripts/obsidian_agent/switch_env.sh ~/switch_env.sh

PLIST_PROD="$HOME/Library/LaunchAgents/com.obsidian.agent.plist"
PLIST_DEV="$HOME/Library/LaunchAgents/com.obsidian.agent.develop.plist"
LABEL_PROD="com.obsidian.agent"
LABEL_DEV="com.obsidian.agent.develop"
DOMAIN="gui/$(id -u)"
PORT=5050
MAX_WAIT=20  # secondi di attesa per la risposta HTTP

# ── Utility ────────────────────────────────────────────────────────────────

_get_pid() {
  launchctl list "$1" 2>/dev/null | awk '/"PID"/ {gsub(/[";]/, "", $3); print $3}'
}

_is_running() {
  local pid
  pid=$(_get_pid "$1")
  [[ -n "$pid" && "$pid" != "0" ]]
}

_stop() {
  local label="$1" plist="$2" pid
  pid=$(_get_pid "$label")
  launchctl bootout "$DOMAIN" "$plist" 2>/dev/null
  [[ -n "$pid" ]] && kill "$pid" 2>/dev/null
  # Attende che la porta si liberi (max 10s)
  local i
  for i in $(seq 1 10); do
    lsof -ti :"$PORT" > /dev/null 2>&1|| break
    sleep 1
  done
  echo "  · $(basename "$plist" .plist) fermato"
}

_start() {
  local label="$1" plist="$2" name="$3"
  launchctl bootstrap "$DOMAIN" "$plist" 2>/dev/null
  launchctl start "$label" 2>/dev/null
  # Attende che il servizio risponda
  local i
  for i in $(seq 1 "$MAX_WAIT"); do
    if curl -sf "http://127.0.0.1:$PORT/status" > /dev/null 2>&1; then
      echo "  ✓ $name attivo su http://127.0.0.1:$PORT"
      return 0
    fi
    sleep 1
  done
  echo "  ⚠ $name non risponde dopo ${MAX_WAIT}s — controlla i log"
  return 1
}

_status() {
  echo "── Stato PKM Agent ───────────────────────────────────────────"
  if _is_running "$LABEL_PROD"; then
    printf "  PRODUZIONE   \033[32m[ATTIVA]\033[0m  PID %s\n" "$(_get_pid "$LABEL_PROD")"
  else
    printf "  PRODUZIONE   \033[90m[ferma]\033[0m\n"
  fi
  if _is_running "$LABEL_DEV"; then
    printf "  SVILUPPO     \033[32m[ATTIVA]\033[0m  PID %s\n" "$(_get_pid "$LABEL_DEV")"
  else
    printf "  SVILUPPO     \033[90m[ferma]\033[0m\n"
  fi
  local port_pid
  port_pid=$(lsof -ti :"$PORT" 2>/dev/null | head -1)
  if [[ -n "$port_pid" ]]; then
    printf "  Porta %-5s  in uso da PID %s\n" "$PORT" "$port_pid"
  else
    printf "  Porta %-5s  libera\n" "$PORT"
  fi
  echo "──────────────────────────────────────────────────────────────"
}

# ── Main ───────────────────────────────────────────────────────────────────

TARGET="${1:-status}"

case "$TARGET" in

  status|"")
    _status
    ;;

  prod|produzione)
    echo "╔══════════════════════════════════════════╗"
    echo "║  PKM Agent → Produzione                  ║"
    echo "╚══════════════════════════════════════════╝"
    _is_running "$LABEL_DEV"  && { echo "── Arresto sviluppo...";    _stop  "$LABEL_DEV"  "$PLIST_DEV";  }
    _is_running "$LABEL_PROD" && { echo "── Riavvio produzione...";  _stop  "$LABEL_PROD" "$PLIST_PROD"; } \
                               || echo "── Avvio produzione..."
    _start "$LABEL_PROD" "$PLIST_PROD" "Produzione"
    echo ""
    _status
    ;;

  dev|develop|sviluppo)
    echo "╔══════════════════════════════════════════╗"
    echo "║  PKM Agent → Sviluppo                    ║"
    echo "╚══════════════════════════════════════════╝"
    _is_running "$LABEL_PROD" && { echo "── Arresto produzione...";  _stop  "$LABEL_PROD" "$PLIST_PROD"; }
    _is_running "$LABEL_DEV"  && { echo "── Riavvio sviluppo...";    _stop  "$LABEL_DEV"  "$PLIST_DEV";  } \
                               || echo "── Avvio sviluppo..."
    _start "$LABEL_DEV" "$PLIST_DEV" "Sviluppo"
    echo ""
    _status
    ;;

  stop)
    echo "── Arresto tutti i servizi PKM Agent..."
    _is_running "$LABEL_PROD" && _stop "$LABEL_PROD" "$PLIST_PROD"
    _is_running "$LABEL_DEV"  && _stop "$LABEL_DEV"  "$PLIST_DEV"
    echo "  ✓ Tutto fermato"
    ;;

  *)
    echo "Uso: $(basename "$0") [prod|dev|stop|status]"
    echo ""
    echo "  prod    — attiva produzione (ferma sviluppo)"
    echo "  dev     — attiva sviluppo   (ferma produzione)"
    echo "  stop    — ferma entrambi"
    echo "  status  — stato attuale (default)"
    exit 1
    ;;
esac
