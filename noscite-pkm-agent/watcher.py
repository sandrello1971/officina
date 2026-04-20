"""
PKM Agent — SharePoint poller.
Sostituisce il vecchio watcher watchdog+filesystem.
"""

import logging
import signal
import sys
import time
import uuid
from pathlib import Path

from config import Config
from graph_client import graph
from processor import FileProcessor
from state_store import StateStore

log = logging.getLogger(__name__)

_shutdown = False


def _signal_handler(signum, frame):
    global _shutdown
    log.info(f"Ricevuto segnale {signum}, shutdown graceful...")
    _shutdown = True


def kb_sync_laravel():
    """Chiama 'php artisan kb:sync' per importare i metadati nel DB Laravel."""
    import subprocess
    try:
        r = subprocess.run(
            ['/usr/bin/php', 'artisan', 'kb:sync'],
            cwd=Config.LARAVEL_APP_PATH,
            capture_output=True, text=True, timeout=60,
        )
        if r.returncode == 0:
            log.info("[SYNC] kb:sync OK")
        else:
            log.warning(f"[SYNC] kb:sync exit {r.returncode}: {r.stderr.strip()[:300]}")
    except subprocess.TimeoutExpired:
        log.warning("[SYNC] kb:sync timeout")
    except Exception as e:
        log.warning(f"[SYNC] kb:sync errore: {e}")


def process_inbox_item(processor: FileProcessor, store: StateStore, item: dict) -> bool:
    """
    Processa un singolo item dall'_inbox SharePoint. Ritorna True se ok.
    """
    item_id = item["id"]
    filename = item["name"]

    if store.should_skip(item_id):
        return False

    log.info(f"▶ Processing: {filename} (id: {item_id[:12]}...)")

    Config.ensure_dirs()
    temp_file = Config.TEMP_DIR / f"{uuid.uuid4()}_{filename}"

    try:
        graph.download_item(item_id, temp_file)
        result = processor.process(temp_file)
        if not result:
            store.mark_error(item_id, filename, "processor returned None")
            return False

        # Upload .md in _metadata
        md_filename = f"{result['stem']}.md"
        graph.upload_text(Config.SP_METADATA, md_filename, result["md_content"])
        log.info(f"  ✓ Uploaded {md_filename} in _metadata")

        # Upload traduzione se presente
        if result.get("translation_stem") and result.get("translation_content"):
            tr_filename = f"{result['translation_stem']}.md"
            graph.upload_text(Config.SP_METADATA, tr_filename, result["translation_content"])
            log.info(f"  ✓ Uploaded {tr_filename} (traduzione)")

        # Move originale in _archive (rinominato con archive_filename che = filename originale)
        graph.move_item(item_id, Config.SP_ARCHIVE)
        log.info(f"  ✓ Moved to _archive")

        store.mark_ok(item_id, filename)
        log.info(f"✅ Completato: {filename}")
        return True

    except Exception as e:
        log.error(f"❌ Errore processing {filename}: {e}", exc_info=True)
        store.mark_error(item_id, filename, str(e))
        return False

    finally:
        try:
            if temp_file.exists():
                temp_file.unlink()
        except Exception:
            pass


def poll_once(processor: FileProcessor, store: StateStore) -> int:
    """Una singola passata sull'inbox SharePoint."""
    try:
        items = graph.list_folder(Config.SP_INBOX)
    except Exception as e:
        log.error(f"Errore list _inbox: {e}")
        return 0

    files = [i for i in items if "file" in i]
    if not files:
        return 0

    log.info(f"Trovati {len(files)} file in _inbox")
    processed = 0
    for item in files:
        if _shutdown:
            break
        if process_inbox_item(processor, store, item):
            processed += 1
            kb_sync_laravel()

    return processed


def main():
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s [%(levelname)s] %(message)s",
        handlers=[
            logging.FileHandler(Config.LOG_FILE),
            logging.StreamHandler(),
        ],
    )

    signal.signal(signal.SIGINT, _signal_handler)
    signal.signal(signal.SIGTERM, _signal_handler)

    log.info("=== PKM Agent (SharePoint edition) avviato ===")
    log.info(f"Poll interval: {Config.POLL_INTERVAL_SECONDS}s, mode: {Config.POLL_MODE}")

    processor = FileProcessor()
    store = StateStore(Config.STATE_DB)

    if Config.POLL_MODE == "once":
        count = poll_once(processor, store)
        log.info(f"Single-shot mode: processati {count} file. Uscita.")
        return

    # Loop
    while not _shutdown:
        try:
            poll_once(processor, store)
        except Exception as e:
            log.error(f"Errore nel loop poll: {e}", exc_info=True)

        # Sleep interruttibile
        for _ in range(Config.POLL_INTERVAL_SECONDS):
            if _shutdown:
                break
            time.sleep(1)

    log.info("Agent fermato.")


if __name__ == "__main__":
    main()
