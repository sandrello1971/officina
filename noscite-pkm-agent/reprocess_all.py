"""
reprocess_all.py — Ricataloga tutti i documenti in _archive/
Elimina i .md esistenti e rigenera metadati, link e dashboard.

Uso:
    python reprocess_all.py              # ricataloga tutto
    python reprocess_all.py --skip-links # salta il linker (più veloce)
    python reprocess_all.py --only EI289 # ricataloga solo un file specifico (stem)
"""

import sys
import shutil
import logging
import argparse

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger(__name__)

from config import Config
import extractor
import analyzer
import md_generator
import linker
import translator
import index_builder
import html_dashboard


IGNORED_NAMES = {
    ".ds_store", ".localized", "thumbs.db", "desktop.ini",
    ".gitkeep", ".gitignore"
}


def reprocess_all(skip_links: bool = False, only: str = None):
    Config.ensure_dirs()

    # Raccogli file da _archive/
    all_files = [
        f for f in Config.ARCHIVE_DIR.iterdir()
        if f.is_file()
        and f.name.lower() not in IGNORED_NAMES
        and f.suffix.lower() not in Config.IGNORED_EXTENSIONS
    ]

    if only:
        all_files = [f for f in all_files if f.stem == only]
        if not all_files:
            log.error(f"Nessun file trovato con stem '{only}' in _archive/")
            return

    if not all_files:
        log.warning("Nessun file trovato in _archive/")
        return

    log.info(f"=== Reprocess All — {len(all_files)} documenti ===")
    if skip_links:
        log.info("Modalità: senza linker semantico")
    log.info("")

    ok = 0
    errors = 0

    for i, path in enumerate(sorted(all_files), 1):
        filename = path.name
        md_dest  = Config.METADATA_DIR / (path.stem + ".md")

        log.info(f"[{i}/{len(all_files)}] {filename}")

        try:
            # 1. Elimina il vecchio .md se esiste
            if md_dest.exists():
                md_dest.unlink()

            # 2. Copia temporaneamente in _inbox/ per il processing
            inbox_copy = Config.INBOX_DIR / filename
            shutil.copy2(str(path), str(inbox_copy))

            # 3. Estrazione
            extracted = extractor.extract(inbox_copy)
            text      = extracted.get("text", "")
            tech_meta = extracted.get("tech_meta", {})
            tech_meta["extension"] = path.suffix.lower()
            tech_meta["file_size"] = tech_meta.get("file_size", "N/D")
            if text:
                tech_meta["caratteri"] = f"{len(text):,}".replace(",", ".")

            # 4. Analisi AI
            meta = analyzer.analyze(filename, text, tech_meta)
            if meta.get("_tokens_total"):
                tech_meta["token_analisi"] = (
                    f"{meta['_tokens_input']:,} in / {meta['_tokens_output']:,} out"
                    .replace(",", ".")
                )

            # 5. Rimuovi la copia temporanea in _inbox/
            inbox_copy.unlink()

            # 6. Traduzione (solo documenti in inglese)
            translation_stem = None
            if meta.get("language") in ("en", "en+it"):
                log.info("  Traduzione in italiano...")
                try:
                    tr = translator.translate(filename, text, meta, tech_meta)
                    if tr:
                        translation_stem = tr["translation_stem"]
                        log.info(f"  ✓ Traduzione: {translation_stem}.md")
                except Exception as e:
                    log.warning(f"  ⚠ Traduzione fallita: {e}")

            # 7. Genera .md base
            md_content = md_generator.generate(filename, meta, tech_meta, translation_stem=translation_stem)
            md_dest.write_text(md_content, encoding="utf-8")

            # 7. Linker semantico (opzionale) — aggiorna il .md appena scritto
            if not skip_links:
                linker_tokens = linker.link(path.stem, meta)
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

            # 8. Aggiorna indice
            index_builder.update(filename, meta, tech_meta)

            log.info("  ✅ Completato\n")
            ok += 1

        except Exception as e:
            log.error(f"  ❌ Errore: {e}\n")
            errors += 1
            # Pulizia in caso di errore
            inbox_copy = Config.INBOX_DIR / filename
            if inbox_copy.exists():
                inbox_copy.unlink()

    # Rigenera dashboard
    log.info("Rigenerazione dashboard HTML...")
    html_dashboard.update()

    log.info(f"\n=== Completato: {ok} OK, {errors} errori ===")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Ricataloga tutti i documenti in _archive/")
    parser.add_argument("--skip-links", action="store_true", help="Salta il linker semantico")
    parser.add_argument("--only", type=str, default=None, help="Ricataloga solo il file con questo stem (es: EI289)")
    args = parser.parse_args()

    reprocess_all(skip_links=args.skip_links, only=args.only)
