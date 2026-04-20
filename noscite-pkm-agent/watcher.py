"""
Obsidian AI Metadata Agent - Watcher
Monitora la cartella inbox e processa automaticamente i nuovi file.
"""

import time
import logging
import threading
from datetime import datetime, timedelta
from pathlib import Path
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler

from processor import FileProcessor
from config import Config
import dashboard_server


def git_push_kb():
    """Committa e pusha i nuovi file al repo GitHub, con retry e logging completo."""
    import subprocess
    import time as _time
    from datetime import datetime

    kb_path = str(Config.VAULT_ROOT)
    commit_msg = f'agent: catalogazione automatica {datetime.now().strftime("%Y-%m-%d %H:%M")}'

    def _run(cmd: str, timeout: int = 60):
        """Esegue un comando shell catturando stdout/stderr come testo."""
        print(f'[GIT] $ {cmd}')
        r = subprocess.run(
            cmd, shell=True, cwd=kb_path,
            capture_output=True, text=True, timeout=timeout
        )
        if r.stdout.strip():
            print(f'[GIT]   stdout: {r.stdout.strip()}')
        if r.stderr.strip():
            print(f'[GIT]   stderr: {r.stderr.strip()}')
        print(f'[GIT]   rc={r.returncode}')
        return r

    for attempt in range(1, 4):
        print(f'[GIT] === Tentativo {attempt}/3 in {kb_path} ===')
        try:
            _run('/usr/bin/git add -A', timeout=30)

            diff = _run('/usr/bin/git diff --cached --quiet', timeout=10)
            if diff.returncode == 0:
                print('[GIT] ✓ Niente da committare')
                return

            commit = _run(f'/usr/bin/git commit -m "{commit_msg}"', timeout=30)
            if commit.returncode != 0:
                print(f'[GIT] ⚠ Commit fallito (tentativo {attempt}/3)')
                if attempt < 3:
                    _time.sleep(2)
                continue

            # Rebase sul remote prima del push per evitare non-fast-forward
            _run('/usr/bin/git pull --rebase origin main', timeout=30)

            push = _run('/usr/bin/git push origin main', timeout=60)
            if push.returncode == 0:
                print('[GIT] ✓ Push completato')
                return
            else:
                print(f'[GIT] ⚠ Push fallito (tentativo {attempt}/3)')

        except subprocess.TimeoutExpired as e:
            print(f'[GIT] ⚠ Timeout tentativo {attempt}/3: {e.cmd}')
        except Exception as e:
            print(f'[GIT] ⚠ Eccezione tentativo {attempt}/3: {e}')

        if attempt < 3:
            _time.sleep(2)

    print('[GIT] ✗ Push fallito dopo 3 tentativi')


def kb_sync_laravel():
    """Chiama 'php artisan kb:sync' per importare i metadati nel DB Laravel."""
    import subprocess
    try:
        print('[SYNC] $ php artisan kb:sync')
        r = subprocess.run(
            '/usr/bin/php artisan kb:sync',
            shell=True, cwd='/var/www/noscite-site',
            capture_output=True, text=True, timeout=60
        )
        if r.stdout.strip():
            print(f'[SYNC]   stdout: {r.stdout.strip()}')
        if r.stderr.strip():
            print(f'[SYNC]   stderr: {r.stderr.strip()}')
        print(f'[SYNC]   rc={r.returncode}')
    except subprocess.TimeoutExpired:
        print('[SYNC] ⚠ Timeout kb:sync')
    except Exception as e:
        print(f'[SYNC] ⚠ Eccezione kb:sync: {e}')


logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.FileHandler(Config.LOG_FILE, encoding="utf-8"),
    ],
    force=True,
)
log = logging.getLogger(__name__)

COOLDOWN: dict[str, float] = {}
PROCESSING: set[str] = set()   # file attualmente in elaborazione
COOLDOWN_LOCK = threading.Lock()
COOLDOWN_SECONDS = 3
LOG_RETENTION_DAYS = 7

IGNORED_NAMES: set[str] = {
    ".ds_store", ".localized", "thumbs.db", "desktop.ini",
    "dashboard.html", "dashboard.htm", "_index.md",
}


def _trim_log():
    """Rimuove righe di log più vecchie di LOG_RETENTION_DAYS."""
    try:
        path = Config.LOG_FILE
        if not path.exists():
            return
        cutoff = datetime.now() - timedelta(days=LOG_RETENTION_DAYS)
        lines = path.read_text(encoding="utf-8", errors="replace").splitlines(keepends=True)
        kept = []
        removed = 0
        for line in lines:
            try:
                ts = datetime.strptime(line[:19], "%Y-%m-%d %H:%M:%S")
                if ts < cutoff:
                    removed += 1
                    continue
            except ValueError:
                pass
            kept.append(line)
        if removed:
            path.write_text("".join(kept), encoding="utf-8")
            log.info(f"Log: rimosse {removed} righe più vecchie di {LOG_RETENTION_DAYS} giorni.")
    except Exception as e:
        log.warning(f"Errore pulizia log: {e}")


def _log_cleanup_loop():
    """Thread che esegue la pulizia log ogni 24 ore."""
    while True:
        time.sleep(86400)
        _trim_log()


