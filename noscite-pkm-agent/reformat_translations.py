"""
reformat_translations.py — Riformatta le traduzioni pregresse nei file _IT.md
rielaborando la sezione "Testo completo tradotto" con il nuovo prompt strutturato.
Conserva invariata la sezione "Riassunto esaustivo".

Uso:
    python3 reformat_translations.py            # tutti i file _IT.md
    python3 reformat_translations.py --dry-run  # mostra cosa farebbe senza modificare
    python3 reformat_translations.py NomeDoc_IT # solo un file specifico
"""

import sys
import logging
import os
import time
from pathlib import Path

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger(__name__)

# Carica la chiave API dal plist del LaunchAgent se non già nell'ambiente
if not os.environ.get("ANTHROPIC_API_KEY"):
    plist = Path.home() / "Library/LaunchAgents/com.obsidian.agent.plist"
    if plist.exists():
        import re
        txt = plist.read_text()
        m = re.search(r"ANTHROPIC_API_KEY</key>\s*<string>([^<]+)</string>", txt)
        if m:
            os.environ["ANTHROPIC_API_KEY"] = m.group(1)
            log.info("  ✓ Chiave API caricata dal plist")

if not os.environ.get("ANTHROPIC_API_KEY"):
    log.error("ANTHROPIC_API_KEY non trovata. Esegui: export ANTHROPIC_API_KEY='sk-ant-...'")
    sys.exit(1)

import anthropic
from config import Config
from api_utils import call_with_retry
from translator import SYSTEM_PROMPT_TRANSLATE, _chunk_text

client = anthropic.Anthropic()


def reformat_all(dry_run: bool = False, only_stem: str = None):
    it_files = sorted(Config.METADATA_DIR.glob("*_IT.md"))
    if only_stem:
        it_files = [f for f in it_files if f.stem == only_stem]

    if not it_files:
        log.info("Nessun file _IT.md trovato.")
        return

    log.info(f"File _IT.md trovati: {len(it_files)}")

    total_in = total_out = 0
    ok = skipped = errors = 0

    for it_path in it_files:
        log.info(f"\n── {it_path.name}")

        try:
            result = reformat_one(it_path, dry_run)
            if result is None:
                skipped += 1
            else:
                ok += 1
                total_in  += result.get("tokens_input", 0)
                total_out += result.get("tokens_output", 0)
                # Pausa anti-rate-limit tra file
                if not dry_run:
                    time.sleep(5)
        except Exception as e:
            log.error(f"  ✗ Errore: {e}")
            errors += 1

    log.info(f"\n{'DRY RUN — ' if dry_run else ''}Completato: {ok} ok, {skipped} saltati, {errors} errori")
    log.info(f"Token totali: {total_in:,} in / {total_out:,} out")


