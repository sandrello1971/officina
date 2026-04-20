"""
cleanup_links.py — Rimuove dalla sezione "Documenti Correlati"
tutti i link con score < 8 da tutti i file .md in _metadata/.

Uso:
    python cleanup_links.py           # rimuove link con score < 8
    python cleanup_links.py --dry-run # mostra cosa verrebbe rimosso senza modificare
    python cleanup_links.py --min 9   # usa soglia diversa
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

MARKER_START = "<!-- RELATED_START -->"
MARKER_END   = "<!-- RELATED_END -->"

# Pattern per estrarre lo score da una riga della tabella
# es: | [[EI289\|Titolo]] | motivazione | 8/10 |
SCORE_PATTERN = re.compile(r"\|\s*(\d+)/10\s*\|?\s*$")


def cleanup(min_score: int = 8, dry_run: bool = False):
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

        kept    = []
        removed = []

        for line in lines:
            stripped = line.strip()
            # Salta righe vuote, intestazione e separatore tabella
            if not stripped or stripped.startswith("|---") or stripped.startswith("| Documento"):
                kept.append(line)
                continue
            # Riga dati — controlla lo score
            if stripped.startswith("|"):
                m = SCORE_PATTERN.search(stripped)
                if m:
                    score = int(m.group(1))
                    if score < min_score:
                        removed.append((stripped, score))
                        continue
            kept.append(line)

        if not removed:
            continue

        total_removed += len(removed)
        total_files   += 1

        log.info(f"{md_path.stem}:")
        for row, score in removed:
            # Estrai il titolo del link per il log
            title_match = re.search(r"\[\[([^\]|]+)[|\\]([^\]]+)\]\]", row)
            title = title_match.group(2) if title_match else row[:60]
            log.info(f"  - Rimosso (score {score}/10): {title}")

        if not dry_run:
            new_section = "\n".join(kept)
            new_content = (
                content[:start + len(MARKER_START)]
                + new_section
                + content[end:]
            )
            md_path.write_text(new_content, encoding="utf-8")

    if dry_run:
        log.info(f"\n[DRY RUN] Verrebbero rimossi {total_removed} link da {total_files} file.")
    else:
        log.info(f"\n✅ Rimossi {total_removed} link da {total_files} file (soglia score >= {min_score}).")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Rimuove link documenti correlati con score basso")
    parser.add_argument("--dry-run", action="store_true", help="Mostra cosa verrebbe rimosso senza modificare")
    parser.add_argument("--min", type=int, default=8, help="Score minimo da mantenere (default: 8)")
    args = parser.parse_args()

    cleanup(min_score=args.min, dry_run=args.dry_run)
