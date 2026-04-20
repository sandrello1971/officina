"""
Orchestratore: coordina estrazione → analisi AI → generazione md content.

In architettura SharePoint edition: NON scrive su filesystem e NON sposta file.
Il chiamante (watcher) gestisce upload/move via Graph.
"""

import logging
import re
import time
import unicodedata
from pathlib import Path

import extractor
import analyzer
import md_generator
import translator
from config import Config

log = logging.getLogger(__name__)


def _sanitize_stem(stem: str) -> str:
    """
    Rimuove o sostituisce caratteri problematici per wikilink Obsidian.
    """
    stem = unicodedata.normalize("NFKD", stem)
    stem = stem.encode("ascii", errors="ignore").decode("ascii")
    stem = re.sub(r"[#|\[\]\^]", "", stem)
    stem = re.sub(r"\s+", " ", stem).strip()
    return stem


def _notify(filename, step, total, desc):
    # Hook disabilitato in fase 2A (dashboard_server passa a Graph in 2B).
    pass


class FileProcessor:
    def process(self, path: Path) -> dict | None:
        """
        Processa un file scaricato da SharePoint (path temporaneo).
        Ritorna dict con:
        - stem: nome sanificato
        - md_content: stringa markdown da uploadare in _metadata
        - translation_stem / translation_content: opzionali
        - archive_filename: nome originale per _archive
        Ritorna None in caso di errore fatale.
        """
        filename = path.name
        safe_stem = _sanitize_stem(path.stem)
        if safe_stem != path.stem:
            log.info(f"  ⚠ Nome sanificato: '{path.stem}' → '{safe_stem}'")

        log.info(f"▶ Processing: {filename}")

        try:
            # 1. Extract
            _notify(filename, 1, 5, "Estrazione contenuto...")
            log.info("  [1/4] Estrazione contenuto...")
            extracted = extractor.extract(path)
            text = extracted.get("text", "")
            tech_meta = extracted.get("tech_meta", {})
            tech_meta["extension"] = path.suffix.lower()
            tech_meta["file_size"] = tech_meta.get("file_size", "N/D")
            if text:
                tech_meta["caratteri"] = f"{len(text):,}".replace(",", ".")

            # 2. Analyze
            _notify(filename, 2, 5, "Analisi AI con Claude...")
            log.info("  [2/4] Analisi AI con Claude...")
            meta = analyzer.analyze(filename, text, tech_meta)

            if meta.get("_tokens_total"):
                tech_meta["token_analisi"] = (
                    f"{meta['_tokens_input']:,} in / {meta['_tokens_output']:,} out"
                    .replace(",", ".")
                )

            # 3. Translation (opzionale, solo docs in inglese)
            translation_stem = None
            translation_content = None
            if meta.get("language") in ("en", "en+it"):
                _notify(filename, 3, 5, "Traduzione in italiano...")
                log.info(f"  [3b] Traduzione in italiano (testo: {len(text):,} car.)...")
                try:
                    tr = translator.translate(filename, text, meta, tech_meta,
                                              safe_stem=safe_stem, return_content=True)
                    if tr:
                        translation_stem = tr.get("translation_stem")
                        translation_content = tr.get("translation_content")
                        if translation_stem:
                            log.info(f"  ✓ Traduzione generata: {translation_stem}.md")
                except TypeError:
                    # Se translator non supporta return_content (compat legacy), skip
                    log.warning("  ⚠ translator.translate non supporta return_content, skip")
                except Exception as e:
                    log.warning(f"  ⚠ Traduzione fallita: {e}")

            # 4. Genera md content (NO write)
            _notify(filename, 3, 5, "Generazione metadati...")
            log.info("  [3/5] Generazione contenuto .md...")
            md_content = md_generator.generate(
                filename, meta, tech_meta,
                translation_stem=translation_stem,
                safe_stem=safe_stem,
                full_text=text,
            )

            # Pausa leggera per ridurre rate pressure
            pause = 60 if meta.get("language") in ("en", "en+it") else 3
            if pause > 3:
                log.info(f"  ⏱ Pausa {pause}s (rate-limit cushion)...")
            time.sleep(pause)

            # 5-7. Linker / Index / Dashboard — TODO sotto-fase 2B
            # linker.link() legge da filesystem: da riadattare via Graph.
            # index_builder.update() scrive _INDEX.md: da riadattare.
            # html_dashboard.update() scrive dashboard.html: da riadattare.
            log.info("  [4/5] Linker/Index/Dashboard: skipped (TODO 2B)")

            log.info(f"✅ Completato (processor): {filename}")

            return {
                "stem": safe_stem,
                "md_content": md_content,
                "translation_stem": translation_stem,
                "translation_content": translation_content,
                "archive_filename": filename,
            }

        except Exception as e:
            log.error(f"❌ Errore processing {filename}: {e}", exc_info=True)
            return None