def _inbox_rescan_loop(processor):
    """
    Thread di backup: ogni 30s ri-scansiona _inbox/ per recuperare file
    che il watchdog ha perso (tipicamente quando la dir viene ricreata
    e l'observer resta legato all'inode vecchio).
    """
    while True:
        try:
            time.sleep(30)
            if not Config.INBOX_DIR.exists():
                continue

            for f in Config.INBOX_DIR.iterdir():
                if not f.is_file():
                    continue
                if f.name.lower() in IGNORED_NAMES:
                    continue
                if f.suffix.lower() in getattr(Config, 'IGNORED_EXTENSIONS', set()):
                    continue

                # Skip se esiste già il .md catalogato
                md_dest = Config.METADATA_DIR / (f.stem + ".md")
                if md_dest.exists():
                    continue

                key = str(f)
                with COOLDOWN_LOCK:
                    if key in PROCESSING:
                        continue
                    # evita double-processing se un evento è appena arrivato
                    if time.time() - COOLDOWN.get(key, 0) < COOLDOWN_SECONDS:
                        continue
                    COOLDOWN[key] = time.time()
                    PROCESSING.add(key)

                log.info(f"[RESCAN] File recuperato: {f.name}")
                try:
                    processor.process(f)
                    kb_sync_laravel()
                    git_push_kb()
                except Exception as e:
                    log.error(f"[RESCAN] Errore processing {f.name}: {e}")
                finally:
                    with COOLDOWN_LOCK:
                        PROCESSING.discard(key)
        except Exception as e:
            log.warning(f"[RESCAN] Errore loop: {e}")


class InboxHandler(FileSystemEventHandler):
    def __init__(self):
        self.processor = FileProcessor()

    def on_created(self, event):
        if event.is_directory:
            return
        path = Path(event.src_path)
        if path.name.lower() in IGNORED_NAMES:
            return
        if path.suffix.lower() in getattr(Config, 'IGNORED_EXTENSIONS', set()):
            return
        key = str(path)
        with COOLDOWN_LOCK:
            now = time.time()
            last = COOLDOWN.get(key, 0)
            if now - last < COOLDOWN_SECONDS:
                return
            if key in PROCESSING:
                return
            COOLDOWN[key] = now
            PROCESSING.add(key)
        log.info(f"[O] Nuovo file rilevato: {path.name}")
        try:
            time.sleep(2)  # attesa per file completamente scritto
            self.processor.process(path)
            kb_sync_laravel()
            git_push_kb()
        finally:
            with COOLDOWN_LOCK:
                PROCESSING.discard(key)


class MetadataHandler(FileSystemEventHandler):
    """Monitora _metadata/ per cancellazioni di .md e rigenera la dashboard."""

    def on_deleted(self, event):
        if event.is_directory:
            return
        path = Path(event.src_path)
        if path.suffix.lower() != ".md":
            return
        if path.stem.lower() in ("_index", "dashboard"):
            return
        log.info(f"  🗑 Metadato eliminato: {path.name} — rigenero dashboard...")
        try:
            import html_dashboard
            import dashboard_server as ds
            html_dashboard.update()
            ds.notify_complete()
        except Exception as e:
            log.warning(f"  ⚠ Errore regen dashboard: {e}")


def main():
    # Crea le cartelle del vault se non esistono (con retry per permessi iCloud)
    for d in (Config.INBOX_DIR, Config.METADATA_DIR, Config.ARCHIVE_DIR):
        for attempt in range(10):
            try:
                d.mkdir(parents=True, exist_ok=True)
                break
            except PermissionError:
                if attempt < 9:
                    time.sleep(3)
                else:
                    log.error(f"  ✗ Impossibile creare directory {d} dopo 10 tentativi")

    log.info("=== Obsidian AI Metadata Agent avviato ===")
    log.info(f"Monitorando: {Config.INBOX_DIR}")
    log.info(f"Metadati salvati in: {Config.METADATA_DIR}")

    # Pulizia log all'avvio
    _trim_log()

    # Thread pulizia log giornaliera
    t = threading.Thread(target=_log_cleanup_loop, daemon=True)
    t.start()

    # Thread rescan inbox ogni 30s (backup per observer perso)
    rescan_processor = FileProcessor()
    t_rescan = threading.Thread(target=_inbox_rescan_loop, args=(rescan_processor,), daemon=True)
    t_rescan.start()

    # Avvia il server dashboard
    dashboard_server.start()

    # Processa file già presenti nella inbox all'avvio (con retry per permessi iCloud)
    processor = FileProcessor()
    existing = []
    for attempt in range(10):
        try:
            existing = [
                f for f in Config.INBOX_DIR.iterdir()
                if f.is_file()
                and f.name.lower() not in IGNORED_NAMES
                and f.suffix.lower() not in getattr(Config, 'IGNORED_EXTENSIONS', set())
            ]
            break
        except PermissionError:
            if attempt < 9:
                log.warning(f"  ⚠ Accesso _inbox negato (iCloud non pronto?), retry in 3s (tentativo {attempt + 1}/10)...")
                time.sleep(3)
            else:
                log.error("  ✗ Impossibile accedere a _inbox dopo 10 tentativi — skip scansione iniziale")
    if existing:
        log.info(f"Trovati {len(existing)} file esistenti da processare...")
        for f in existing:
            processor.process(f)
            kb_sync_laravel()
            git_push_kb()

    observer = Observer()
    observer.schedule(InboxHandler(), str(Config.INBOX_DIR), recursive=False)
    observer.schedule(MetadataHandler(), str(Config.METADATA_DIR), recursive=False)
    observer.start()
    log.info("Watcher in ascolto. Premi Ctrl+C per uscire.\n")

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        observer.stop()
        log.info("Agent fermato.")
    observer.join()


if __name__ == "__main__":
    main()