def reformat_one(it_path: Path, dry_run: bool = False) -> dict | None:
    """
    Riformatta un singolo file _IT.md.
    Restituisce dict con token usati, o None se saltato.
    """
    text = it_path.read_text(encoding="utf-8", errors="replace")

    # Estrai le sezioni esistenti
    summary = _extract_section(text, "## 📝 Riassunto esaustivo")
    old_translation = _extract_section(text, "## 📄 Testo completo tradotto")
    frontmatter = _extract_frontmatter(text)
    header = _extract_header(text)

    if not old_translation:
        log.info("  · Sezione traduzione non trovata — salto")
        return None

    # Recupera il testo originale dal file in _archive
    original_filename = _get_original_filename(frontmatter)
    original_text = ""
    if original_filename:
        archive_path = Config.ARCHIVE_DIR / original_filename
        if archive_path.exists():
            original_text = _extract_original_text(archive_path)
            log.info(f"  ✓ Testo originale recuperato: {len(original_text):,} car.")
        else:
            log.warning(f"  ⚠ File originale non trovato: {original_filename}")

    # Se non abbiamo il testo originale, ri-traduciamo la traduzione esistente
    # (meno ideale ma funziona per ri-strutturare)
    source_text = original_text if original_text else old_translation
    source_label = "originale EN" if original_text else "traduzione esistente"
    log.info(f"  ℹ Sorgente: {source_label}")

    if dry_run:
        log.info(f"  [DRY RUN] Riformatterei {len(source_text):,} car. in {len(_chunk_text(source_text[:Config.MAX_TEXT_CHARS]))} chunk")
        return {"tokens_input": 0, "tokens_output": 0}

    # Traduzione/riformattazione a chunk
    text_to_translate = source_text[:Config.MAX_TEXT_CHARS]
    chunks = _chunk_text(text_to_translate)
    log.info(f"  📝 {len(chunks)} chunk da tradurre/riformattare...")

    translated_chunks = []
    tokens_input = tokens_output = 0

    for i, chunk in enumerate(chunks, 1):
        log.info(f"    Chunk {i}/{len(chunks)}...")
        try:
            r = call_with_retry(client.messages.create,
                model=Config.CLAUDE_MODEL,
                max_tokens=8000,
                system=SYSTEM_PROMPT_TRANSLATE,
                messages=[{"role": "user", "content": (
                    f"Traduci il seguente testo dall'inglese all'italiano "
                    f"(parte {i} di {len(chunks)}).\n"
                    f"Rispetta TUTTA la struttura markdown: titoli, elenchi, tabelle, separatori.\n\n"
                    f"TESTO:\n\n{chunk}"
                )}]
            )
            translated_chunks.append(r.content[0].text)
            tokens_input  += r.usage.input_tokens
            tokens_output += r.usage.output_tokens
            time.sleep(2)
        except Exception as e:
            log.error(f"    ⚠ Errore chunk {i}: {e}")
            translated_chunks.append(f"_[Riformattazione chunk {i} non disponibile]_\n\n{chunk}")

    new_translation = "\n\n".join(translated_chunks)

    # Ricostruisci il file _IT.md
    from datetime import datetime
    now = datetime.now().strftime("%d/%m/%Y %H:%M")

    new_content = (
        frontmatter
        + header
        + "\n\n---\n\n"
        + "## 📝 Riassunto esaustivo\n\n"
        + summary
        + "\n\n---\n\n"
        + "## 📄 Testo completo tradotto\n\n"
        + new_translation
        + f"\n\n---\n\n*Traduzione riformattata da PKM Agent — {now}*\n"
    )

    it_path.write_text(new_content, encoding="utf-8")
    log.info(f"  ✓ Salvato ({tokens_input:,} in / {tokens_output:,} out)")

    return {"tokens_input": tokens_input, "tokens_output": tokens_output}


def _extract_section(text: str, heading: str) -> str:
    """Estrae il contenuto di una sezione markdown fino alla sezione successiva o ---."""
    idx = text.find(heading)
    if idx == -1:
        return ""
    start = text.find("\n", idx) + 1
    # Cerca la prossima sezione H2 o separatore ---
    rest = text[start:]
    for marker in ["\n## ", "\n---"]:
        pos = rest.find(marker)
        if pos != -1:
            return rest[:pos].strip()
    return rest.strip()


def _extract_frontmatter(text: str) -> str:
    """Estrae il blocco frontmatter YAML inclusi i ---."""
    if not text.startswith("---"):
        return ""
    end = text.find("---", 3)
    if end == -1:
        return ""
    return text[:end + 3] + "\n"


def _extract_header(text: str) -> str:
    """Estrae il blocco tra frontmatter e prima sezione H2."""
    if text.startswith("---"):
        end = text.find("---", 3)
        if end != -1:
            rest = text[end + 3:].lstrip("\n")
            idx = rest.find("\n## ")
            if idx != -1:
                return "\n" + rest[:idx].rstrip()
    return ""


def _get_original_filename(frontmatter: str) -> str | None:
    """Estrae il nome del file originale dal frontmatter."""
    for line in frontmatter.splitlines():
        if line.startswith("file_originale:"):
            val = line.split(":", 1)[1].strip().strip('"').strip("'")
            # Rimuove wikilink [[_archive/xxx]] → xxx
            if "[[" in val:
                val = val.split("[[")[1].split("]]")[0]
            if "/" in val:
                val = val.rsplit("/", 1)[-1]
            return val if val else None
    return None


def _extract_original_text(archive_path: Path) -> str:
    """Estrae il testo dal file originale in _archive usando extractor."""
    try:
        import extractor
        result = extractor.extract(archive_path)  # Path, non str
        return result.get("text", "") or ""
    except Exception as e:
        log.warning(f"  ⚠ Errore estrazione testo: {e}")
        return ""


if __name__ == "__main__":
    args = sys.argv[1:]
    dry_run = "--dry-run" in args
    stems = [a for a in args if not a.startswith("--")]
    only_stem = stems[0] if stems else None

    if dry_run:
        log.info("=== DRY RUN — nessuna modifica verrà applicata ===")

    reformat_all(dry_run=dry_run, only_stem=only_stem)
