"""
dedup_links.py — Rimuove link duplicati dalla sezione "Documenti Correlati"
in tutti i file .md di _metadata/. Mantiene solo la prima occorrenza
di ogni stem, preferendo quella con score più alto.

Uso:
    python dedup_links.py           # rimuove duplicati
    python dedup_links.py --dry-run # mostra cosa verrebbe rimosso
"""

import re
import sys
import argparse
import logging

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger(__name__)

from config import Config

MARKER_START  = "<!-- RELATED_START -->"
MARKER_END    = "<!-- RELATED_END -->"
SCORE_PATTERN = re.compile(r"\|\s*(\d+)/10\s*\|?\s*$")
STEM_PATTERN  = re.compile(r"\[\[([^\]|\\]+)")


def dedup(dry_run: bool = False):
    md_files = sorted(Config.METADATA_DIR.glob("*.md"))
    md_files = [f for f in md_files if f.stem not in ("_INDEX", "DASHBOARD")]

    total_removed = 0
    total_files   = 0

    for md_path in md_files:
        content = md_path.read_text(encoding="utf-8")

        if MARKER_START not in content:
            continue

        start = content.find(MARKER_START)
        end   = content.find(MARKER_END)
        if start == -1 or end == -1:
            continue

        section = content[start + len(MARKER_START):end]
        lines   = section.splitlines()

        # Separa righe strutturali (intestazione, separatori, vuote) dalle righe dati
        structural = []
        data_lines = []
        for line in lines:
            stripped = line.strip()
            if not stripped or stripped.startswith("|---") or stripped.startswith("| Documento"):
                structural.append(("structural", line))
            elif stripped.startswith("|"):
                # Estrai stem e score
                stem_match  = STEM_PATTERN.search(stripped)
                score_match = SCORE_PATTERN.search(stripped)
                stem  = stem_match.group(1).strip() if stem_match else None
                score = int(score_match.group(1)) if score_match else 0
                data_lines.append({"stem": stem, "score": score, "line": line})
            else:
                structural.append(("structural", line))

        # Deduplicazione: per ogni stem mantieni solo quello con score più alto
        seen   = {}
        kept   = []
        removed = []
        for item in data_lines:
            stem = item["stem"]
            if not stem:
                kept.append(item)
                continue
            if stem not in seen:
                seen[stem] = item
                kept.append(item)
            else:
                # Mantieni quello con score più alto
                if item["score"] > seen[stem]["score"]:
                    removed.append(seen[stem])
                    kept.remove(seen[stem])
                    seen[stem] = item
                    kept.append(item)
                else:
                    removed.append(item)

        if not removed:
            continue

        total_removed += len(removed)
        total_files   += 1

        log.info(f"{md_path.stem}:")
        for item in removed:
            log.info(f"  - Duplicato rimosso (score {item['score']}/10): {item['stem']}")

        if not dry_run:
            # Ricostruisci la sezione
            new_lines = []
            # Prima le righe strutturali iniziali (vuote + intestazione + separatore)
            for kind, line in structural:
                new_lines.append(line)
                # Dopo intestazione e separatore inseriamo i dati
                if line.strip().startswith("|---"):
                    for k in kept:
                        new_lines.append(k["line"])
                    break

            new_section = "\n".join(new_lines)
            new_content = (
                content[:start + len(MARKER_START)]
                + new_section + "\n"
                + content[end:]
            )
            md_path.write_text(new_content, encoding="utf-8")

    if dry_run:
        log.info(f"\n[DRY RUN] Verrebbero rimossi {total_removed} duplicati da {total_files} file.")
    else:
        log.info(f"\n✅ Rimossi {total_removed} duplicati da {total_files} file.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Rimuove link duplicati da Documenti Correlati")
    parser.add_argument("--dry-run", action="store_true", help="Mostra cosa verrebbe rimosso senza modificare")
    args = parser.parse_args()
    dedup(dry_run=args.dry_run)
