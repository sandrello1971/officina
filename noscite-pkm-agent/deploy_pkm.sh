#!/bin/bash
# deploy_pkm.sh — Aggiorna tutti i file del PKM Agent da ~/Downloads
# Uso: bash ~/deploy_pkm.sh [--no-restart] [--no-regen]

AGENT_DIR="$HOME/Scripts/obsidian_agent"
DOWNLOADS="$HOME/Downloads"
VAULT="/Users/lsala/Library/Mobile Documents/iCloud~md~obsidian/Documents/my_PKM"
PLIST="$HOME/Library/LaunchAgents/com.obsidian.agent.plist"
RESTART=true
REGEN=true

for arg in "$@"; do
  case $arg in
    --no-restart) RESTART=false ;;
    --no-regen)   REGEN=false ;;
  esac
done

echo "╔══════════════════════════════════════════╗"
echo "║   PKM Agent — Deploy                     ║"
echo "╚══════════════════════════════════════════╝"
echo ""

COPIED=0
SKIPPED=0

copy_if_newer() {
  local filename="$1"
  local src="$DOWNLOADS/$filename"
  local dst="$AGENT_DIR/$filename"
  if [ -f "$src" ]; then
    if [ ! -f "$dst" ]; then
      cp "$src" "$dst"
      echo "  ✓ $filename (nuovo)"
      COPIED=$((COPIED + 1))
    else
      # Confronta contenuto con MD5 — ignora la data di modifica
      src_md5=$(md5 -q "$src" 2>/dev/null || md5sum "$src" | cut -d' ' -f1)
      dst_md5=$(md5 -q "$dst" 2>/dev/null || md5sum "$dst" | cut -d' ' -f1)
      if [ "$src_md5" != "$dst_md5" ]; then
        cp "$src" "$dst"
        echo "  ✓ $filename (aggiornato)"
        COPIED=$((COPIED + 1))
      else
        echo "  · $filename (identico, skip)"
        SKIPPED=$((SKIPPED + 1))
      fi
    fi
  fi
}

copy_if_newer "analyzer.py"
copy_if_newer "api_utils.py"
copy_if_newer "config.py"
copy_if_newer "dashboard_server.py"
copy_if_newer "extractor.py"
copy_if_newer "html_dashboard.py"
copy_if_newer "index_builder.py"
copy_if_newer "linker.py"
copy_if_newer "md_generator.py"
copy_if_newer "processor.py"
copy_if_newer "reprocess_all.py"
copy_if_newer "report_generator.py"
copy_if_newer "translator.py"
copy_if_newer "watcher.py"

echo ""
echo "  Copiati: $COPIED  |  Skippati: $SKIPPED"
echo ""

if [ "$REGEN" = true ]; then
  echo "── Rigenerazione dashboard.html..."
  cd "$AGENT_DIR"
  source venv/bin/activate
  OBSIDIAN_VAULT="$VAULT" python3 -c "import html_dashboard; html_dashboard.update()" && \
    echo "  ✓ dashboard.html aggiornata" || \
    echo "  ⚠ Errore rigenerazione dashboard"
  echo ""
fi

if [ "$RESTART" = true ]; then
  echo "── Riavvio servizio..."
  LABEL=$(defaults read "$PLIST" Label 2>/dev/null || \
    /usr/libexec/PlistBuddy -c "Print :Label" "$PLIST" 2>/dev/null)
  DOMAIN="gui/$(id -u)"

  # Ferma e deregistra il servizio (bootout è l'API moderna di unload)
  launchctl bootout "$DOMAIN" "$PLIST" 2>/dev/null && echo "  · Servizio fermato"

  # Attende che la porta si liberi (max 10s)
  PORT_KEY=$(defaults read "$PLIST" EnvironmentVariables 2>/dev/null | \
    grep -o 'PKM_PORT = [0-9]*' | awk '{print $3}')
  if [ -n "$PORT_KEY" ]; then
    for i in $(seq 1 10); do
      lsof -ti :"$PORT_KEY" > /dev/null 2>&1 || break
      sleep 1
    done
  else
    sleep 2
  fi

  # Avvia e registra il servizio (bootstrap è l'API moderna di load)
  if launchctl bootstrap "$DOMAIN" "$PLIST"; then
    echo "  ✓ Servizio registrato"
    # Verifica avvio effettivo
    sleep 2
    if launchctl list "$LABEL" > /dev/null 2>&1; then
      echo "  ✓ Servizio attivo ($(launchctl list "$LABEL" | awk 'NR==2{print "PID "$1}'))"
    else
      echo "  ⚠ Servizio registrato ma non ancora avviato — verifica i log"
    fi
  else
    echo "  ✗ Errore avvio servizio — verifica il plist e i permessi"
  fi
  echo ""
fi

echo "╔══════════════════════════════════════════╗"
echo "║   Deploy completato                      ║"
echo "╚══════════════════════════════════════════╝"
