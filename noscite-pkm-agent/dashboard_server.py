"""
Micro server Flask — serve dashboard.html e fa da bridge
verso Local REST API di Obsidian per aprire i file al click.
Porta configurata via PKM_PORT (default: 5050).
"""

import logging
import os
import threading
import time
from pathlib import Path

from config import Config

log = logging.getLogger(__name__)

# ── Filtro log: max 1 riga ogni 30s per /status e /activity ─────────────────
class _StatusThrottle(logging.Filter):
    def __init__(self):
        super().__init__()
        self._last: dict[str, float] = {}

    def filter(self, record):
        msg = record.getMessage()
        for endpoint in ("/status", "/activity"):
            if endpoint in msg:
                now = time.time()
                if now - self._last.get(endpoint, 0) < 30:
                    return False
                self._last[endpoint] = now
                break
        return True

logging.getLogger("werkzeug").addFilter(_StatusThrottle())

OBSIDIAN_API     = "http://127.0.0.1:27123"
OBSIDIAN_API_KEY = os.environ.get("OBSIDIAN_API_KEY", "")
PORT             = int(os.environ.get("PKM_PORT", 5050))

# Contatore operazioni completate — incrementato dopo ogni processing
_op_count = 0
_reprocess_status: dict = {}  # stem -> "running"|"ok"|"error:msg"|"overload"

# Attività corrente — aggiornata dal processor durante il processing
_current_activity: dict = {}  # {filename, step, total_steps, description}

# Contatore reprocess attivi — blocca notify_complete() spuri dal watcher
_reprocessing_count = 0

def notify_complete(from_reprocess: bool = False):
    """Chiamato da processor/reprocess al termine di ogni operazione."""
    global _op_count, _current_activity, _reprocessing_count
    if not from_reprocess and _reprocessing_count > 0:
        return  # ignora eventi dal watcher mentre un reprocess è in corso
    _op_count += 1
    _current_activity = {}  # reset attività

def notify_activity(filename: str, step: int, total_steps: int, description: str):
    """Aggiorna l'attività corrente visibile nella dashboard."""
    global _current_activity
    _current_activity = {
        "filename": filename,
        "step": step,
        "total_steps": total_steps,
        "description": description,
    }


_server_started = False

def start():
    """Avvia il server Flask in un thread daemon."""
    global _server_started
    if _server_started:
        return
    _server_started = True
    t = threading.Thread(target=_run, daemon=True)
    t.start()
    log.info(f"  ✓ Dashboard server avviato su http://127.0.0.1:{PORT}")


