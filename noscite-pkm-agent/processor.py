"""
Orchestratore: coordina estrazione → analisi AI → salvataggio .md → indice.
"""

import logging
import re
import shutil
import unicodedata
from pathlib import Path

import extractor
import analyzer
import md_generator
import index_builder
import html_dashboard
import linker
import translator
import dashboard_server
from config import Config

log = logging.getLogger(__name__)


def _sanitize_stem(stem: str) -> str:
    """
    Rimuove o sostituisce i caratteri che causano problemi nei wikilink di Obsidian:
      - # → causa interpretazione come heading anchor
      - | → causa interpretazione come alias
      - [ ] ^ → sintassi wikilink/block reference
      - Lettere accentate → normalizzate in ASCII per evitare mismatch filesystem
    """
    # Normalizza lettere accentate in ASCII (é→e, à→a, ecc.)
    stem = unicodedata.normalize("NFKD", stem)
    stem = stem.encode("ascii", errors="ignore").decode("ascii")
    # Rimuovi caratteri problematici per Obsidian
    stem = re.sub(r"[#|\[\]\^]", "", stem)
    # Comprimi spazi multipli e rimuovi spazi iniziali/finali
    stem = re.sub(r"\s+", " ", stem).strip()
    return stem


def _notify(filename, step, total, desc):
    try:
        dashboard_server.notify_activity(filename, step, total, desc)
    except Exception:
        pass


class FileProcessor:
    def process(self, path: Path):
        filename = path.name
        safe_stem = _sanitize_stem(path.stem)
        if safe_stem != path.stem:
            log.info(f"  ⚠ Nome sanificato: '{path.stem}' → '{safe_stem}'")
        md_dest = Config.METADATA_DIR / (safe_stem + ".md")

        # Salta se già catalogato
        if md_dest.exists():
            log.info(f"Già catalogato, skippato: {filename}")
            return

        log.info(f"▶ Processing: {filename}")

        try:
            # 1. Estrai testo e metadati tecnici
            _notify(filename, 1, 5, "Estrazione contenuto...")
            log.info("  [1/4] Estrazione contenuto...")
            extracted = extractor.extract(path)
            text = extracted.get("text", "")
            tech_meta = extracted.get("tech_meta", {})
            tech_meta["extension"] = path.suffix.lower()
            tech_meta["file_size"] = tech_meta.get("file_size", "N/D")
            if text:
                tech_meta["caratteri"] = f"{len(text):,}".replace(",", ".")

            # 2. Analisi AI
            _notify(filename, 2, 5, "Analisi AI con Claude...")
            log.info("  [2/4] Analisi AI con Claude...")
            meta = analyzer.analyze(filename, text, tech_meta)

            # Aggiungi token analisi ai metadati tecnici
            if meta.get("_tokens_total"):
                tech_meta["token_analisi"] = (
                    f"{meta['_tokens_input']:,} in / {meta['_tokens_output']:,} out"
                    .replace(",", ".")
                )

            # 3. Sposta file originale in archive
            archive_dest = Config.ARCHIVE_DIR / filename
            if not archive_dest.exists():
                try:
                    shutil.move(str(path), str(archive_dest))
                    log.info(f"  ✓ Archiviato: {filename} → _archive/")
                except FileNotFoundError:
                    log.warning(f"  ⚠ File non trovato durante archiviazione (già spostato?): {filename}")
                except Exception as e:
                    log.warning(f"  ⚠ Errore archiviazione {filename}: {e}")
            else:
                log.info(f"  · {filename} già presente in _archive/, skip archiviazione")

            # 4. Traduzione (solo documenti in inglese)
            translation_stem = None
            if meta.get("language") in ("en", "en+it"):
                _notify(filename, 3, 5, "Traduzione in italiano...")
                log.info(f"  [3b] Traduzione in italiano (testo: {len(text):,} car.)...")
                try:
                    tr = translator.translate(filename, text, meta, tech_meta, safe_stem=safe_stem)
                    if tr:
                        translation_stem = tr["translation_stem"]
                        log.info(f"  ✓ Traduzione salvata: {translation_stem}.md")
                except Exception as e:
                    log.warning(f"  ⚠ Traduzione fallita: {e}")

            # 5. Genera e salva file .md base
            _notify(filename, 3, 5, "Generazione file metadati...")
            log.info("  [3/5] Generazione file metadati...")
            md_content = md_generator.generate(filename, meta, tech_meta, translation_stem=translation_stem, safe_stem=safe_stem, full_text=text)
            md_dest.write_text(md_content, encoding="utf-8")
            log.info(f"  ✓ Salvato: {md_dest.name}")

            # Pausa per evitare rate limit tra analisi/traduzione e linker
            import time
            pause = 60 if meta.get("language") in ("en", "en+it") else 3
            if pause > 3:
                log.info(f"  ⏱ Pausa {pause}s (documento in inglese tradotto)...")
            time.sleep(pause)

            # 5. Collega documenti semanticamente affini
            _notify(filename, 4, 5, "Ricerca documenti correlati...")
            log.info("  [4/5] Ricerca documenti correlati...")
            linker_tokens = linker.link(safe_stem, meta)
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

            # 6. Aggiorna indice globale
            _notify(filename, 5, 5, "Aggiornamento indice e dashboard...")
            log.info("  [5/5] Aggiornamento indice...")
            index_builder.update(filename, meta, tech_meta)

            # 7. Rigenera dashboard HTML
            html_dashboard.update()
            dashboard_server.notify_complete()

            log.info(f"✅ Completato: {filename}\n")

        except Exception as e:
            log.error(f"❌ Errore processing {filename}: {e}", exc_info=True)