def _run():
    try:
        from flask import Flask, send_file, jsonify, request
        from flask_cors import CORS
        import requests as req

        app = Flask(__name__)
        CORS(app)
        app.config["MAX_CONTENT_LENGTH"] = 500 * 1024 * 1024  # 500 MB

        @app.route("/")
        def index():
            try:
                from flask import make_response
                import html_dashboard
                docs = html_dashboard._load_all_metadata()
                html_content = html_dashboard._render_html(docs)
                html_content = html_content.replace(
                    "http://127.0.0.1:5050", f"http://127.0.0.1:{PORT}"
                )
                resp = make_response(html_content)
                resp.headers["Content-Type"] = "text/html; charset=utf-8"
                resp.headers["Cache-Control"] = "no-store, no-cache, must-revalidate"
                resp.headers["Pragma"] = "no-cache"
                return resp
            except Exception as e:
                log.warning(f"Errore rigenerazione dashboard: {e}")
            html = Config.METADATA_DIR / "dashboard.html"
            if html.exists():
                resp = send_file(str(html))
                resp.headers["Cache-Control"] = "no-store, no-cache, must-revalidate"
                resp.headers["Pragma"] = "no-cache"
                return resp
            return "<h2>Dashboard non ancora generata. Aggiungi un file in _inbox.</h2>", 404

        @app.route("/status")
        def status():
            from flask import make_response
            resp = make_response(jsonify({"op": _op_count}))
            resp.headers["Cache-Control"] = "no-store"
            return resp

        @app.route("/activity")
        def activity():
            from flask import make_response
            resp = make_response(jsonify(_current_activity))
            resp.headers["Cache-Control"] = "no-store"
            return resp

        @app.route("/reprocess_all")
        def reprocess_all_endpoint():
            try:
                import threading
                import sys
                sys.path.insert(0, str(Path(__file__).parent))

                def _run_reprocess_all():
                    import importlib
                    import reprocess_all as rpa
                    importlib.reload(rpa)
                    rpa.reprocess_all(skip_links=False)

                threading.Thread(target=_run_reprocess_all, daemon=True).start()
                return jsonify({"ok": True, "message": "Riprocessamento avviato per tutti i documenti. Controlla i log per il progresso."})
            except Exception as e:
                log.error(f"Errore reprocess_all: {e}")
                return jsonify({"error": str(e)}), 500

        @app.route("/refresh")
        def refresh():
            try:
                import html_dashboard
                html_dashboard.update()
                from flask import redirect
                return redirect(f"http://127.0.0.1:{PORT}?t={int(time.time())}")
            except Exception as e:
                log.error(f"Errore refresh: {e}")
                return jsonify({"error": str(e)}), 500

        @app.route("/regen_index")
        def regen_index():
            try:
                import index_builder
                index_builder.regen()
                return jsonify({"ok": True, "message": "_INDEX.md rigenerato."})
            except Exception as e:
                log.error(f"Errore regen_index: {e}")
                return jsonify({"error": str(e)}), 500

        @app.route("/reprocess_status")
        def reprocess_status_ep():
            from flask import make_response
            s = request.args.get("stem", "")
            status = _reprocess_status.get(s, "unknown")
            resp = make_response(jsonify({"stem": s, "status": status}))
            resp.headers["Cache-Control"] = "no-store"
            return resp

        @app.route("/reprocess")
        def reprocess():
            stem = request.args.get("stem", "")
            if not stem:
                return jsonify({"error": "missing stem"}), 400
            try:
                import threading
                import sys
                sys.path.insert(0, str(Path(__file__).parent))
                _reprocess_status[stem] = "running"

                def _run():
                    import extractor
                    import analyzer
                    import md_generator
                    import linker
                    import index_builder
                    import html_dashboard
                    import translator
                    import dashboard_server as ds
                    from config import Config
                    from api_utils import OverloadedError

                    def _notify(step, total, desc):
                        try:
                            ds.notify_activity(filename, step, total, desc)
                        except Exception:
                            pass

                    ds._reprocessing_count += 1
                    try:
                        matches = list(Config.ARCHIVE_DIR.glob(stem + ".*"))
                        if not matches:
                            _reprocess_status[stem] = "error:file non trovato in archivio"
                            return
                        path = matches[0]
                        filename = path.name
                        md_dest = Config.METADATA_DIR / (stem + ".md")
                        if md_dest.exists():
                            md_dest.unlink()

                        _notify(1, 5, "Estrazione contenuto...")
                        extracted = extractor.extract(path)
                        text = extracted.get("text", "")
                        tech_meta = extracted.get("tech_meta", {})
                        tech_meta["extension"] = path.suffix.lower()
                        tech_meta["file_size"] = tech_meta.get("file_size", "N/D")
                        if text:
                            tech_meta["caratteri"] = f"{len(text):,}".replace(",", ".")

                        _notify(2, 5, "Analisi AI con Claude...")
                        meta = analyzer.analyze(filename, text, tech_meta)
                        if meta.get("_tokens_total"):
                            tech_meta["token_analisi"] = (
                                f"{meta['_tokens_input']:,} in / {meta['_tokens_output']:,} out"
                                .replace(",", ".")
                            )

                        translation_stem = None
                        if meta.get("language") in ("en", "en+it"):
                            _notify(3, 5, "Traduzione in italiano...")
                            try:
                                tr = translator.translate(filename, text, meta, tech_meta)
                                if tr:
                                    translation_stem = tr["translation_stem"]
                            except OverloadedError:
                                log.warning("  ⚠ Traduzione saltata (overload server)")
                                _reprocess_status[stem] = "overload"
                            except Exception as e:
                                log.warning(f"  ⚠ Traduzione fallita: {e}")

                        _notify(3, 5, "Generazione file metadati...")
                        md_content = md_generator.generate(filename, meta, tech_meta, translation_stem=translation_stem)
                        md_dest.write_text(md_content, encoding="utf-8")

                        _notify(4, 5, "Ricerca documenti correlati...")
                        try:
                            linker_tokens = linker.link(stem, meta)
                            if linker_tokens:
                                token_str = (
                                    f"{linker_tokens['_linker_tokens_input']:,} in / {linker_tokens['_linker_tokens_output']:,} out"
                                    .replace(",", ".")
                                )
                                current = md_dest.read_text(encoding="utf-8")
                                if "token_linker:" not in current:
                                    current = current.replace(
                                        "token_analisi:",
                                        f'token_linker: "{token_str}"\ntoken_analisi:'
                                    )
                                    md_dest.write_text(current, encoding="utf-8")
                        except OverloadedError:
                            log.warning("  ⚠ Linker saltato (overload server)")

                        _notify(5, 5, "Aggiornamento indice e dashboard...")
                        index_builder.update(filename, meta, tech_meta)
                        html_dashboard.update()
                        ds.notify_complete(from_reprocess=True)
                        log.info(f"Reprocess completato: {filename}")
                        if _reprocess_status.get(stem) != "overload":
                            _reprocess_status[stem] = "ok"

                    except OverloadedError as e:
                        _reprocess_status[stem] = "overload"
                        log.error(f"Reprocess interrotto (overload): {e}")
                    except Exception as e:
                        _reprocess_status[stem] = f"error:{e}"
                        log.error(f"Errore reprocess {stem}: {e}", exc_info=True)
                    finally:
                        ds._reprocessing_count = max(0, ds._reprocessing_count - 1)

                threading.Thread(target=_run, daemon=True).start()
                return jsonify({"ok": True})
            except Exception as e:
                log.error(f"Errore reprocess: {e}")
                return jsonify({"error": str(e)}), 500

        @app.route("/analyze_selection", methods=["POST"])
        def analyze_selection():
            try:
                import threading
                data = request.get_json()
                stems = data.get("stems", [])
                custom_prompt = data.get("custom_prompt", "")
                focus_items = data.get("focus_items", [])
                if not stems:
                    return jsonify({"error": "nessun documento selezionato"}), 400

                def _run_analysis():
                    import report_generator
                    import dashboard_server as ds
                    from urllib.parse import quote
                    import subprocess
                    n = len(stems)
                    ds.notify_activity(f"Analisi {n} documenti", 1, 3, "Lettura documenti selezionati...")
                    result = report_generator.generate(stems, custom_prompt=custom_prompt, focus_items=focus_items)
                    if result:
                        ds.notify_activity(f"Analisi {n} documenti", 3, 3, "Salvataggio report...")
                        report_stem = result["report_stem"]
                        import html_dashboard
                        html_dashboard.update()
                        ds.notify_complete()
                        file_path = f"_metadata/{report_stem}.md"
                        vault_name = Path(os.environ.get("OBSIDIAN_VAULT", "")).name
                        try:
                            req.post(
                                f"{OBSIDIAN_API}/open/{file_path}?newLeaf=true&mode=preview",
                                headers={"Authorization": f"Bearer {OBSIDIAN_API_KEY}"},
                                timeout=2,
                            )
                        except Exception:
                            pass
                        try:
                            uri = f"obsidian://open?vault={quote(vault_name)}&file={quote(file_path)}"
                            subprocess.Popen(["open", uri])
                        except Exception:
                            pass
                        log.info(f"Report analisi completato: {report_stem}.md")

                threading.Thread(target=_run_analysis, daemon=True).start()
                return jsonify({"ok": True})
            except Exception as e:
                log.error(f"Errore analyze_selection: {e}")
                return jsonify({"error": str(e)}), 500

        @app.route("/upload", methods=["POST"])
        def upload_file():
            try:
                if "file" not in request.files:
                    return jsonify({"error": "nessun file ricevuto"}), 400
                f = request.files["file"]
                if not f.filename:
                    return jsonify({"error": "nome file vuoto"}), 400

                original_name = f.filename
                exists_inbox   = (Config.INBOX_DIR   / original_name).exists()
                exists_archive = (Config.ARCHIVE_DIR / original_name).exists()

                if exists_archive:
                    # File già catalogato e archiviato → salva in _remove
                    Config.REMOVE_DIR.mkdir(parents=True, exist_ok=True)
                    dest = Config.REMOVE_DIR / original_name
                    i = 1
                    while dest.exists():
                        dest = Config.REMOVE_DIR / f"{Path(original_name).stem}_{i}{Path(original_name).suffix}"
                        i += 1
                    f.save(str(dest))
                    log.info(f"  ⚠ Duplicato spostato in _remove/: {dest.name}")
                    return jsonify({"ok": True, "filename": dest.name,
                                    "duplicate": True, "duplicate_where": "archivio"})

                if exists_inbox:
                    # File già in inbox → salva con nome rinominato
                    dest = Config.INBOX_DIR / original_name
                    i = 1
                    while dest.exists():
                        dest = Config.INBOX_DIR / f"{Path(original_name).stem}_{i}{Path(original_name).suffix}"
                        i += 1
                    f.save(str(dest))
                    log.info(f"  ⚠ Duplicato in inbox, rinominato: {dest.name}")
                    return jsonify({"ok": True, "filename": dest.name,
                                    "duplicate": True, "duplicate_where": "inbox"})

                # File nuovo → carica normalmente in _inbox
                dest = Config.INBOX_DIR / original_name
                f.save(str(dest))
                log.info(f"  ✓ File caricato in _inbox/: {dest.name}")
                return jsonify({"ok": True, "filename": dest.name, "duplicate": False})

            except Exception as e:
                log.error(f"Errore upload: {e}")
                return jsonify({"error": str(e)}), 500

        @app.route("/open")
        def open_file():
            path = request.args.get("file", "")
            new_leaf = request.args.get("newLeaf", "false")
            if not path:
                return jsonify({"error": "missing file param"}), 400

            # Prova prima con la REST API di Obsidian (apre in nuovo leaf senza perdere focus)
            try:
                url = f"{OBSIDIAN_API}/open/{path}"
                params = ["mode=preview"]
                if new_leaf == "true":
                    params.append("newLeaf=true")
                resp = req.post(
                    url + "?" + "&".join(params),
                    headers={"Authorization": f"Bearer {OBSIDIAN_API_KEY}"},
                    timeout=2,
                )
                if resp.status_code < 400:
                    return jsonify({"ok": True, "method": "rest"})
            except Exception:
                pass

            # Fallback: obsidian:// URI (funziona sempre ma porta Obsidian in primo piano)
            try:
                import subprocess
                from urllib.parse import quote
                vault_name = Path(os.environ.get("OBSIDIAN_VAULT", "")).name
                uri = f"obsidian://open?vault={quote(vault_name)}&file={quote(path)}"
                subprocess.Popen(["open", uri])
                return jsonify({"ok": True, "method": "uri"})
            except Exception as e:
                log.warning(f"Errore apertura file Obsidian: {e}")
                return jsonify({"error": str(e)}), 500

        @app.route("/download_zip", methods=["POST"])
        def download_zip():
            import io, zipfile
            data = request.get_json()
            stems = data.get("stems", [])
            if not stems:
                return jsonify({"error": "nessun documento selezionato"}), 400
            buf = io.BytesIO()
            with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as zf:
                for stem in stems:
                    for f in Config.ARCHIVE_DIR.glob(stem + ".*"):
                        zf.write(f, f.name)
            buf.seek(0)
            fname = stems[0] + ".zip" if len(stems) == 1 else "documenti.zip"
            return send_file(buf, mimetype="application/zip", as_attachment=True, download_name=fname)

        @app.route("/open_files", methods=["POST"])
        def open_files():
            import subprocess
            data = request.get_json()
            stems = data.get("stems", [])
            opened = []
            for stem in stems:
                for f in Config.ARCHIVE_DIR.glob(stem + ".*"):
                    subprocess.Popen(["open", str(f)])
                    opened.append(f.name)
            return jsonify({"ok": True, "opened": opened})

        @app.route("/share_files", methods=["POST"])
        def share_files():
            import subprocess
            data = request.get_json()
            stems = data.get("stems", [])
            files = []
            for stem in stems:
                files.extend(Config.ARCHIVE_DIR.glob(stem + ".*"))
            if not files:
                return jsonify({"error": "nessun file trovato"}), 404
            paths_js = ", ".join(f'"{f}"' for f in files)
            jxa = f"""
ObjC.import('AppKit');
ObjC.import('Foundation');
var items = $.NSMutableArray.alloc.init;
[{paths_js}].forEach(function(p) {{ items.addObject($.NSURL.fileURLWithPath(p)); }});
var app = $.NSApplication.sharedApplication;
app.setActivationPolicy(1);
app.activateIgnoringOtherApps(true);
var win = $.NSWindow.alloc.initWithContentRectStyleMaskBackingDefer(
    {{origin:{{x:400,y:400}},size:{{width:220,height:60}}}}, 3, 2, false);
win.setAlphaValue(0);
win.makeKeyAndOrderFront(null);
var picker = $.NSSharingServicePicker.alloc.initWithItems(items);
picker.showRelativeToRectOfViewPreferredEdge(
    {{origin:{{x:0,y:0}},size:{{width:220,height:60}}}}, win.contentView, 1);
$.NSRunLoop.mainRunLoop.runUntilDate($.NSDate.dateWithTimeIntervalSinceNow(300));
"""
            subprocess.Popen(["osascript", "-l", "JavaScript", "-e", jxa])
            return jsonify({"ok": True})

        @app.route("/compose_mail", methods=["POST"])
        def compose_mail():
            import subprocess, json as _json
            data = request.get_json()
            stems = data.get("stems", [])
            files = []
            for stem in stems:
                files.extend(Config.ARCHIVE_DIR.glob(stem + ".*"))
            if not files:
                return jsonify({"error": "nessun file trovato"}), 404
            # Legge il bundle ID dell'app default per mailto: dal plist LaunchServices
            mail_bundle = "com.apple.mail"
            try:
                plist = os.path.expanduser(
                    "~/Library/Preferences/com.apple.LaunchServices/com.apple.launchservices.secure.plist"
                )
                raw = subprocess.check_output(
                    ["plutil", "-convert", "json", "-o", "-", plist], text=True, timeout=5
                )
                for h in _json.loads(raw).get("LSHandlers", []):
                    if h.get("LSHandlerURLScheme") == "mailto":
                        mail_bundle = h.get("LSHandlerRoleAll", mail_bundle)
                        break
            except Exception:
                pass
            subprocess.Popen(["open", "-b", mail_bundle] + [str(f) for f in files])
            return jsonify({"ok": True, "bundle": mail_bundle, "files": [f.name for f in files]})

        @app.route("/share_whatsapp", methods=["POST"])
        def share_whatsapp():
            import subprocess
            data = request.get_json()
            stems = data.get("stems", [])
            files = []
            for stem in stems:
                files.extend(Config.ARCHIVE_DIR.glob(stem + ".*"))
            if not files:
                return jsonify({"error": "nessun file trovato"}), 404
            # Copia i file negli appunti tramite Finder, poi apre WhatsApp
            if len(files) == 1:
                clip = f'tell application "Finder" to set the clipboard to (POSIX file "{files[0]}")'
            else:
                refs = ", ".join(f'POSIX file "{f}"' for f in files)
                clip = f'tell application "Finder" to set the clipboard to {{{refs}}}'
            try:
                subprocess.run(["osascript", "-e", clip], timeout=5)
            except Exception:
                pass
            subprocess.Popen(["open", "-b", "net.whatsapp.WhatsApp"])
            n = len(files)
            toast = f"{'File copiato' if n == 1 else str(n) + ' file copiati'} negli appunti — apri una conversazione in WhatsApp e premi ⌘V"
            return jsonify({"ok": True, "toast": toast, "files": [f.name for f in files]})

        for _attempt in range(10):
            try:
                app.run(host="127.0.0.1", port=PORT, debug=False, use_reloader=False, threaded=True)
                break
            except OSError as e:
                if "Address already in use" in str(e) and _attempt < 9:
                    log.warning(f"  ⚠ Porta {PORT} occupata, retry in 3s (tentativo {_attempt + 1}/10)...")
                    time.sleep(3)
                else:
                    log.error(f"  ✗ Impossibile avviare dashboard su porta {PORT}: {e}")
                    break

    except ImportError as e:
        log.warning(f"Flask non installato, dashboard server non disponibile: {e}")
        log.warning("Installa con: pip install flask flask-cors requests")
